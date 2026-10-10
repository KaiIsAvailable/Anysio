<?php

namespace App\Services;

use App\Models\{Lease, Property, Room, Ticket, TicketMsg, Unit, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantCustomerService
{
    public function validLeases(User $user)
    {
        $today = Carbon::now(config('app.timezone'))->toDateString();
        return Lease::query()->whereHas('tenant', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_current', true)->whereIn('status', ['New', 'Renew', 'active'])
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->whereNull('checked_out_at')->whereNull('agreement_ended_at');
    }

    public function ownerId(Lease $lease): ?string
    {
        $model = $lease->leasable;
        $id = match (true) {
            $model instanceof Property, $model instanceof Unit => $model->owner_id,
            $model instanceof Room => $model->unit?->owner_id,
            default => null,
        };
        return $id && User::whereKey($id)->exists() ? $id : null;
    }

    public function recipient($leases): array
    {
        if ($leases->isEmpty()) {
            return ['owner_id' => null, 'reason' => 'You cannot create a complaint because you have no valid lease. You can still reply to your open tickets.'];
        }
        $owners = $leases->map(fn ($lease) => $this->ownerId($lease));
        if ($owners->contains(null)) {
            return ['owner_id' => null, 'reason' => 'An owner is unavailable for one or more valid leases. Please contact your property manager.'];
        }
        $owners = $owners->unique()->values();
        if ($owners->count() !== 1) {
            return ['owner_id' => null, 'reason' => 'Your valid leases have different owners. The complaint recipient cannot be determined. Please contact your property manager.'];
        }
        return ['owner_id' => $owners->sole(), 'reason' => null];
    }

    public function isClosed(Ticket $ticket): bool
    {
        return in_array(strtolower(trim($ticket->status)), config('customer_service.closed_statuses'), true);
    }

    public function authorize(Ticket $ticket, User $user): void
    {
        abort_unless($user->isTenant() && $ticket->sender_id === $user->id, 403);
    }

    public function attachmentPath(TicketMsg $message): ?string
    {
        $prefix = 'ticket-attachments/'.$message->ticket_id.'/';
        if (!str_starts_with($message->message, $prefix)) {
            return null;
        }
        $name = substr($message->message, strlen($prefix));
        return preg_match('/^[a-f0-9-]{36}\.[a-z0-9]+$/D', $name)
            && array_key_exists(pathinfo($name, PATHINFO_EXTENSION), config('customer_service.types'))
            ? $message->message : null;
    }

    public function validateBatch(Request $request): array
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.config('customer_service.max_items')],
            'items.*' => ['required', 'array:type,text,file'],
            'items.*.type' => ['required', 'in:text,file'],
            'items.*.text' => ['nullable', 'string', 'max:'.config('customer_service.max_text_length')],
            'items.*.file' => ['nullable', 'file'],
        ]);
        $count = 0;
        $total = 0;
        foreach ($data['items'] as $i => &$item) {
            if ($item['type'] === 'text') {
                if (trim($item['text'] ?? '') === '' || isset($item['file'])) {
                    throw ValidationException::withMessages(["items.$i" => 'Please enter a non-empty text message.']);
                }
                // Reserve the server attachment namespace; ordinary legacy text remains readable.
                if (str_starts_with($item['text'], 'ticket-attachments/')) {
                    throw ValidationException::withMessages(["items.$i" => 'This message starts with a reserved attachment path.']);
                }
            } else {
                $file = $item['file'] ?? null;
                if (!$file || !$file->isValid() || !empty($item['text'])) {
                    throw ValidationException::withMessages(["items.$i" => 'Please choose a valid uploaded file.']);
                }
                $extension = strtolower($file->getClientOriginalExtension());
                $type = config('customer_service.types')[$extension] ?? null;
                if (!$type || !in_array($this->actualMime($file->getRealPath(), $extension), $type['mimes'], true)) {
                    throw ValidationException::withMessages(["items.$i" => 'The actual file type does not match an allowed format.']);
                }
                if ($file->getSize() > $type['max_kb'] * 1024) {
                    throw ValidationException::withMessages(["items.$i" => 'This file exceeds its size limit.']);
                }
                $item['extension'] = $extension;
                $count++;
                $total += $file->getSize();
            }
        }
        unset($item);
        if ($count > config('customer_service.max_files') || $total > config('customer_service.max_total_kb') * 1024) {
            throw ValidationException::withMessages(['items' => 'The batch exceeds the file count or total size limit.']);
        }
        return $data['items'];
    }

    private function actualMime(string $path, string $extension): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $office = [
            'docx' => ['word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
            'xlsx' => ['xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'],
            'pptx' => ['ppt/presentation.xml', 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'],
        ];
        if (!isset($office[$extension]) || !in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed'], true)) {
            return $mime;
        }
        // Inspect package metadata without extracting any archive entries.
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return $mime;
        try {
            [$entry, $contentType] = $office[$extension];
            $stat = $zip->statName('[Content_Types].xml');
            if (!$stat || $stat['size'] > 1048576 || !$zip->statName($entry)) return $mime;
            $xml = $zip->getFromName('[Content_Types].xml');
            if (!$xml || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) return $mime;
            $previous = libxml_use_internal_errors(true);
            try {
                $document = new \DOMDocument();
                if (!$document->loadXML($xml, LIBXML_NONET)) return $mime;
                foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override') as $node) {
                    if ($node->getAttribute('PartName') === '/'.$entry && $node->getAttribute('ContentType') === $contentType) {
                        return config('customer_service.types')[$extension]['mimes'][0];
                    }
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            return $mime;
        } finally {
            $zip->close();
        }
    }

    public function append(Ticket $ticket, array $items, array &$uploaded): void
    {
        foreach ($items as $item) {
            $message = $item['text'] ?? '';
            if ($item['type'] === 'file') {
                $name = Str::uuid().'.'.$item['extension'];
                $message = $item['file']->storeAs('ticket-attachments/'.$ticket->id, $name, 'local');
                if (!$message) {
                    throw new \RuntimeException('Unable to store the attachment.');
                }
                $uploaded[] = $message;
            }
            $ticket->messages()->create(['sender_type' => 'tenant', 'message' => $message]);
        }
    }
}

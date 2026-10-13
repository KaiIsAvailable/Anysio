<?php

namespace App\Services;

use App\Models\{Lease, Property, Room, Ticket, TicketMsg, Unit, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantCustomerService
{
    public function attachmentManagerId(User $tenant): string
    {
        // TenantsController records get_effective_user()->id in tenants.created_by.
        // Cross-check current leases using the same resolver as dashboard/penalties.
        $creatorId = $tenant->tenant?->created_by;
        $ids = collect();
        if ($creatorId) {
            $ids->push(User::whereKey($creatorId)->whereIn('role', [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN])->value('id'));
        }
        foreach ($this->validLeases($tenant)->with('leasable')->get() as $lease) {
            $ids->push(app(LeaseManagementResolver::class)->resolve($lease)?->id);
        }
        if ($ids->isEmpty() || $ids->contains(null) || $ids->unique()->count() !== 1) {
            throw ValidationException::withMessages(['attachments' => 'The tenant management account is unavailable or ambiguous. Please contact your property manager.']);
        }
        return $ids->first();
    }

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
        if (preg_match('~^uploads/user_[A-Za-z0-9-]+/tenants/tickets/([a-f0-9-]{36}\.[a-zA-Z0-9]+)$~D', $message->message, $matches)) {
            return array_key_exists(strtolower(pathinfo($matches[1], PATHINFO_EXTENSION)), config('customer_service.types')) ? $message->message : null;
        }
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
        return $this->validateItems($data['items']);
    }

    public function validateCreate(Request $request): array
    {
        $data = $request->validate([
            'details' => ['nullable', 'string', 'max:'.config('customer_service.max_text_length')],
            'attachments' => ['nullable', 'array', 'max:'.config('customer_service.max_files')],
            'attachments.*' => ['required', 'file'],
        ]);
        $items = [];
        if (trim($data['details'] ?? '') !== '') $items[] = ['type' => 'text', 'text' => $data['details']];
        foreach ($data['attachments'] ?? [] as $file) $items[] = ['type' => 'file', 'file' => $file];
        if (!$items) throw ValidationException::withMessages(['details' => 'Enter details or choose at least one attachment.']);
        return $this->validateItems($items);
    }

    private function validateItems(array $items): array
    {
        $count = 0;
        $total = 0;
        foreach ($items as $i => &$item) {
            if ($item['type'] === 'text') {
                if (trim($item['text'] ?? '') === '' || isset($item['file'])) {
                    throw ValidationException::withMessages(["items.$i" => 'Please enter a non-empty text message.']);
                }
                // Reserve the server attachment namespace; ordinary legacy text remains readable.
                if (str_starts_with($item['text'], 'ticket-attachments/') || str_starts_with($item['text'], 'uploads/')) {
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
                    throw ValidationException::withMessages(["items.$i" => $file->getClientOriginalName().': the actual file type does not match an allowed format.']);
                }
                if ($file->getSize() > $type['max_kb'] * 1024) {
                    throw ValidationException::withMessages(["items.$i" => $file->getClientOriginalName().': maximum '.($type['max_kb'] / 1024).' MB per file.']);
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
        return $items;
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
        $managerId = collect($items)->contains(fn ($item) => $item['type'] === 'file')
            ? $this->attachmentManagerId(User::findOrFail($ticket->sender_id)) : null;
        foreach ($items as $item) {
            $message = $item['text'] ?? '';
            if ($item['type'] === 'file') {
                $message = in_array($item['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                    ? app(TicketImageProcessor::class)->upload($item['file'], $managerId)
                    : app(FileService::class)->upload($item['file'], $managerId, 'tenant_ticket');
                if (!$message) {
                    throw new \RuntimeException('Unable to store the attachment.');
                }
                $uploaded[] = $message;
            }
            $ticket->messages()->create(['sender_type' => 'tenant', 'message' => $message]);
        }
    }
}

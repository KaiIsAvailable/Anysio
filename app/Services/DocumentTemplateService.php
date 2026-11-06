<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log;

class DocumentTemplateService
{
    /**
     * Return the variables supported by document templates.
     *
     * The values are intentionally kept separate from the data source. Callers
     * provide the data for the document context being rendered.
     */
    public function variableNames(): array
    {
        return [
            'user_name',
            'user_email',
            'user_phone',
            'user_company_logo',
            'user_company_name',
            'user_old_ssm_number',
            'user_new_ssm_number',
            'user_company_address',
            'user_company_phone_number',
            'user_company_email',
            'user_bank_name',
            'user_bank_code',
            'user_account_holder_name',
            'user_account_number',

            'tenant_name',
            'tenant_ic',
            'tenant_email',
            'tenant_phone',
            'tenant_company_logo',
            'tenant_company_name',
            'tenant_old_ssm_number',
            'tenant_new_ssm_number',
            'tenant_company_address',
            'tenant_company_phone_number',
            'tenant_company_email',
            'tenant_bank_name',
            'tenant_bank_code',
            'tenant_account_holder_name',
            'tenant_account_number',

            'owner_name',
            'owner_ic',
            'owner_email',
            'owner_phone',
            'owner_company_logo',
            'owner_company_name',
            'owner_old_ssm_number',
            'owner_new_ssm_number',
            'owner_company_address',
            'owner_company_phone_number',
            'owner_company_email',
            'owner_bank_name',
            'owner_bank_code',
            'owner_account_holder_name',
            'owner_account_number',
            'bank_name',
            'bank_code',
            'account_holder_name',
            'account_number',

            'property_name',
            'property_type',
            'property_address',
            'start_date',
            'start_date_formatted',
            'end_date',
            'lease_period',
            'check_out_date',
            'check_out_status',
            'rent_price',
            
            'total_deposit',
            'deposit_receipt_no',
            'total_deductions',
            'total_deposit_collected',
            'wallet_refund_amount',
            'auto_deductions_total',
            'custom_deductions_total',
            'nett_refund_amount',
            'unpaid_invoices_breakdown',
            'custom_items_breakdown',
            'security_deposit',
            'utilities_deposit',
            'management_fee',
        ];
    }

    /**
     * Replace template variables with document data.
     *
     * This does not modify the stored template. It supports both GrapesJS
     * data-variable elements and plain {{ variable }} placeholders.
     */
    public function replaceData(string $templateHtml, array $data, array $trustedHtml = []): string
    {
        $data = $this->normalizeVariableData($data);
        $replacements = [];

        Log::channel('testing')->info('Document template replacement started:', [
            'template_html' => $templateHtml,
            'data' => $data,
            'trusted_html' => $trustedHtml,
        ]);

        $templateHtml = preg_replace_callback(
            '/<img\b([^>]*\bdata-variable\s*=\s*(["\'])([^"\']+)\2[^>]*)>/i',
            function (array $matches) use ($data, &$replacements): string {
                $attributes = $matches[1];
                $variable = strtolower(trim($matches[3]));

                if (!in_array($variable, [
                    'user_company_logo',
                    'tenant_company_logo',
                    'owner_company_logo',
                ], true)) {
                    return $matches[0];
                }

                $value = $data[$variable] ?? null;
                $replacements[] = [
                    'type' => 'image',
                    'variable' => $variable,
                    'value' => $value,
                    'found' => array_key_exists($variable, $data),
                ];
                if ($value === null || $value === '') {
                    return $matches[0];
                }

                $escapedValue = e((string) $value);
                $attributes = preg_replace(
                    '/\s+src\s*=\s*(["\'])[^"\']*\1/i',
                    ' src="'.$escapedValue.'"',
                    $attributes,
                    1,
                    $srcCount
                );

                if ($srcCount === 0) {
                    $attributes .= ' src="'.$escapedValue.'"';
                }

                return '<img'.$attributes.'>';
            },
            $templateHtml
        );

        $templateHtml = preg_replace_callback(
            '/<([a-z][a-z0-9:-]*)\b(?=[^>]*\bdata-variable\s*=\s*(["\'])([^"\']+)\2)[^>]*>[\s\S]*?<\/\1>/i',
            function (array $matches) use ($data, $trustedHtml, &$replacements): string {
                $variable = trim($matches[3]);
                $value = $data[$variable] ?? 'N/A';
                $replacements[] = [
                    'type' => 'data-variable',
                    'variable' => $variable,
                    'value' => $value,
                    'found' => array_key_exists($variable, $data),
                ];

                $replacement = $this->formatReplacementValue($variable, $value, $trustedHtml);
                // Preserve the variable element and its typography, including
                // bold tags around the placeholder, rather than dropping it.
                $pattern = '/\{\{\s*'.preg_quote($variable, '/').'\s*\}\}/i';
                $formatted = preg_replace_callback($pattern, fn () => $replacement, $matches[0], -1, $count);

                if ($count > 0) {
                    return $formatted;
                }

                return preg_replace_callback(
                    '/^(<[^>]+>)[\s\S]*(<\/[^>]+>)$/',
                    fn (array $element) => $element[1].$replacement.$element[2],
                    $matches[0]
                );
            },
            $templateHtml
        );

        $renderedTemplate = preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i',
            function (array $matches) use ($data, $trustedHtml, &$replacements): string {
                $variable = strtolower($matches[1]);
                $value = $data[$variable] ?? 'N/A';
                $replacements[] = [
                    'type' => 'placeholder',
                    'variable' => $variable,
                    'value' => $value,
                    'found' => array_key_exists($variable, $data),
                ];

                return $this->formatReplacementValue($variable, $value, $trustedHtml);
            },
            $templateHtml
        );

        Log::channel('testing')->info('Document template replacement completed:', [
            'replacements' => $replacements,
            'rendered_template' => $renderedTemplate,
        ]);

        return $renderedTemplate;
    }

    /**
     * Alias for callers that describe the operation as template rendering.
     */
    public function render(string $templateHtml, array $data, array $trustedHtml = []): string
    {
        return $this->replaceData($templateHtml, $data, $trustedHtml);
    }

    /**
     * Extract variable names used by a template.
     *
     * Supports both GrapesJS data-variable elements and {{ variable }}
     * placeholders.
     *
     * @return list<string>
     */
    public function extractVariableNames(string $templateHtml): array
    {
        preg_match_all(
            '/\bdata-variable\s*=\s*(["\'])([^"\']+)\1/i',
            $templateHtml,
            $dataVariableMatches
        );
        preg_match_all(
            '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i',
            $templateHtml,
            $placeholderMatches
        );

        return collect(array_merge(
            $dataVariableMatches[2] ?? [],
            $placeholderMatches[1] ?? []
        ))
            ->map(fn (string $variable): string => strtolower(trim($variable)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build user-scoped variables, loading company and bank data only when
     * the selected template references those fields.
     *
     * @param list<string>|null $requiredVariables
     */
    public function userVariables(User $user, string $prefix, ?array $requiredVariables = null): array
    {
        $loadAllVariables = $requiredVariables === null;
        $requiredVariables ??= $this->variableNames();
        $hasCompanyVariables = collect($requiredVariables)
            ->contains(fn (string $variable): bool => str_starts_with($variable, "{$prefix}_company_"));
        $hasBankVariables = collect($requiredVariables)
            ->contains(fn (string $variable): bool => in_array($variable, [
                "{$prefix}_bank_name",
                "{$prefix}_bank_code",
                "{$prefix}_account_holder_name",
                "{$prefix}_account_number",
            ], true));
        $company = $hasCompanyVariables ? $this->getCompanyInfo($user) : [];
        $bank = $hasBankVariables ? $this->getBankInfo($user) : [];

        $variables = [
            "{$prefix}_name" => $user->name ?? 'N/A',
            "{$prefix}_email" => $user->email ?? 'N/A',
            "{$prefix}_phone" => $user->phone ?? 'N/A',
            "{$prefix}_company_logo" => $company['logo'] ?? 'N/A',
            "{$prefix}_company_name" => $company['name'] ?? 'N/A',
            "{$prefix}_old_ssm_number" => $company['old_ssm_number'] ?? 'N/A',
            "{$prefix}_new_ssm_number" => $company['new_ssm_number'] ?? 'N/A',
            "{$prefix}_company_address" => $company['address'] ?? 'N/A',
            "{$prefix}_company_phone_number" => $company['phone_number'] ?? 'N/A',
            "{$prefix}_company_email" => $company['email'] ?? 'N/A',
            "{$prefix}_bank_name" => $bank['name'] ?? 'N/A',
            "{$prefix}_bank_code" => $bank['code'] ?? 'N/A',
            "{$prefix}_account_holder_name" => $bank['account_holder_name'] ?? 'N/A',
            "{$prefix}_account_number" => $bank['account_number'] ?? 'N/A',
        ];

        if ($loadAllVariables) {
            return $variables;
        }

        return array_intersect_key($variables, array_flip($requiredVariables));
    }

    /**
     * Return the first company associated with the user for template rendering.
     *
     * @return array<string, mixed>
     */
    public function getCompanyInfo(User $user): array
    {
        $user->loadMissing('companies');
        $company = $user->companies->first();

        return [
            'logo' => $company?->logo_path
                ? asset('storage/'.ltrim($company->logo_path, '/'))
                : null,
            'name' => $company?->name,
            'old_ssm_number' => $company?->ssm_old_number,
            'new_ssm_number' => $company?->ssm_new_number,
            'address' => $company?->address,
            'phone_number' => $company?->phone,
            'email' => $company?->email,
        ];
    }

    public function identificationValue(?\Illuminate\Database\Eloquent\Model $person, ?User $user): string
    {
        foreach ([$person?->ic_number, $person?->passport] as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        $number = $user ? ($this->getCompanyInfo($user)['new_ssm_number'] ?? null) : null;

        return filled($number) ? (string) $number : 'N/A';
    }

    /**
     * Return the user's default bank account for template rendering.
     *
     * @return array<string, mixed>
     */
    public function getBankInfo(User $user): array
    {
        $user->loadMissing([
            'bankUsers' => fn ($query) => $query
                ->where('is_default', true)
                ->with('bankDetail'),
        ]);
        $bank = $user->bankUsers->first()?->bankDetail;

        return [
            'name' => $bank?->bank_name,
            'code' => $bank?->bank_code,
            'account_holder_name' => $bank?->account_holder_name,
            'account_number' => $bank?->account_number,
        ];
    }

    public function replaceRefundTable(string $templateHtml, array $items, array $summary = []): string
    {
        $depositReceiptNo = e((string) ($summary['deposit_receipt_no'] ?? 'N/A'));
        $depositAmount = e((string) ($summary['total_deposit_collected'] ?? '0.00'));
        $depositCents = (int) round((float) str_replace(',', '', $depositAmount) * 100);
        $creditItems = [[
            'description' => 'Deposit Collected ('.$depositReceiptNo.')',
            'type' => 'deposit',
            'qty' => 1,
            'amount_cents' => $depositCents,
        ]];
        $walletCents = (int) round((float) str_replace(',', '', (string) ($summary['wallet_refund_amount'] ?? '0.00')) * 100);
        if ($walletCents > 0) {
            $creditItems[] = [
                'description' => 'Wallet Balance to Refund',
                'type' => 'wallet',
                'qty' => 1,
                'amount_cents' => $walletCents,
            ];
        }
        $statementItems = array_merge($creditItems, $items);
        $rows = collect($statementItems)->values()->map(function ($item, $index): string {
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                $description = data_get($item, 'fee_type.name')
                    ?? $item['fee_type_name']
                    ?? 'Refund deduction';
            }
            $description = e($description);
            $quantity = e((string) ($item['qty'] ?? 1));
            $amountCents = (int) ($item['amount_cents'] ?? 0);
            if (!empty($item['invoice_no'])) {
                $description .= ' ('.e((string) $item['invoice_no']).')';
            }

            return '<tr>'
                . '<td style="border: 1px solid #cbd5e1; padding: 10px;">'.($index + 1).'</td>'
                . '<td style="border: 1px solid #cbd5e1; padding: 10px;">'.$description.'</td>'
                . '<td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">'.$quantity.'</td>'
                . '<td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right;">RM '
                .number_format($amountCents / 100, 2).'</td>'
                . '</tr>';
        })->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="4" style="border: 1px dashed #bbf7d0; padding: 20px; text-align: center; color: #166534; font-weight: bold;">No refund deduction items</td></tr>';
        }

        return preg_replace(
            '/<tbody\b[^>]*id=["\']dynamic-refund-tbody["\'][^>]*>[\s\S]*?<\/tbody>/i',
            '<tbody id="dynamic-refund-tbody">'.$rows.'</tbody>',
            $templateHtml
        );
    }

    /**
     * Normalize supported legacy variable names to the canonical names.
     */
    private function normalizeVariableData(array $data): array
    {
        if (!array_key_exists('start_date_formatted', $data)) {
            $data['start_date_formatted'] = 'N/A';
            $startDate = $data['start_date'] ?? null;
            if ($startDate instanceof \DateTimeInterface) {
                $data['start_date_formatted'] = $startDate->format('dS F Y');
            } elseif (is_string($startDate)) {
                foreach (['Y-m-d', 'd/m/Y'] as $format) {
                    try {
                        $date = \Carbon\Carbon::createFromFormat('!'.$format, $startDate);
                        if ($date && $date->format($format) === $startDate) {
                            $data['start_date_formatted'] = $date->format('dS F Y');
                            break;
                        }
                    } catch (\InvalidArgumentException $exception) {
                        // Try the next supported source date format.
                    }
                }
            }
        }

        $aliases = [
            'company_phone' => 'company_phone_number',
            'checkout_date' => 'check_out_date',
        ];

        foreach ($aliases as $alias => $canonical) {
            if (!array_key_exists($canonical, $data) && array_key_exists($alias, $data)) {
                $data[$canonical] = $data[$alias];
            }
        }

        foreach ($aliases as $alias => $canonical) {
            if (!array_key_exists($alias, $data) && array_key_exists($canonical, $data)) {
                $data[$alias] = $data[$canonical];
            }
        }

        $data['check_out_status'] = $this->checkOutStatus(
            $data['check_out_date'] ?? null, $data['end_date'] ?? null
        );

        return $data;
    }

    public function checkOutStatus(mixed $checkOutDate, mixed $endDate): string
    {
        $parse = static function (mixed $value): ?\Carbon\Carbon {
            if ($value instanceof \DateTimeInterface) {
                return \Carbon\Carbon::instance($value)->startOfDay();
            }
            if (!is_string($value)) return null;
            foreach (['Y-m-d', 'd/m/Y'] as $format) {
                try {
                    $date = \Carbon\Carbon::createFromFormat('!'.$format, $value);
                    if ($date && $date->format($format) === $value) return $date;
                } catch (\InvalidArgumentException $exception) {
                    // Missing or invalid dates do not produce a status label.
                }
            }
            return null;
        };
        $checkout = $parse($checkOutDate);
        $end = $parse($endDate);
        if (!$checkout || !$end || !$checkout->lt($end)) return '';

        $days = (int) $checkout->diffInDays($end);

        return 'Early Check Out ('.$days.' '.($days === 1 ? 'day' : 'days').')';
    }

    private function formatReplacementValue(string $variable, mixed $value, array $trustedHtml): string
    {
        if ($variable === 'check_out_status') {
            return ($value === null || $value === 'N/A') ? '' : e((string) $value);
        }
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if (in_array($variable, [
            'user_company_logo',
            'tenant_company_logo',
            'owner_company_logo',
        ], true)) {
            $logoUrl = e((string) $value);

            return '<img src="'.$logoUrl.'" alt="Company Logo" style="max-width: 180px; max-height: 80px; object-fit: contain;">';
        }

        if (in_array($variable, $trustedHtml, true)) {
            return (string) $value;
        }

        return e((string) $value);
    }

    public function create(array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($data) {$userId = empty($data['user_id']) ? null : $data['user_id'];
            $category =$data['category'];

            // 🌟 核心防呆：在建立新的 Active 範本前，先把同一個人的同分類舊範本強制退休 (Inactive)
            DocumentTemplate::where('user_id', $userId)
                ->where('category', $category)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            return DocumentTemplate::create([
                'parent_id' => null, // Create 永远是始祖
                'user_id' => $userId,
                'created_by' => Auth::id(),
                'category' => $category,
                'title' => $data['title'],
                'version' => $data['version'],
                'details' => $data['details'] ?? '',
                'html_template' => $data['html_template'],
                'status' => 'active', // 新建直接设为 active
                'is_system_default' => empty($data['user_id']),
            ]);
        });
    }

    // 💡 处理编辑后生成新版本的逻辑
    public function createNewVersion(DocumentTemplate $oldTemplate, array$data): DocumentTemplate
    {
        return DB::transaction(function () use ($oldTemplate,$data) {
            // 1. 找到这个家族的始祖 ID
            $rootParentId = $oldTemplate->parent_id ?: $oldTemplate->id;

            // 2. 将这棵树上的所有旧版本设为 inactive
            DocumentTemplate::where(function ($q) use ($rootParentId) {
                $q->where('id',$rootParentId)
                  ->orWhere('parent_id', $rootParentId);
            })->update(['status' => 'inactive']);

            // 3. 创建新的版本，并绑定 parent_id 认祖归宗
            return DocumentTemplate::create([
                'parent_id' => $rootParentId,
                'user_id' => $data['user_id'] ?: null,
                'created_by' => Auth::id(),
                'category' => $data['category'],
                'title' => $data['title'],
                'version' => $data['version'],
                'details' => $data['details'] ?? '',
                'html_template' => $data['html_template'],
                'status' => 'active', // 新版本自动成为 active
                'is_system_default' => empty($data['user_id']),
            ]);
        });
    }
}

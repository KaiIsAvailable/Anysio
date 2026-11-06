<?php

namespace Tests\Feature;

use App\Services\DocumentTemplateService;
use Tests\TestCase;

class DocumentTemplateTypographyTest extends TestCase
{
    public function test_checkout_status_is_early_by_calendar_days_or_empty(): void
    {
        config(['logging.channels.testing' => ['driver' => 'null']]);
        $service = app(DocumentTemplateService::class);
        foreach ([
            ['2026-11-01', '2026-11-30', 'Early Check Out (29 days)'],
            ['29/11/2026', '30/11/2026', 'Early Check Out (1 day)'],
            ['2026-11-30', '2026-11-30', ''],
            ['2026-12-01', '2026-11-30', ''],
            [null, '2026-11-30', ''],
            ['2026-11-01', null, ''],
            ['2026-02-30', '2026-03-01', ''],
        ] as [$checkout, $end, $expected]) {
            $this->assertSame($expected, $service->replaceData('{{ check_out_status }}', [
                'check_out_date' => $checkout, 'end_date' => $end,
            ]));
            $this->assertSame('<span data-variable="check_out_status">'.$expected.'</span>',
                $service->replaceData('<span data-variable="check_out_status">{{ check_out_status }}</span>', [
                    'check_out_date' => $checkout, 'end_date' => $end,
                ]));
        }
        $this->assertSame('', $service->replaceData('{{ check_out_status }}', []));
    }

    public function test_identification_prefers_ic_then_passport_then_company_registration(): void
    {
        $service = app(DocumentTemplateService::class);
        $user = (new \App\Models\User())->setRelation('companies', collect([
            new \App\Models\Company(['ssm_new_number' => '202601000123']),
        ]));
        foreach ([\App\Models\Tenants::class, \App\Models\Owners::class] as $model) {
            $person = new $model();
            $person->ic_number = 'IC123';
            $person->passport = 'P123';
            $this->assertSame('IC123', $service->identificationValue($person, $user));
            $person->ic_number = null;
            $this->assertSame('P123', $service->identificationValue($person, $user));
            $person->passport = null;
            $this->assertSame('202601000123', $service->identificationValue($person, $user));
            $person->ic_number = ' ';
            $person->passport = '';
            $this->assertSame('202601000123', $service->identificationValue($person, $user));
            $this->assertSame('N/A', $service->identificationValue($person, null));
        }
    }

    public function test_start_date_formatted_supports_form_and_saved_lease_dates(): void
    {
        config(['logging.channels.testing' => ['driver' => 'null']]);
        $service = app(DocumentTemplateService::class);
        foreach ([
            '2026-01-01' => '01st January 2026',
            '01/01/2026' => '01st January 2026',
            '2026-01-02' => '02nd January 2026',
            '2026-01-03' => '03rd January 2026',
            '2026-01-11' => '11th January 2026',
            '2026-01-12' => '12th January 2026',
            '2026-01-13' => '13th January 2026',
            '2026-01-21' => '21st January 2026',
            '2026-02-30' => 'N/A',
            '' => 'N/A',
        ] as $input => $expected) {
            $this->assertSame($expected, $service->replaceData(
                '{{ start_date_formatted }}', ['start_date' => $input]
            ));
        }
    }

    public function test_replacing_variables_preserves_template_typography(): void
    {
        config(['logging.channels.testing' => ['driver' => 'null']]);
        $service = app(DocumentTemplateService::class);
        foreach ([
            '<span data-variable="property_address" style="font-weight:700">{{ property_address }}</span>',
            '<span data-variable="property_address"><b>{{ property_address }}</b></span>',
            '<p>{{ property_address }}</p>',
        ] as $template) {
            $this->assertSame(
                str_replace('{{ property_address }}', 'Street &lt;A&gt;', $template),
                $service->replaceData($template, ['property_address' => 'Street <A>'])
            );
        }
    }
}

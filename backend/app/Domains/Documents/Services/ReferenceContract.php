<?php

namespace App\Domains\Documents\Services;

use App\Domains\Credit\Services\CreditCalculator;
use Carbon\CarbonImmutable;
use NumberFormatter;

/** Fixed transcription of the supplied PDF. No spelling or clause rewriting. */
final class ReferenceContract
{
    public function payload(array $snapshot): array
    {
        abort_unless(class_exists(NumberFormatter::class), 503, 'Activa la extensión PHP intl para generar importes en letra.');
        $reference = json_decode(file_get_contents(resource_path('documents/contract-reference.json')), true, flags: JSON_THROW_ON_ERROR);
        $total = $snapshot['terms']['totals']['total_repayment'];
        $cents = CreditCalculator::scaled($total);
        $formatter = new NumberFormatter('es_MX', NumberFormatter::SPELLOUT);
        $number = mb_strtoupper($formatter->format(intdiv($cents, 100)));
        $words = $number.' PESOS '.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT).'/100 M.N.';
        $count = count($snapshot['terms']['members']);
        $date = CarbonImmutable::parse($snapshot['schedule'][15]['due_on'])->locale('es');
        $replace = [':amount_words_number' => $number, ':amount_words' => $words,
            ':count_words' => mb_strtoupper($formatter->format($count)), ':count' => (string) $count,
            ':amount' => number_format(intdiv($cents, 100), 0, '.', ',').'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT),
            ':group' => $snapshot['group'], ':due_date' => mb_strtoupper($date->translatedFormat('d \D\E F \D\E\L Y')),
            // Signature data must not be guessed from generation/approval dates.
            ':signing_city' => $snapshot['document_profile']['fields']['signing_place'] ?: '___________________________',
            ':signing_date' => '___________________________',
        ];
        foreach (['promissory_text', 'annex_text', 'rules_text', 'payment_conditions_text'] as $key) {
            $reference[$key] = strtr($reference[$key], $replace);
        }
        $reference['contract_paragraphs'] = array_map(fn ($text) => strtr($text, $replace), $reference['contract_paragraphs']);

        return $reference + ['amount' => $replace[':amount']];
    }
}

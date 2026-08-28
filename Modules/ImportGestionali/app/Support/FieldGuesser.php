<?php

namespace Modules\ImportGestionali\Support;

/**
 * Best-effort pre-fill of the column mapping from the header names, so the
 * user usually only has to check it rather than build it. Italian and English
 * synonyms; the user always overrides in the mapping step.
 */
final class FieldGuesser
{
    /**
     * Field => normalized synonyms. Order matters: the first field that
     * matches wins, and each field is assigned at most once per header.
     *
     * @var array<string, list<string>>
     */
    private const SYNONYMS = [
        'sku' => ['sku', 'codice', 'cod', 'codicearticolo', 'codart', 'codprodotto', 'articolo', 'ref', 'riferimento', 'barcode', 'ean'],
        'name' => ['nome', 'name', 'descrizione', 'descrizionebreve', 'titolo', 'denominazione', 'desc', 'prodotto'],
        'description' => ['descrizioneestesa', 'descrizionelunga', 'descrizionecompleta', 'descrizione2', 'longdescription', 'dettaglio', 'note'],
        'price' => ['prezzo', 'price', 'prezzovendita', 'prezzolistino', 'prezzopubblico', 'importo', 'listino', 'pubblico'],
        'stock' => ['giacenza', 'stock', 'quantita', 'qta', 'qty', 'disponibilita', 'disponibile', 'magazzino', 'scorta'],
        'weight' => ['peso', 'weight', 'pesokg', 'kg'],
        'length' => ['lunghezza', 'length', 'lung', 'profondita', 'prof'],
        'width' => ['larghezza', 'width', 'larg'],
        'height' => ['altezza', 'height', 'alt'],
        'status' => ['stato', 'status', 'statoprodotto', 'pubblicato', 'attivo'],
    ];

    /**
     * @param  list<string>  $header
     * @return array<int, string> column index => field ('' when not guessed)
     */
    public static function forHeader(array $header): array
    {
        $mapping = [];
        $taken = [];

        foreach ($header as $index => $name) {
            $field = self::guess($name);
            $mapping[$index] = ($field !== null && ! isset($taken[$field])) ? $field : '';

            if ($mapping[$index] !== '') {
                $taken[$field] = true;
            }
        }

        return $mapping;
    }

    public static function guess(string $header): ?string
    {
        $needle = self::normalize($header);

        if ($needle === '') {
            return null;
        }

        foreach (self::SYNONYMS as $field => $synonyms) {
            if (in_array($needle, $synonyms, true)) {
                return $field;
            }
        }

        foreach (self::SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (strlen($synonym) >= 4 && (str_contains($needle, $synonym) || str_contains($synonym, $needle))) {
                    return $field;
                }
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }
}

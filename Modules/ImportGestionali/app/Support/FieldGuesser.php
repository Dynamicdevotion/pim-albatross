<?php

namespace Modules\ImportGestionali\Support;

use Modules\Localization\Support\Locales;

/**
 * Best-effort pre-fill of the column mapping from the header names, so the
 * user usually only has to check it rather than build it. Italian and English
 * synonyms; the user always overrides in the mapping step.
 *
 * A bare "Nome"/"Descrizione"-style header (no language hint) guesses to the
 * base language's translation target — the same meaning `name`/`description`
 * used to have before the dynamic "Traduzioni" group existed (see
 * {@see MappingTarget}). A header naming a specific non-base language (e.g.
 * "Nome EN") is not guessed — the synonym lists have no per-language variants
 * to match against, so that column is left for the user to map by hand.
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
        // Checked before `sku` on purpose: a "Codice Padre" / "SKU padre"
        // header must not be swallowed by the looser `sku` synonyms ('codice',
        // 'ref', …) in the fuzzy pass.
        'parent_sku' => ['codicepadre', 'skupadre', 'parentsku', 'parent', 'padre', 'codpadre', 'articolopadre', 'codicearticolopadre'],
        'sku' => ['sku', 'codice', 'cod', 'codicearticolo', 'codart', 'codprodotto', 'articolo', 'ref', 'riferimento', 'barcode', 'ean'],
        'name' => ['nome', 'name', 'descrizione', 'descrizionebreve', 'titolo', 'denominazione', 'desc', 'prodotto'],
        'description' => ['descrizioneestesa', 'descrizionelunga', 'descrizionecompleta', 'descrizione2', 'longdescription', 'dettaglio', 'note'],
        'meta_title' => ['metatitle', 'titoloseo', 'seotitle', 'metatag', 'titleseo'],
        'meta_description' => ['metadescription', 'descrizioneseo', 'seodescription', 'metadesc'],
        'slug' => ['slug', 'urlseo', 'permalink', 'friendlyurl'],
        'price' => ['prezzo', 'price', 'prezzovendita', 'prezzolistino', 'prezzopubblico', 'importo', 'listino', 'pubblico'],
        'stock' => ['giacenza', 'stock', 'quantita', 'qta', 'qty', 'disponibilita', 'disponibile', 'magazzino', 'scorta'],
        'weight' => ['peso', 'weight', 'pesokg', 'kg'],
        'length' => ['lunghezza', 'length', 'lung', 'profondita', 'prof'],
        'width' => ['larghezza', 'width', 'larg'],
        'height' => ['altezza', 'height', 'alt'],
        'status' => ['stato', 'status', 'statoprodotto', 'pubblicato', 'attivo'],
    ];

    /**
     * Fields that, once guessed, mean "the base language" and must be
     * translated into the equivalent `translation:{baseLanguageId}:{field}`
     * target rather than kept as a bare string.
     *
     * @var list<string>
     */
    private const TRANSLATABLE = ['name', 'description', 'meta_title', 'meta_description', 'slug'];

    /**
     * @param  list<string>  $header
     * @param  array<int, string>  $taxonomies  id => name; a header that matches
     *                                          a taxonomy name exactly (normalized)
     *                                          is pre-mapped to `taxonomy:{id}`
     * @return array<int, string> column index => target ('' when not guessed)
     */
    public static function forHeader(array $header, array $taxonomies = []): array
    {
        $taxonomyByName = [];

        foreach ($taxonomies as $id => $name) {
            $taxonomyByName[self::normalize($name)] = MappingTarget::forTaxonomy($id);
        }

        $baseLanguageId = Locales::base()->id;
        $mapping = [];
        $taken = [];

        foreach ($header as $index => $name) {
            $target = $taxonomyByName[self::normalize($name)] ?? self::guess($name, $baseLanguageId);
            $mapping[$index] = ($target !== null && $target !== '' && ! isset($taken[$target])) ? $target : '';

            if ($mapping[$index] !== '') {
                $taken[$mapping[$index]] = true;
            }
        }

        return $mapping;
    }

    public static function guess(string $header, ?int $baseLanguageId = null): ?string
    {
        $needle = self::normalize($header);

        if ($needle === '') {
            return null;
        }

        $field = self::matchSynonym($needle);

        if ($field === null) {
            return null;
        }

        if (in_array($field, self::TRANSLATABLE, true)) {
            return MappingTarget::forTranslation($baseLanguageId ?? Locales::base()->id, $field);
        }

        return $field;
    }

    private static function matchSynonym(string $needle): ?string
    {
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

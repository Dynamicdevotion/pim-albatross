<?php

// Stringhe UI del pannello admin (etichette, azioni, messaggi). I contenuti di
// prodotti e tassonomie sono tradotti a parte dal modulo Localization.

return [
    'nav' => [
        'pricing' => 'Prezzi',
    ],

    'resource' => [
        'product' => ['label' => 'Prodotto', 'plural' => 'Prodotti'],
        'taxonomy' => ['label' => 'Tassonomia', 'plural' => 'Tassonomie'],
        'price_list' => ['label' => 'Listino', 'plural' => 'Listini'],
        'language' => ['label' => 'Lingua', 'plural' => 'Lingue'],
    ],

    'page' => [
        'bulk_prices' => [
            'nav' => 'Modifica prezzi in blocco',
            'title' => 'Modifica prezzi in blocco',
        ],
    ],

    'field' => [
        'name' => 'Nome',
        'sku' => 'SKU',
        'external_id' => 'ID esterno',
        'status' => 'Stato',
        'type' => 'Tipo',
        'stock' => 'Giacenza',
        'variants' => 'Varianti',
        'variant' => 'Variante',
        'participating_taxonomies' => 'Tassonomie coinvolte',
        'variant_values' => 'Valori da combinare',
        'description' => 'Descrizione',
        'slug' => 'Slug',
        'parent' => 'Genitore',
        'children' => 'Figli',
        'code' => 'Codice',
        'base' => 'Base',
        'active' => 'Attiva',
        'default' => 'Predefinito',
        'price' => 'Prezzo',
        'prices' => 'Prezzi',
        'price_list' => 'Listino',
        'price_min' => 'Prezzo min',
        'price_max' => 'Prezzo max',
        'terms' => 'Termini',
        'taxonomy_terms' => 'Termini di tassonomia',
        'translations' => 'Traduzioni',
        'source_list' => 'Listino di origine',
        'adjustment_percent' => 'Variazione %',
        'category' => 'Categoria',
        'saved_view' => 'Vista salvata',
        'search' => 'Cerca',
        'columns' => 'Colonne',
        'base_suffix' => ' — base',
        'weight' => 'Peso',
        'length' => 'Lunghezza',
        'width' => 'Larghezza',
        'height' => 'Altezza',
        'image' => 'Immagine',
        'main_image' => 'Immagine principale',
        'gallery' => 'Galleria',
    ],

    'option' => [
        'status' => [
            'draft' => 'Bozza',
            'active' => 'Attivo',
            'archived' => 'Archiviato',
        ],
        'type' => [
            'simple' => 'Semplice',
            'variable' => 'Variabile',
            'variant' => 'Variante',
        ],
        'price' => [
            'all' => 'Tutti i prodotti',
            'with' => 'Con prezzo',
            'without' => 'Senza prezzo',
        ],
        'variant_scope' => [
            'all' => 'Tutti',
            'variants' => 'Solo varianti',
            'simple' => 'Solo semplici',
        ],
        'stock' => [
            'zero' => 'Giacenza a zero',
            'low' => 'Bassa (≤ :threshold)',
        ],
        'any' => 'Qualsiasi',
        'none' => '— nessuna —',
    ],

    'filter' => [
        'missing_translation' => 'Traduzione mancante',
        'price' => 'Prezzo',
        'type' => 'Tipo',
        'variant_scope' => 'Varianti',
        'taxonomy_terms' => 'Tassonomia',
        'price_presence' => 'Presenza prezzo',
    ],

    'action' => [
        'set_default' => 'Imposta come predefinito',
        'deactivate' => 'Disattiva…',
        'assign_taxonomy_terms' => 'Assegna termini di tassonomia',
        'set_price' => 'Imposta prezzo',
        'adjust_selection' => 'Variazione % (selezione)',
        'adjust_category' => 'Variazione % (categoria)',
        'save_view' => 'Salva come vista',
        'update_view' => 'Aggiorna vista',
        'delete_view' => 'Elimina vista',
        'add_price' => 'Aggiungi un prezzo',
        'generate_variants' => 'Genera varianti',
        'add_variant' => 'Aggiungi una variante',
    ],

    'section' => [
        'populate_prices' => 'Popola i prezzi da un altro listino',
        'populate_prices_hint' => 'Opzionale. Copia tutti i prezzi dal listino scelto nel nuovo, applicando una variazione percentuale.',
        'dimensions' => 'Dimensioni e peso',
        'media' => 'Immagini',
    ],

    'helper' => [
        'slug_from_name' => 'Lascia vuoto per generarlo dal nome base.',
        'language_code' => 'ISO 639-1, minuscolo (es. "it", "en").',
        'percent' => 'es. 10 per +10%, -15 per −15%. Vuoto copia i prezzi invariati.',
        'percent_short' => 'es. 10 per +10%, -15 per −15%.',
        'adjust_category_scope' => 'Si applica solo al listino selezionato sopra.',
        'variant_sku' => 'Proposto — modificabile prima di generare.',
        'generate_variants' => 'Scegli quali tassonomie definiscono le varianti e quali valori combinare. Viene creata una variante per ogni combinazione.',
        'variable_no_price' => 'Un prodotto variabile raggruppa le sue varianti; prezzo e giacenza sono su ciascuna variante.',
        'variant_main_image' => 'Se vuota, la variante mostra l\'immagine principale del prodotto padre.',
    ],

    'modal' => [
        'set_default_hint' => 'Questo listino diventa predefinito ed è forzato attivo; il predefinito attuale viene declassato.',
        'deactivate_heading' => 'Disattiva :name',
        'deactivate_hint' => 'Questa lingua ha già contenuti tradotti nel catalogo.',
        'deactivate_mode' => [
            'keep' => 'Mantieni i contenuti — nascondili soltanto (riappaiono riattivando la lingua)',
            'delete' => 'Elimina tutte le traduzioni in questa lingua',
        ],
    ],

    'notification' => [
        'language_activated' => ':name attivata',
        'language_deactivated' => ':name disattivata',
        'content_deleted' => ':count traduzioni rimosse.',
        'content_kept' => 'Contenuti mantenuti e nascosti.',
        'price_list_default' => ':name è ora il listino predefinito',
        'terms_assigned' => 'Termini assegnati a :count prodotti',
        'view_saved' => 'Vista ":name" salvata',
        'view_updated' => 'Vista ":name" aggiornata',
        'view_deleted' => 'Vista eliminata',
        'prices_set' => ':count prezzi impostati',
        'prices_adjusted_selection' => ':count prezzi modificati (le righe senza prezzo in questo listino sono state saltate)',
        'prices_adjusted_category' => ':count prezzi modificati in questo listino',
        'select_rows_first' => 'Seleziona prima alcune righe nella griglia',
        'variants_generated' => ':count varianti generate',
        'variants_generated_partial' => ':created generate, :skipped saltate (SKU già esistente)',
        'variants_none_selected' => 'Seleziona almeno un valore da combinare',
        'too_many_combinations' => 'Troppe combinazioni (:count) — seleziona meno valori (massimo :max)',
    ],

    'column' => [
        'variants_count' => '{0} nessuna variante|{1} :count variante|[2,*] :count varianti',
    ],

    'grid' => [
        'search_placeholder' => 'Nome o SKU',
        'row_cap' => 'Mostrati i primi :count prodotti — restringi i filtri per vedere gli altri.',
    ],

    'validation' => [
        'type_locked_has_variants' => 'Elimina prima le varianti collegate per cambiare il tipo di questo prodotto.',
        'variant_needs_parent' => 'Una variante deve appartenere a un prodotto variabile.',
        'only_variant_has_parent' => 'Solo una variante può avere un prodotto genitore.',
        'parent_not_variable' => 'Il prodotto genitore non è di tipo variabile.',
    ],

    'tooltip' => [
        'translated_languages' => 'Lingue per cui questo prodotto ha contenuti',
        'variants_count' => 'Numero di varianti di questo prodotto',
    ],
];

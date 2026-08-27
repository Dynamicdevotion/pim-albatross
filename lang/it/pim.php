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
    ],

    'option' => [
        'status' => [
            'draft' => 'Bozza',
            'active' => 'Attivo',
            'archived' => 'Archiviato',
        ],
        'price' => [
            'all' => 'Tutti i prodotti',
            'with' => 'Con prezzo',
            'without' => 'Senza prezzo',
        ],
        'any' => 'Qualsiasi',
        'none' => '— nessuna —',
    ],

    'filter' => [
        'missing_translation' => 'Traduzione mancante',
        'price' => 'Prezzo',
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
    ],

    'section' => [
        'populate_prices' => 'Popola i prezzi da un altro listino',
        'populate_prices_hint' => 'Opzionale. Copia tutti i prezzi dal listino scelto nel nuovo, applicando una variazione percentuale.',
    ],

    'helper' => [
        'slug_from_name' => 'Lascia vuoto per generarlo dal nome base.',
        'language_code' => 'ISO 639-1, minuscolo (es. "it", "en").',
        'percent' => 'es. 10 per +10%, -15 per −15%. Vuoto copia i prezzi invariati.',
        'percent_short' => 'es. 10 per +10%, -15 per −15%.',
        'adjust_category_scope' => 'Si applica solo al listino selezionato sopra.',
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
    ],

    'grid' => [
        'search_placeholder' => 'Nome o SKU',
        'row_cap' => 'Mostrati i primi :count prodotti — restringi i filtri per vedere gli altri.',
    ],

    'tooltip' => [
        'translated_languages' => 'Lingue per cui questo prodotto ha contenuti',
    ],
];

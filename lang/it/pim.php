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
        'missing_translation_any' => 'Una qualsiasi lingua attiva',
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

    // Modulo ImportGestionali — import prodotti da CSV/Excel.
    'import' => [
        'nav' => [
            'group' => 'Import',
            'upload' => 'Importa prodotti',
            'history' => 'Esiti import',
        ],
        'page' => [
            'title' => 'Importa prodotti da file',
        ],
        'step' => [
            'upload' => 'Carica il file',
            'map' => 'Associa le colonne',
            'preview' => 'Anteprima',
        ],
        'confirm' => 'Conferma e importa',
        'field' => [
            'file' => 'File CSV o Excel',
            'ignore' => '— ignora questa colonna —',
            'sku' => 'SKU',
            'name' => 'Nome',
            'description' => 'Descrizione',
            'price' => 'Prezzo',
            'stock' => 'Giacenza',
            'weight' => 'Peso',
            'length' => 'Lunghezza',
            'width' => 'Larghezza',
            'height' => 'Altezza',
            'status' => 'Stato',
            'image_url' => 'Immagine principale (URL)',
            'gallery_urls' => 'Galleria (URL separati da |)',
            'update_existing' => 'Aggiorna i prodotti già presenti',
        ],
        'help' => [
            'file' => 'Formati accettati: CSV, XLSX, ODS. Il vecchio formato .xls (Excel 97-2003) non è supportato: esportalo come .xlsx o .csv.',
            'map' => 'Per ogni colonna del file scegli il campo del sistema a cui corrisponde. SKU è obbligatorio: è la chiave con cui i prodotti vengono riconosciuti. Le colonne immagine contengono URL da cui scaricare i file; se ne mappi una, l\'import viene sempre elaborato in coda.',
            'update_existing' => 'Disattivato: le righe con uno SKU già presente vengono saltate e segnalate nel report. Attivato: aggiornano il prodotto esistente; i campi lasciati vuoti non vengono toccati.',
            'sample' => 'es. :values',
        ],
        'preview' => [
            'intro' => 'Anteprima delle prime :shown righe su :total totali.',
            'update_on' => 'Aggiornamento prodotti esistenti: attivo',
            'update_off' => 'Aggiornamento prodotti esistenti: disattivato',
            'empty' => 'Nessuna riga da mostrare.',
            'outcome' => 'Esito previsto',
            'will_create' => 'Verrà creato',
            'will_update' => "Aggiornerà l'esistente",
            'will_skip' => 'Saltata',
        ],
        'notify' => [
            'done' => 'Import completato',
            'queued' => 'Import avviato: il file è grande, l\'elaborazione prosegue in coda',
        ],
        'status' => [
            'pending' => 'In coda',
            'processing' => 'In corso',
            'completed' => 'Completato',
            'failed' => 'Fallito',
        ],
        'col' => [
            'when' => 'Data',
            'user' => 'Utente',
            'created' => 'Creati',
            'updated' => 'Aggiornati',
            'skipped' => 'Saltati',
        ],
        'record' => [
            'label' => 'Esito import',
            'plural' => 'Esiti import',
        ],
        'report' => [
            'summary' => 'Riepilogo',
            'counts' => 'Risultato',
            'started' => 'Iniziato',
            'finished' => 'Terminato',
            'total_rows' => 'Righe nel file',
            'error' => 'Errore',
            'skipped_rows' => 'Righe con problemi',
            'more' => '…e altre :count segnalazioni.',
            'running_hint' => "L'import è ancora in corso. Ricarica la pagina per aggiornare i numeri.",
        ],
        'error' => [
            'title' => 'File non importabile',
            'cannot_open' => 'Il file non è un CSV o un Excel valido, oppure è danneggiato.',
            'parse_failed' => 'Non riesco a leggere il contenuto del file: potrebbe essere danneggiato o in un formato non previsto.',
            'unsupported_type' => 'Il formato :ext non è supportato. Salva il file come CSV o XLSX.',
            'bad_header' => "Non riesco a leggere l'intestazione delle colonne. Controlla che la prima riga contenga i nomi delle colonne.",
            'no_rows' => 'Il file non contiene righe di dati.',
            'bad_encoding' => 'Il file ha una codifica non supportata. Riesporta dal gestionale in UTF-8.',
            'not_inspected' => 'Carica prima un file valido.',
            'sku_unmapped' => 'Devi assegnare una colonna al campo SKU: è la chiave per riconoscere i prodotti.',
            'field_mapped_twice' => 'Il campo «:field» è assegnato a più di una colonna.',
            'file_gone' => 'Il file caricato non è più disponibile. Ricaricalo e riprova.',
            'unexpected' => "Errore imprevisto durante l'import.",
        ],
        'issue' => [
            'sku_missing' => 'riga :line: SKU mancante',
            'sku_dup_in_file' => 'riga :line: SKU «:sku» duplicato nel file (già alla riga :first)',
            'sku_exists' => 'riga :line: SKU «:sku» già presente, saltato',
            'name_missing' => 'riga :line: nome mancante (obbligatorio per un prodotto nuovo)',
            'price_not_numeric' => 'riga :line: prezzo non numerico («:value»)',
            'stock_not_numeric' => 'riga :line: giacenza non numerica («:value»)',
            'stock_not_integer' => 'riga :line: la giacenza deve essere un numero intero («:value»)',
            'weight_not_numeric' => 'riga :line: peso non numerico («:value»)',
            'length_not_numeric' => 'riga :line: lunghezza non numerica («:value»)',
            'width_not_numeric' => 'riga :line: larghezza non numerica («:value»)',
            'height_not_numeric' => 'riga :line: altezza non numerica («:value»)',
            'status_unknown' => 'riga :line: stato «:value» non riconosciuto (ammessi: bozza, attivo, archiviato)',
            'negative' => 'riga :line: :field non può essere negativo («:value»)',
            'image_main' => 'riga :line: immagine principale — :detail',
            'image_gallery' => 'riga :line: galleria — :ok/:total immagini importate (:failed non scaricate)',
        ],
        'image' => [
            'bad_url' => 'URL non valido (:url)',
            'blocked_host' => 'host non consentito (:url)',
            'unreachable' => 'non raggiungibile (:url)',
            'http_error' => 'download fallito (HTTP :status)',
            'empty' => 'file vuoto',
            'too_large' => 'oltre il limite di dimensione',
            'bad_type' => 'formato non supportato (:type) — ammessi JPG, PNG, WebP',
        ],
    ],

    // Modulo ExportProdotti — export prodotti in CSV/XLSX.
    'export' => [
        'nav' => [
            'group' => 'Export',
            'history' => 'Esiti export',
        ],
        'record' => [
            'label' => 'Esito export',
            'plural' => 'Esiti export',
        ],
        'action' => [
            'trigger' => 'Esporta',
            'download' => 'Scarica',
        ],
        'modal' => [
            'heading' => 'Esporta prodotti',
            'description' => 'Vengono esportati tutti i prodotti che corrispondono ai filtri attivi nella lista, senza limiti di pagina. Le varianti dei prodotti variabili sono incluse come righe separate.',
            'submit' => 'Esporta',
            'summary' => '{0} Nessun prodotto corrisponde ai filtri correnti|{1} :count prodotto corrisponde ai filtri correnti|[2,*] :count prodotti corrispondono ai filtri correnti',
        ],
        'field' => [
            'format' => 'Formato',
            'columns' => 'Colonne da includere',
        ],
        'format' => [
            'xlsx' => 'Excel (.xlsx)',
            'csv' => 'CSV (.csv)',
        ],
        'column' => [
            'sku' => 'SKU',
            'name' => 'Nome (lingua base)',
            'description' => 'Descrizione (lingua base)',
            'price' => 'Prezzo (listino predefinito)',
            'stock' => 'Giacenza',
            'weight' => 'Peso',
            'length' => 'Lunghezza',
            'width' => 'Larghezza',
            'height' => 'Altezza',
            'status' => 'Stato',
            'image_url' => 'Immagine principale (URL)',
            'gallery_urls' => 'Galleria (URL separati da |)',
        ],
        'notify' => [
            'queued' => 'Export avviato: il catalogo è grande, l\'elaborazione prosegue in coda. Puoi seguire l\'avanzamento in questa pagina.',
        ],
        'status' => [
            'pending' => 'In coda',
            'processing' => 'In corso',
            'completed' => 'Completato',
            'failed' => 'Fallito',
        ],
        'col' => [
            'when' => 'Data',
            'user' => 'Utente',
            'format' => 'Formato',
            'columns' => 'Colonne',
            'rows' => 'Righe',
        ],
        'report' => [
            'summary' => 'Riepilogo',
            'started' => 'Iniziato',
            'finished' => 'Terminato',
            'rows' => 'Righe esportate',
            'columns' => 'Colonne',
            'error' => 'Errore',
            'running_hint' => 'L\'export è ancora in corso. La pagina si aggiorna da sola; il pulsante di download compare appena il file è pronto.',
        ],
        'error' => [
            'unexpected' => 'Errore imprevisto durante l\'export.',
        ],
    ],

    // Modulo Branding — aspetto del pannello (riga singola `settings`).
    'branding' => [
        'nav' => [
            'group' => 'Impostazioni',
            'label' => 'Branding',
        ],
        'page' => [
            'title' => 'Branding e aspetto',
        ],
        'section' => [
            'identity' => 'Identità del pannello',
            'identity_hint' => 'Logo, nome e colore usati nell\'intestazione e nel tema dell\'area di amministrazione.',
        ],
        'field' => [
            'logo' => 'Logo',
            'brand_name' => 'Nome azienda / prodotto',
            'primary_color' => 'Colore primario',
        ],
        'help' => [
            'logo' => 'Mostrato nell\'intestazione al posto del testo. JPG, PNG o WebP, max 5 MB.',
            'brand_name' => 'Usato nell\'intestazione quando non è caricato alcun logo.',
            'primary_color' => 'Applicato a bottoni, link e accenti del tema. Vuoto = colore predefinito (ambra).',
        ],
        'action' => [
            'save' => 'Salva',
        ],
        'notification' => [
            'saved' => 'Impostazioni di branding salvate',
        ],
    ],

    // Modulo Dashboard — panoramica catalogo nella home del pannello.
    'dashboard' => [
        'stat' => [
            'active' => 'Prodotti attivi',
            'draft' => 'Bozze',
            'archived' => 'Archiviati',
            'no_price' => 'Senza prezzo',
            'no_price_hint' => 'Nessun prezzo sul listino «:list»',
            'stock_zero' => 'Stock a zero',
            'missing_translation' => 'Traduzione mancante',
        ],
        'col' => [
            'created' => 'Creato il',
        ],
        'missing_image' => [
            'heading' => 'Prodotti recenti senza immagine',
            'empty' => 'Tutti i prodotti recenti hanno un\'immagine principale.',
        ],
        'import_issues' => [
            'heading' => 'Righe scartate nell\'ultimo import',
            'view_report' => 'Vedi report completo',
            'subheading' => 'Import «:file» del :date — :count righe scartate.',
            'more' => '…e altre :count righe.',
            'empty' => 'Nessun import recente con righe scartate.',
        ],
        'chart' => [
            'category' => [
                'heading' => 'Prodotti per categoria',
                'dataset' => 'Prodotti',
            ],
        ],
    ],
];

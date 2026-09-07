<?php

namespace Modules\Pricing\Filament\Resources\PriceLists\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Modules\Pricing\Filament\Resources\PriceLists\PriceListResource;
use Modules\Pricing\Support\MultiplePriceListsFeature;

class ListPriceLists extends ListRecords
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Prepends the "Pro feature" notice above the table when multiple price
     * lists are off — same composition ListRecords itself uses by default
     * (see {@see ListRecords::content()}), just
     * with one extra component at the top.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            ...(MultiplePriceListsFeature::enabled() ? [] : [
                Section::make()
                    ->icon(Heroicon::OutlinedSparkles)
                    ->iconColor('warning')
                    ->heading(__('pim.pricing.pro_notice.heading'))
                    ->description(__('pim.pricing.pro_notice.description')),
            ]),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }
}

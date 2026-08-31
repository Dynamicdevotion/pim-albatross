<?php

namespace Modules\Products\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * "Choose from an existing image" — a hint action for the product / variant
 * upload fields. It does not share media: {@see Media::copy()} duplicates the
 * chosen file into this product as an independent Media row (its own file on
 * disk). Pure convenience to skip re-uploading a file that is already in the
 * library.
 *
 * Available once the product exists (edit / variant edit); on the create form
 * it asks the user to save first.
 */
final class ExistingImagePicker
{
    private const COLLECTIONS = ['main_image', 'gallery'];

    public static function hintAction(string $field, bool $multiple): Action
    {
        return Action::make('pick_existing_'.$field)
            ->label(__('pim.action.pick_existing_image'))
            ->icon('heroicon-m-photo')
            ->modalHeading(__('pim.action.pick_existing_image'))
            ->modalSubmitActionLabel(__('pim.action.pick_existing_confirm'))
            ->modalWidth('lg')
            ->schema([
                Select::make('media_id')
                    ->label(__('pim.field.pick_existing'))
                    ->required()
                    ->native(false)
                    ->allowHtml()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::results($search))
                    ->getOptionLabelUsing(fn ($value): ?string => self::label(Media::find($value)))
                    ->helperText(__('pim.helper.pick_existing')),
            ])
            ->action(function (array $data, SpatieMediaLibraryFileUpload $component) use ($field, $multiple): void {
                $target = $component->getRecord();

                if (! $target instanceof HasMedia) {
                    Notification::make()
                        ->warning()
                        ->title(__('pim.notification.pick_existing_needs_save'))
                        ->send();

                    return;
                }

                $source = Media::find($data['media_id'] ?? null);

                if ($source === null) {
                    return;
                }

                if (! $multiple) {
                    $target->clearMediaCollection($field);
                }

                $source->copy($target, $field, 'public');

                // Reload the upload field from the media relation so the new
                // image shows without a full page reload.
                $target->unsetRelation('media');
                $component->loadStateFromRelationships();

                Notification::make()
                    ->success()
                    ->title(__('pim.notification.pick_existing_done'))
                    ->send();
            });
    }

    /**
     * @return array<int, string> media id => HTML label
     */
    private static function results(string $search): array
    {
        return Media::query()
            ->where('model_type', (new Product)->getMorphClass())
            ->whereIn('collection_name', self::COLLECTIONS)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('file_name', 'like', '%'.$search.'%')
                ->orWhere('name', 'like', '%'.$search.'%')))
            ->latest('id')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Media $media): array => [$media->id => self::label($media)])
            ->all();
    }

    private static function label(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }

        $product = Product::query()->with('translations')->find($media->model_id);
        $owner = $product?->translate(Locales::baseCode())?->name ?? $product?->sku ?? '#'.$media->model_id;

        $thumb = $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl();

        return '<span style="display:inline-flex;align-items:center;gap:.5rem">'
            .'<img src="'.e($thumb).'" alt="" style="width:2rem;height:2rem;object-fit:cover;border-radius:.25rem;flex:none">'
            .'<span>'.e($owner).' · '.e($media->file_name).'</span>'
            .'</span>';
    }
}

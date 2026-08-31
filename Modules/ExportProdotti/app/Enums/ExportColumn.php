<?php

namespace Modules\ExportProdotti\Enums;

use Modules\ExportProdotti\Models\ExportRecord;
use Modules\ImportGestionali\Enums\TargetField;

/**
 * A product attribute that can be written to an export file. The value is the
 * column key stored on the {@see ExportRecord}
 * and used as the header cell; deliberately the same vocabulary as
 * {@see TargetField} so a file exported here
 * can be fed straight back into the importer.
 */
enum ExportColumn: string
{
    case Sku = 'sku';
    case Name = 'name';
    case Description = 'description';
    case Price = 'price';
    case Stock = 'stock';
    case Weight = 'weight';
    case Length = 'length';
    case Width = 'width';
    case Height = 'height';
    case Status = 'status';
    case ImageUrl = 'image_url';
    case GalleryUrls = 'gallery_urls';

    public function label(): string
    {
        return __('pim.export.column.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * value => label, for the column CheckboxList.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Keep only known column keys, in the canonical enum order — so the file
     * layout never depends on the order the checkboxes were ticked.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function ordered(array $keys): array
    {
        return array_values(array_filter(self::values(), static fn (string $v): bool => in_array($v, $keys, true)));
    }
}

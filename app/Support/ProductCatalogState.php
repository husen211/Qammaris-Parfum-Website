<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ProductCatalogState
{
    private const GENDERS = [
        'unisex' => 'Unisex',
        'pria' => 'Pria',
        'wanita' => 'Wanita',
    ];

    private const AVAILABILITIES = [
        Product::AVAILABILITY_AVAILABLE,
        Product::AVAILABILITY_SOLD_OUT,
        Product::AVAILABILITY_UNKNOWN,
    ];

    private const SORTS = [
        'latest',
        'price_low',
        'price_high',
        'popular',
    ];

    public function __construct(
        public readonly ?string $search,
        public readonly array $brandIds,
        public readonly ?int $categoryId,
        public readonly ?string $gender,
        public readonly ?int $priceMin,
        public readonly ?int $priceMax,
        public readonly ?string $availability,
        public readonly string $sort,
        public readonly int $page,
    ) {}

    public static function fromRequest(Request $request, Collection $brands, Collection $categories): self
    {
        $allowedBrandIds = $brands->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $allowedCategoryIds = $categories->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $brandIds = collect(self::arrayInput($request->query('brand', [])))
            ->map(static fn ($value): ?int => self::integer($value, 1))
            ->filter(static fn (?int $value): bool => $value !== null && in_array($value, $allowedBrandIds, true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $categoryId = self::integer($request->query('category'), 1);
        if ($categoryId !== null && ! in_array($categoryId, $allowedCategoryIds, true)) {
            $categoryId = null;
        }

        $genderInput = self::string($request->query('gender'));
        $gender = $genderInput === null ? null : (self::GENDERS[mb_strtolower($genderInput)] ?? null);

        $availabilityInput = self::string($request->query('availability'));
        $availability = $availabilityInput !== null && in_array($availabilityInput, self::AVAILABILITIES, true)
            ? $availabilityInput
            : null;

        $sortInput = self::string($request->query('sort'));
        $sort = $sortInput !== null && in_array($sortInput, self::SORTS, true) ? $sortInput : 'latest';

        $priceMin = self::integer($request->query('price_min'), 0);
        $priceMax = self::integer($request->query('price_max'), 0);
        if ($priceMin !== null && $priceMax !== null && $priceMax < $priceMin) {
            $priceMax = null;
        }

        return new self(
            search: self::search($request->query('search')),
            brandIds: $brandIds,
            categoryId: $categoryId,
            gender: $gender,
            priceMin: $priceMin,
            priceMax: $priceMax,
            availability: $availability,
            sort: $sort,
            page: self::integer($request->query('page'), 1) ?? 1,
        );
    }

    public function query(bool $includePage = true): array
    {
        $query = [];

        if ($this->search !== null) {
            $query['search'] = $this->search;
        }

        if ($this->brandIds !== []) {
            $query['brand'] = $this->brandIds;
        }

        if ($this->categoryId !== null) {
            $query['category'] = $this->categoryId;
        }

        if ($this->gender !== null) {
            $query['gender'] = $this->gender;
        }

        if ($this->priceMin !== null) {
            $query['price_min'] = $this->priceMin;
        }

        if ($this->priceMax !== null) {
            $query['price_max'] = $this->priceMax;
        }

        if ($this->availability !== null) {
            $query['availability'] = $this->availability;
        }

        if ($this->sort !== 'latest') {
            $query['sort'] = $this->sort;
        }

        if ($includePage && $this->page > 1) {
            $query['page'] = $this->page;
        }

        return $query;
    }

    public function activeFilterCount(): int
    {
        return (int) ($this->search !== null)
            + (int) ($this->brandIds !== [])
            + (int) ($this->categoryId !== null)
            + (int) ($this->gender !== null)
            + (int) ($this->priceMin !== null || $this->priceMax !== null)
            + (int) ($this->availability !== null);
    }

    private static function arrayInput(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_scalar($value) ? [$value] : [];
    }

    private static function integer(mixed $value, int $minimum): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d+$/', $value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minimum],
        ]);

        return $integer === false ? null : $integer;
    }

    private static function search(mixed $value): ?string
    {
        $value = self::string($value);
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_substr($value, 0, 100);

        return $value === '' ? null : $value;
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

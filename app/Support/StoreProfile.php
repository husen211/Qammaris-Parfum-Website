<?php

namespace App\Support;

use App\Models\StoreInfo;

/** SEO-01: the store's single NAP and its schema.org Store node, defined on /store/location and referenced elsewhere. */
class StoreProfile
{
    public static function id(): string
    {
        return route('store.location').'#store';
    }

    public static function fullAddress(): string
    {
        $profile = config('store.profile');

        return $profile['street'].', '.$profile['locality'].', '.$profile['region'].' '.$profile['postal_code'];
    }

    public static function schema(): array
    {
        $profile = config('store.profile');

        $schema = [
            '@type' => 'Store',
            '@id' => self::id(),
            'name' => $profile['name'],
            'url' => route('store.location'),
            'image' => asset('images/store/facade-1200.webp'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $profile['street'],
                'addressLocality' => $profile['locality'],
                'addressRegion' => $profile['region'],
                'postalCode' => $profile['postal_code'],
                'addressCountry' => $profile['country'],
            ],
            'hasMap' => $profile['maps_url'],
            'openingHoursSpecification' => [[
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $profile['opening_days'],
                'opens' => $profile['opens'],
                'closes' => $profile['closes'],
            ]],
            'sameAs' => $profile['same_as'],
        ];
        // ORD-07: test environments never publish the real store number, structured data included.
        if (! StoreInfo::usesTestWhatsapp()) {
            $schema = array_slice($schema, 0, 5, true) + ['telephone' => $profile['telephone']] + $schema;
        }

        return $schema;
    }
}

<div class="journal-product" data-journal-component>
    @include('products._catalog-card', ['product' => $product, 'detailUrl' => route('products.show', $product->slug), 'headingLevel' => 3, 'showPrice' => true, 'prioritizeImage' => false])
</div>

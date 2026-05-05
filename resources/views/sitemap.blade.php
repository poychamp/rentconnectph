<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($staticUrls as $u)
    <url>
        <loc>{{ $u['loc'] }}</loc>
        <lastmod>{{ $u['lastmod'] }}</lastmod>
    </url>
@endforeach
@foreach ($listings as $listing)
    <url>
        <loc>{{ route('listings.show', ['listing' => $listing->uuid]) }}</loc>
        <lastmod>{{ $listing->updated_at->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>

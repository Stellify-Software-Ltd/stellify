@php echo '<?xml version="1.0" encoding="UTF-8" ?>' @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($pages as $page)
        <url>
            <loc>{{$root}}/{{ $page->path == '/' ? '' : $page->path }}</loc>
            <lastmod>{{$date}}</lastmod>
        </url>
    @endforeach
</urlset>
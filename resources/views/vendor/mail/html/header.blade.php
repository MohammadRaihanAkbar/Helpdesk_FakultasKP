{{--
    [M6] Kepala email: nama aplikasi berwarna hijau + "UIN Siber Syekh Nurjati Cirebon".
    Sengaja TANPA gambar logo: gambar di email butuh URL publik (APP_URL Laragon = localhost tidak bisa dibuka
    dari Gmail penerima), dan banyak klien email memblokir gambar secara bawaan.
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{!! $slot !!}
</a>
<p style="margin: 4px 0 0; font-size: 12px; color: #52625a;">UIN Siber Syekh Nurjati Cirebon</p>
</td>
</tr>

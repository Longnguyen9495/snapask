<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Đổi ngôn ngữ rồi quay lại đúng trang vừa xem.
     *
     * Ghi cả cookie lẫn users.locale khi đã đăng nhập: cookie phục vụ chính máy
     * này, còn cột trong bảng theo người dùng sang máy khác và sang cả ứng dụng
     * desktop.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(SetLocale::supported($locale), 404);

        $request->user()?->forceFill(['locale' => $locale])->save();

        return redirect($this->destination($request, $locale))
            ->withCookie(Cookie::make(SetLocale::COOKIE, $locale, SetLocale::COOKIE_MINUTES));
    }

    /**
     * Trang để quay về sau khi đổi.
     *
     * Trang công khai mang ngôn ngữ trong đường dẫn, nên phải đổi cả tiền tố —
     * bằng không người dùng bấm "EN" rồi lại rơi về đúng trang tiếng Việt cũ.
     * Trang quản trị không có tiền tố, quay lại nguyên chỗ cũ là đủ.
     */
    private function destination(Request $request, string $locale): string
    {
        $previous = $request->headers->get('referer');

        if ($previous === null || ! str_starts_with($previous, $request->getSchemeAndHttpHost())) {
            return route('home', [], false) ?: '/';
        }

        $path = (string) parse_url($previous, PHP_URL_PATH);
        $query = parse_url($previous, PHP_URL_QUERY);

        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        $prefixes = array_filter(array_column(config('snapask.locales'), 'prefix'));

        // Bỏ tiền tố cũ nếu có, rồi gắn tiền tố mới. Trang quản trị không khớp
        // tiền tố nào nên đi thẳng qua bước này mà không đổi gì.
        if ($segments !== [] && in_array($segments[0], $prefixes, true)) {
            array_shift($segments);
        }

        $prefix = config("snapask.locales.{$locale}.prefix");

        if ($prefix !== '' && $this->isPublic($segments)) {
            array_unshift($segments, $prefix);
        }

        return '/'.implode('/', $segments).($query !== null ? '?'.$query : '');
    }

    /**
     * Đường dẫn này có phải trang công khai — loại có bản dịch theo tiền tố.
     *
     * Danh sách ngắn và khai thẳng ở đây: chỉ những trang này mới nhân đôi theo
     * ngôn ngữ, mọi trang còn lại dùng cookie.
     */
    private function isPublic(array $segments): bool
    {
        return $segments === [] || in_array($segments[0], ['download'], true);
    }
}

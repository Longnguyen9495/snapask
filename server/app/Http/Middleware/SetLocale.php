<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chọn ngôn ngữ cho mỗi request.
 *
 * Bốn nguồn, xét từ nguồn nào người dùng nói rõ ý nhất:
 *
 *   1. Tiền tố URL (/en)   — người dùng đang mở đúng bản đó, kể cả khi mở từ
 *                            link người khác gửi. Nói to hơn mọi thứ đã lưu.
 *   2. users.locale        — lựa chọn đã lưu của tài khoản, theo họ sang cả app
 *                            desktop.
 *   3. cookie locale       — dành cho khách chưa đăng nhập.
 *   4. Accept-Language     — chỉ với API, nơi app desktop gửi ngôn ngữ nó
 *                            đang hiển thị.
 *
 * Hết cả bốn thì dùng APP_LOCALE.
 */
class SetLocale
{
    /** Cookie sống một năm: lựa chọn ngôn ngữ không phải thứ đổi mỗi phiên. */
    public const COOKIE = 'locale';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    /** Ngôn ngữ đầu tiên hợp lệ trong bốn nguồn. */
    private function resolve(Request $request): string
    {
        /*
         * Trang công khai: địa chỉ quyết định tuyệt đối, không ai chen vào.
         *
         * `/` luôn là tiếng Việt và `/en` luôn là tiếng Anh, kể cả khi trình
         * duyệt khai Accept-Language khác. Bằng không hai địa chỉ mà tôi vừa
         * khai là bản dịch của nhau lại trả về cùng một thứ tiếng, và thẻ
         * hreflang thành lời nói dối.
         */
        if ($this->isPublic($request)) {
            return $this->fromPath($request) ?? config('app.locale');
        }

        /*
         * Accept-Language chỉ tin ở API: ở đó app desktop gửi đúng ngôn ngữ
         * người dùng đang xem. Trình duyệt thì chỉ khai ngôn ngữ của máy, mà
         * sản phẩm muốn ai chưa tự chọn cũng thấy tiếng Việt trước.
         */
        $candidates = [
            $request->user()?->locale,
            $request->cookie(self::COOKIE),
            $request->is('api/*') ? $this->fromHeader($request) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && self::supported($candidate)) {
                return $candidate;
            }
        }

        return config('app.locale');
    }

    /**
     * Request này có nhắm vào một trang công khai không.
     *
     * Chỉ những trang này mới nhân đôi theo ngôn ngữ; mọi trang còn lại dùng
     * lựa chọn đã lưu của người dùng.
     */
    private function isPublic(Request $request): bool
    {
        $segments = array_values(array_filter($request->segments(), fn ($s) => $s !== ''));
        $prefixes = array_filter(array_column(config('snapask.locales'), 'prefix'));

        if ($segments !== [] && in_array($segments[0], $prefixes, true)) {
            array_shift($segments);
        }

        return $segments === [] || $segments[0] === 'download';
    }

    /**
     * Tiền tố đầu tiên của đường dẫn, khi nó trùng một tiền tố đã khai.
     *
     * Chỉ trang công khai mới có tiền tố; trang quản trị giữ nguyên đường dẫn cũ
     * nên ở đó bước này luôn trả về null.
     */
    private function fromPath(Request $request): ?string
    {
        $first = $request->segment(1);

        if ($first === null || $first === '') {
            return null;
        }

        foreach (config('snapask.locales') as $locale => $meta) {
            if ($meta['prefix'] !== '' && $meta['prefix'] === $first) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Ngôn ngữ ưa thích của trình duyệt.
     *
     * Chỉ so phần chính của thẻ ngôn ngữ, nên "en-GB" vẫn ra "en".
     */
    private function fromHeader(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(explode('_', str_replace('-', '_', $language))[0]);

            if (self::supported($primary)) {
                return $primary;
            }
        }

        return null;
    }

    /** Ngôn ngữ này có được khai trong config không. */
    public static function supported(string $locale): bool
    {
        return array_key_exists($locale, config('snapask.locales'));
    }
}

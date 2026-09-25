'use strict';

/**
 * Xử lý danh sách lịch sử ở renderer: nhóm theo thời gian, gộp trang, đưa hội
 * thoại vừa hoạt động lên đầu.
 *
 * Máy chủ là nguồn sự thật; ở đây chỉ giữ bản sao để vẽ nhanh và cập nhật lạc
 * quan (thêm/xoá ngay rồi khớp lại với máy chủ sau).
 *
 * Nạp được cả bằng <script> (gắn vào window.SnapAskLib) lẫn require() khi test.
 */
(function (root, factory) {
  const lib = factory();

  if (typeof module === 'object' && module.exports) module.exports = lib;
  else (root.SnapAskLib = root.SnapAskLib || {}).history = lib;
})(typeof self !== 'undefined' ? self : this, () => {
  const DAY = 24 * 60 * 60 * 1000;

  const activityOf = (item) => Date.parse(item.last_activity_at || item.updated_at || item.created_at || 0) || 0;

  const startOfDay = (date) => {
    const copy = new Date(date);
    copy.setHours(0, 0, 0, 0);

    return copy.getTime();
  };

  /**
   * Nhóm theo Hôm nay / Hôm qua / 7 ngày qua / Cũ hơn, theo giờ máy người dùng.
   *
   * @returns {Array<{ key: 'today'|'yesterday'|'week'|'older', items: object[] }>}
   */
  function groupByTime(items, now = new Date()) {
    const today = startOfDay(now);
    const yesterday = today - DAY;
    const week = today - 6 * DAY;
    const groups = { today: [], yesterday: [], week: [], older: [] };

    for (const item of items) {
      const at = activityOf(item);

      if (at >= today) groups.today.push(item);
      else if (at >= yesterday) groups.yesterday.push(item);
      else if (at >= week) groups.week.push(item);
      else groups.older.push(item);
    }

    return ['today', 'yesterday', 'week', 'older']
      .filter((key) => groups[key].length > 0)
      .map((key) => ({ key, items: groups[key] }));
  }

  const byActivity = (a, b) => activityOf(b) - activityOf(a) || (b.id || 0) - (a.id || 0);

  /**
   * Gộp một trang mới vào danh sách đang có.
   *
   * Không nhân đôi khi một hội thoại vừa nổi lên đầu làm lệch ranh giới trang:
   * bản mới hơn (theo thời điểm hoạt động) thắng.
   */
  function mergePage(existing, incoming, { replace = false } = {}) {
    const map = new Map();

    for (const item of replace ? [] : existing) map.set(item.id, item);

    for (const item of incoming) {
      const current = map.get(item.id);

      if (!current || activityOf(item) >= activityOf(current)) map.set(item.id, { ...current, ...item });
    }

    return [...map.values()].sort(byActivity);
  }

  /** Thêm hoặc cập nhật một hội thoại rồi xếp lại, để nó lên đúng chỗ theo lần hoạt động. */
  function upsert(items, item) {
    return mergePage(items, [item]);
  }

  function remove(items, id) {
    return items.filter((item) => item.id !== id);
  }

  /** Mục kế tiếp để chuyển focus tới sau khi xoá mục `id`. */
  function neighbourOf(items, id) {
    const index = items.findIndex((item) => item.id === id);

    if (index === -1) return null;

    return items[index + 1] || items[index - 1] || null;
  }

  /**
   * Thời gian tương đối ngắn cho danh sách: "5 phút", "3 giờ", "Hôm qua", ngày.
   *
   * @param {(key: string, vars?: object) => string} t
   */
  function relative(dateLike, t, now = new Date(), locale = 'vi') {
    const at = Date.parse(dateLike);

    if (!at) return '';

    const diff = Math.max(0, now.getTime() - at);
    const minute = 60 * 1000;
    const hour = 60 * minute;

    if (diff < minute) return t('just now');
    if (diff < hour) return t(':count min', { count: Math.floor(diff / minute) });
    if (at >= startOfDay(now)) return t(':count h', { count: Math.floor(diff / hour) });
    if (at >= startOfDay(now) - DAY) return t('Yesterday');

    const date = new Date(at);
    const sameYear = date.getFullYear() === now.getFullYear();

    return date.toLocaleDateString(locale === 'vi' ? 'vi-VN' : 'en-GB', sameYear
      ? { day: 'numeric', month: 'short' }
      : { day: 'numeric', month: 'short', year: 'numeric' });
  }

  return { groupByTime, mergePage, upsert, remove, neighbourOf, relative, activityOf };
});

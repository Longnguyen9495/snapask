'use strict';

/**
 * Sổ theo dõi các lượt hỏi đang chạy.
 *
 * Mỗi lượt có một AbortController, đánh khoá theo cửa sổ gửi tới và mã lượt do
 * renderer đặt. Nhờ vậy workspace và ô chat nhỏ chạy song song được mà bấm
 * Dừng ở bên này không cắt nhầm câu trả lời bên kia — điều mà một biến
 * `cancelStream` dùng chung không làm được.
 *
 * Không phụ thuộc Electron để chạy được bằng `node --test`.
 */

class StreamRegistry {
  constructor() {
    /** @type {Map<string, {controller: AbortController, ownerId: number}>} */
    this.entries = new Map();
  }

  static key(ownerId, requestId) {
    return `${ownerId}:${requestId}`;
  }

  /** Mở một lượt mới; lượt trùng mã của cùng cửa sổ bị huỷ trước. */
  start(ownerId, requestId) {
    this.cancel(ownerId, requestId);

    const controller = new AbortController();
    this.entries.set(StreamRegistry.key(ownerId, requestId), { controller, ownerId });

    return controller;
  }

  /** Gỡ lượt đã xong khỏi sổ, chỉ khi đúng là controller đó (không gỡ nhầm lượt mới cùng mã). */
  finish(ownerId, requestId, controller) {
    const key = StreamRegistry.key(ownerId, requestId);

    if (this.entries.get(key)?.controller === controller) this.entries.delete(key);
  }

  cancel(ownerId, requestId) {
    const key = StreamRegistry.key(ownerId, requestId);
    const entry = this.entries.get(key);

    if (!entry) return false;

    entry.controller.abort();
    this.entries.delete(key);

    return true;
  }

  /** Huỷ mọi lượt của một cửa sổ — khi nó bị đóng hoặc bấm Dừng ở ô chat nhỏ. */
  cancelOwner(ownerId) {
    let count = 0;

    for (const [key, entry] of this.entries) {
      if (entry.ownerId === ownerId) {
        entry.controller.abort();
        this.entries.delete(key);
        count++;
      }
    }

    return count;
  }

  /** Huỷ tất cả — lúc đăng xuất hay phiên hết hạn. */
  cancelAll() {
    for (const entry of this.entries.values()) entry.controller.abort();

    const count = this.entries.size;
    this.entries.clear();

    return count;
  }

  get size() {
    return this.entries.size;
  }
}

module.exports = { StreamRegistry };

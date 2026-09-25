'use strict';

/**
 * Áp các sự kiện SSE của một lượt hỏi lên tin nhắn trả lời đang dựng.
 *
 * Hàm thuần: nhận tin nhắn cũ và một sự kiện, trả tin nhắn mới. Nhờ vậy cùng
 * một logic dùng được cho workspace và kiểm thử, và mọi trạng thái cuối —
 * xong, đã dừng, lỗi trước chữ đầu, đứt giữa chừng — đều nằm một chỗ.
 *
 * Trạng thái (`status`):
 *   pending     đã gửi, chưa có chữ nào
 *   streaming   đang nhận chữ
 *   done        máy chủ báo xong
 *   stopped     người dùng bấm Dừng; giữ phần đã nhận
 *   failed      lỗi trước khi có chữ nào — cho phép thử lại
 *   incomplete  lỗi sau khi đã có chữ — giữ nội dung, đánh dấu chưa xong
 *
 * Nạp được cả bằng <script> (gắn vào window.SnapAskLib) lẫn require() khi test.
 */
(function (root, factory) {
  const lib = factory();

  if (typeof module === 'object' && module.exports) module.exports = lib;
  else (root.SnapAskLib = root.SnapAskLib || {}).stream = lib;
})(typeof self !== 'undefined' ? self : this, () => {
  const FINAL = new Set(['done', 'stopped', 'failed', 'incomplete']);

  function initial(requestId) {
    return { requestId, role: 'assistant', content: '', tools: [], status: 'pending', error: null, conversationId: null };
  }

  /**
   * @param {ReturnType<typeof initial>} message
   * @param {{ type: string, text?: string, label?: string, conversation_id?: number, message?: string, code?: string }} event
   */
  function apply(message, event) {
    // Lượt đã chốt thì không nhận thêm gì: tránh sự kiện đến muộn sau khi Dừng.
    if (FINAL.has(message.status)) return message;

    switch (event.type) {
      // Máy chủ báo id hội thoại ngay đầu lượt (hội thoại mới vừa được tạo).
      case 'conversation':
        return { ...message, conversationId: event.conversation_id || message.conversationId };

      // Có chữ mới nghĩa là bước tra cứu trước đó đã xong.
      case 'delta':
        return {
          ...message,
          content: message.content + (event.text || ''),
          status: 'streaming',
          tools: message.tools.some((tool) => !tool.done) ? message.tools.map((tool) => ({ ...tool, done: true })) : message.tools,
        };

      case 'tool': {
        const tools = message.tools.map((tool) => ({ ...tool, done: true }));

        return { ...message, tools: [...tools, { label: event.label || '', done: false }] };
      }

      case 'done':
        return {
          ...message,
          status: 'done',
          conversationId: event.conversation_id || message.conversationId,
          tools: message.tools.map((tool) => ({ ...tool, done: true })),
        };

      case 'error':
        return {
          ...message,
          status: message.content ? 'incomplete' : 'failed',
          error: { code: event.code || 'unknown', message: event.message || '', quota: event.quota || null },
          tools: message.tools.map((tool) => ({ ...tool, done: true })),
        };

      default:
        return message;
    }
  }

  /** Người dùng bấm Dừng. */
  function stop(message) {
    if (FINAL.has(message.status)) return message;

    return { ...message, status: message.content ? 'stopped' : 'failed', error: message.content ? null : { code: 'stopped', message: '' }, tools: message.tools.map((tool) => ({ ...tool, done: true })) };
  }

  const isFinal = (message) => FINAL.has(message.status);

  return { initial, apply, stop, isFinal };
});

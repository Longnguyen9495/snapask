(() => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const platformSource = navigator.userAgentData?.platform ?? navigator.platform ?? navigator.userAgent;
  const currentPlatform = /mac/i.test(platformSource) ? 'mac' : 'windows';

  document.querySelectorAll('.js-platform-download').forEach((link) => {
    const href = currentPlatform === 'mac' ? link.dataset.mac : link.dataset.win;
    const label = currentPlatform === 'mac' ? link.dataset.macLabel : link.dataset.winLabel;

    if (href) {
      link.href = href;
    }

    if (label) {
      link.textContent = label;
    }
  });

  const platformCards = document.querySelectorAll('[data-platform]');
  platformCards.forEach((card) => {
    const recommended = card.dataset.platform === currentPlatform;
    card.classList.toggle('is-recommended', recommended);

    const label = card.querySelector('.platform__recommended');
    if (label) {
      label.hidden = !recommended;
    }
  });

  const demo = document.querySelector('[data-capture-demo]');
  const replayButtons = document.querySelectorAll('[data-replay]');
  let replayTimer;

  const showFinalDemoState = () => {
    demo?.classList.remove('is-playing');
    demo?.classList.add('is-complete');
  };

  const playDemo = () => {
    if (!demo || reducedMotion) {
      showFinalDemoState();
      return;
    }

    window.clearTimeout(replayTimer);
    demo.classList.remove('is-playing', 'is-complete');
    void demo.offsetWidth;
    demo.classList.add('is-playing');
    replayTimer = window.setTimeout(() => {
      demo.classList.remove('is-playing');
      demo.classList.add('is-complete');
    }, 4800);
  };

  if (demo) {
    if (reducedMotion || !('IntersectionObserver' in window)) {
      showFinalDemoState();
      if (!reducedMotion) {
        playDemo();
      }
    } else {
      // Diễn lại mỗi lần cuộn trở lại, không chỉ lần đầu. Chờ demo đi hẳn khỏi
      // màn hình rồi mới cho phát lại, để cuộn qua lại quanh mép không làm nó
      // giật cục giữa chừng.
      const demoObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          // Đang diễn thì để yên: cuộn qua lại quanh ngưỡng 0.35 sẽ bắn sự kiện
          // liên tục, phát lại mỗi lần thì đoạn phim không bao giờ chạy hết.
          if (entry.isIntersecting) {
            if (!demo.classList.contains('is-playing')) {
              playDemo();
            }
          } else if (entry.intersectionRatio === 0) {
            window.clearTimeout(replayTimer);
            demo.classList.remove('is-playing', 'is-complete');
          }
        });
      }, { threshold: [0, 0.35] });

      demoObserver.observe(demo);
    }
  }

  replayButtons.forEach((button) => {
    button.addEventListener('click', playDemo);
  });

  /*
   * Hiện dần khi cuộn tới, và ẩn lại khi ra khỏi tầm nhìn.
   *
   * Không `unobserve` như trước: cuộn ngược lên rồi xuống lại mà trang đứng im
   * thì người xem tưởng nó hỏng. Chỉ thu lại khi phần tử đã đi hẳn khỏi màn
   * hình — thu ngay lúc mới khuất một góc sẽ thành nhấp nháy lúc cuộn chậm.
   */
  const revealElements = document.querySelectorAll('.reveal');
  if (reducedMotion || !('IntersectionObserver' in window)) {
    revealElements.forEach((element) => element.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
        } else if (entry.intersectionRatio === 0) {
          entry.target.classList.remove('is-visible');
        }
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: [0, 0.08] });

    revealElements.forEach((element) => revealObserver.observe(element));
  }

  /*
   * Các khối con hiện lần lượt, không ùa vào cùng lúc.
   *
   * Độ trễ đặt bằng biến CSS thay vì viết sẵn trong stylesheet: số phần tử mỗi
   * nhóm do Blade sinh ra, JS đếm được còn CSS thì không.
   */
  const stagger = document.querySelectorAll('[data-stagger]');
  if (reducedMotion || !('IntersectionObserver' in window)) {
    stagger.forEach((group) => group.classList.add('is-visible'));
  } else {
    // 55ms: đủ để mắt thấy thứ tự, đủ ngắn để nhóm năm thẻ không kéo quá 0.3s.
    const step = 55;
    const staggerObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
        } else if (entry.intersectionRatio === 0) {
          entry.target.classList.remove('is-visible');
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: [0, 0.12] });

    stagger.forEach((group) => {
      [...group.children].forEach((child, index) => {
        child.style.setProperty('--stagger-delay', `${index * step}ms`);
      });

      staggerObserver.observe(group);
    });
  }

  /*
   * Khối minh hoạ tự diễn hoạt khi cuộn tới, và diễn lại mỗi lần quay lại.
   *
   * Gỡ class rồi ép trình duyệt tính lại layout (`offsetWidth`) trước khi gắn
   * lại — không có bước đó thì trình duyệt gộp hai thao tác làm một và
   * animation không chạy lần thứ hai.
   */
  const scenes = document.querySelectorAll('[data-scene]');
  if (!reducedMotion && 'IntersectionObserver' in window) {
    const sceneObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.remove('is-running');
          void entry.target.offsetWidth;
          entry.target.classList.add('is-running');
        } else if (entry.intersectionRatio === 0) {
          entry.target.classList.remove('is-running');
        }
      });
    }, { threshold: [0, 0.45] });

    scenes.forEach((scene) => sceneObserver.observe(scene));
  }

  /*
   * Bản dùng thử có trạng thái riêng và có gọi mạng, nên nằm ở public/js/demo.js.
   * File này chỉ lo hiệu ứng cuộn của cả trang.
   */

  const accordion = document.querySelector('[data-accordion]');
  accordion?.querySelectorAll('details').forEach((details) => {
    details.addEventListener('toggle', () => {
      if (!details.open) {
        return;
      }

      accordion.querySelectorAll('details[open]').forEach((openDetails) => {
        if (openDetails !== details) {
          openDetails.open = false;
        }
      });
    });
  });

  document.querySelectorAll('[data-download-action]').forEach((link) => {
    link.addEventListener('click', () => {
      link.classList.add('is-loading');
      link.setAttribute('aria-busy', 'true');

      window.setTimeout(() => {
        link.classList.remove('is-loading');
        link.removeAttribute('aria-busy');
      }, 2200);
    });
  });
})();

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

  const tryDemo = document.querySelector('[data-try-demo]');
  if (tryDemo) {
    const screen = tryDemo.querySelector('[data-demo-screen]');
    const selection = tryDemo.querySelector('[data-demo-selection]');
    const empty = tryDemo.querySelector('[data-demo-empty]');
    const form = tryDemo.querySelector('[data-demo-form]');
    const question = tryDemo.querySelector('[data-demo-question]');
    const thinking = tryDemo.querySelector('[data-demo-thinking]');
    const result = tryDemo.querySelector('[data-demo-result]');
    const answerTitle = tryDemo.querySelector('[data-demo-answer-title]');
    const answerBody = tryDemo.querySelector('[data-demo-answer-body]');
    const answerCode = tryDemo.querySelector('[data-demo-answer-code]');
    const status = tryDemo.querySelector('[data-demo-status]');
    const selectMessage = status?.textContent;
    const askMessage = form?.querySelector('label')?.textContent;
    const thinkingMessage = thinking?.querySelector('p')?.textContent;
    const answerMessage = result?.querySelector('.try-result__label')?.textContent;
    let dragStart = null;

    const normalizeQuestion = (value) => value
      .toLocaleLowerCase('vi')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd');

    const answerFor = (value) => {
      const normalized = normalizeQuestion(value);

      if (/(nghia|dich|tieng viet|la gi|what does|mean)/.test(normalized)) {
        return { title: result.dataset.meaningTitle, body: result.dataset.meaningBody, showCode: false };
      }

      if (/(tai sao|vi sao|nguyen nhan|why|cause)/.test(normalized)) {
        return { title: result.dataset.causeTitle, body: result.dataset.causeBody, showCode: false };
      }

      if (/(sua|khac phuc|giai quyet|lam sao|fix|solve|how)/.test(normalized)) {
        return { title: result.dataset.fixTitle, body: result.dataset.fixBody, showCode: true };
      }

      return { title: result.dataset.generalTitle, body: result.dataset.generalBody, showCode: true };
    };

    const setStep = (step) => {
      tryDemo.dataset.step = step;
      empty.hidden = step !== 'select';
      form.hidden = step !== 'ask';
      thinking.hidden = step !== 'thinking';
      result.hidden = step !== 'answer';
      status.textContent = { select: selectMessage, ask: askMessage, thinking: thinkingMessage, answer: answerMessage }[step] ?? '';
    };

    const selectTarget = () => {
      const rect = screen.getBoundingClientRect();
      selection.style.left = `${rect.width * 0.13}px`;
      selection.style.top = `${rect.height * 0.31}px`;
      selection.style.width = `${rect.width * 0.74}px`;
      selection.style.height = `${rect.height * 0.35}px`;
      selection.classList.add('is-active');
      setStep('ask');
      window.setTimeout(() => question.focus(), reducedMotion ? 0 : 180);
    };

    screen.addEventListener('pointerdown', (event) => {
      if (tryDemo.dataset.step !== 'select') return;
      const rect = screen.getBoundingClientRect();
      dragStart = { x: event.clientX - rect.left, y: event.clientY - rect.top };
      selection.style.left = `${dragStart.x}px`;
      selection.style.top = `${dragStart.y}px`;
      selection.style.width = '0px';
      selection.style.height = '0px';
      selection.classList.add('is-active');
      screen.setPointerCapture(event.pointerId);
    });

    screen.addEventListener('pointermove', (event) => {
      if (!dragStart) return;
      const rect = screen.getBoundingClientRect();
      const x = Math.max(0, Math.min(event.clientX - rect.left, rect.width));
      const y = Math.max(0, Math.min(event.clientY - rect.top, rect.height));
      selection.style.left = `${Math.min(dragStart.x, x)}px`;
      selection.style.top = `${Math.min(dragStart.y, y)}px`;
      selection.style.width = `${Math.abs(x - dragStart.x)}px`;
      selection.style.height = `${Math.abs(y - dragStart.y)}px`;
    });

    screen.addEventListener('pointerup', (event) => {
      if (!dragStart) return;
      const box = selection.getBoundingClientRect();
      dragStart = null;
      screen.releasePointerCapture(event.pointerId);
      if (box.width < 24 || box.height < 24) {
        selectTarget();
        return;
      }
      setStep('ask');
      window.setTimeout(() => question.focus(), reducedMotion ? 0 : 180);
    });

    screen.addEventListener('keydown', (event) => {
      if ((event.key === 'Enter' || event.key === ' ') && tryDemo.dataset.step === 'select') {
        event.preventDefault();
        selectTarget();
      }
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const value = question.value.trim();
      if (!value) {
        question.focus();
        return;
      }

      const answer = answerFor(value);
      answerTitle.textContent = answer.title;
      answerBody.textContent = answer.body;
      answerCode.hidden = !answer.showCode;
      setStep('thinking');
      window.setTimeout(() => {
        setStep('answer');
        result.focus();
      }, reducedMotion ? 250 : 1250);
    });

    tryDemo.querySelectorAll('[data-demo-reset]').forEach((button) => {
      button.addEventListener('click', () => {
        selection.classList.remove('is-active');
        selection.removeAttribute('style');
        setStep('select');
        screen.focus();
      });
    });
  }

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

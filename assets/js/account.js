(() => {
  'use strict';
  const body = document.body;
  const ar = document.documentElement.lang.toLowerCase().startsWith('ar');
  const t = (en, arabic) => ar ? arabic : en;
  const toggle = document.querySelector('[data-account-menu-toggle]');
  const closeTargets = document.querySelectorAll('[data-account-menu-close]');
  const sidebar = document.getElementById('account-sidebar');
  const sidebarBackdrop = document.querySelector('[data-account-menu-close].app-sidebar-backdrop');
  const collapseToggle = document.querySelector('[data-account-sidebar-collapse]');
  let menuPreviousFocus = null;
  let desktopCollapsed = false;
  const collapseKey = 'auraa-account-sidebar-collapsed';

  const readCollapsePreference = () => {const cookie=document.cookie.split('; ').find(x=>x.startsWith('auraa_account_sidebar_collapsed='));if(cookie)return cookie.endsWith('=1');try{const value=localStorage.getItem(collapseKey);if(value==='1'||value==='0')return value==='1';}catch(_){}return false;};
  const setDesktopCollapsed = (collapsed, persist = true) => {
    desktopCollapsed = Boolean(collapsed);
    const active = window.innerWidth > 980 && desktopCollapsed;
    body.classList.toggle('account-sidebar-collapsed', active);
    document.documentElement.classList.remove('pref-account-collapsed');
    collapseToggle?.setAttribute('aria-pressed', String(active));
    collapseToggle?.setAttribute('aria-label', active ? t('Expand account navigation','توسيع قائمة الحساب') : t('Collapse account navigation','طي قائمة الحساب'));
    collapseToggle?.setAttribute('title', active ? t('Expand navigation','توسيع القائمة') : t('Collapse navigation','طي القائمة'));
    const icon = collapseToggle?.querySelector('span');
    if (icon) icon.textContent = active ? '›' : '‹';
    if (persist) { try { localStorage.setItem(collapseKey, desktopCollapsed ? '1' : '0'); } catch (_) {} document.cookie = 'auraa_account_sidebar_collapsed=' + (desktopCollapsed ? '1' : '0') + '; path=/; max-age=31536000; samesite=lax'; }
  };

  const sidebarFocusables = () => sidebar
    ? Array.from(sidebar.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter((el) => !el.hasAttribute('hidden') && el.getClientRects().length > 0)
    : [];

  const setMenu = (open) => {
    const wasOpen = body.classList.contains('account-sidebar-open');
    body.classList.toggle('account-sidebar-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    toggle?.setAttribute('aria-label', open ? t('Close account navigation','إغلاق قائمة الحساب') : t('Open account navigation','فتح قائمة الحساب'));
    const drawerMode = window.innerWidth <= 980;
    if (sidebar) {
      sidebar.inert = drawerMode && !open;
      sidebar.setAttribute('aria-hidden', drawerMode && !open ? 'true' : 'false');
    }
    if (sidebarBackdrop) sidebarBackdrop.tabIndex = drawerMode && open ? 0 : -1;
    if (open && !wasOpen) {
      menuPreviousFocus = document.activeElement;
      window.requestAnimationFrame(() => sidebarFocusables()[0]?.focus());
    } else if (!open && wasOpen && menuPreviousFocus instanceof HTMLElement) {
      menuPreviousFocus.focus();
      menuPreviousFocus = null;
    }
  };

  toggle?.addEventListener('click', () => setMenu(!body.classList.contains('account-sidebar-open')));
  closeTargets.forEach((el) => el.addEventListener('click', () => setMenu(false)));
  sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenu(false);
    if (event.key !== 'Tab' || !body.classList.contains('account-sidebar-open') || window.innerWidth > 980) return;
    const focusables = sidebarFocusables();
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus();
    }
  });
  collapseToggle?.addEventListener('click', () => setDesktopCollapsed(!desktopCollapsed));
  window.addEventListener('resize', () => {
    if (window.innerWidth > 980) setMenu(false);
    setDesktopCollapsed(desktopCollapsed, false);
  }, { passive: true });
  setMenu(false);
  desktopCollapsed = readCollapsePreference();
  setDesktopCollapsed(desktopCollapsed, false);

  const navTooltip = document.createElement('div');
  navTooltip.className = 'account-nav-tooltip';
  navTooltip.setAttribute('role', 'tooltip');
  document.body.appendChild(navTooltip);
  const hideNavTooltip = () => navTooltip.classList.remove('is-visible');
  const showNavTooltip = (link) => {
    if (!body.classList.contains('account-sidebar-collapsed') || window.innerWidth <= 980) return;
    const label = link?.getAttribute?.('title') || link?.textContent?.trim() || '';
    if (!label) return;
    const rect = link.getBoundingClientRect();
    navTooltip.textContent = label;
    const rtl = document.documentElement.dir === 'rtl';
    const tooltipWidth = navTooltip.offsetWidth || 160;
    const preferred = rtl ? rect.left - tooltipWidth - 10 : rect.right + 10;
    navTooltip.style.left = `${Math.max(8, Math.min(window.innerWidth - tooltipWidth - 8, Math.round(preferred)))}px`;
    navTooltip.style.top = `${Math.round(rect.top + rect.height / 2)}px`;
    navTooltip.style.transform = 'translateY(-50%)';
    navTooltip.classList.add('is-visible');
  };
  document.addEventListener('pointerover', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.account-side-link') : null;
    if (link) showNavTooltip(link);
  });
  document.addEventListener('pointerout', (event) => {
    if (event.target instanceof Element && event.target.closest('.account-side-link')) hideNavTooltip();
  });
  document.addEventListener('focusin', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.account-side-link') : null;
    if (link) showNavTooltip(link);
  });
  document.addEventListener('focusout', (event) => {
    if (event.target instanceof Element && event.target.closest('.account-side-link')) hideNavTooltip();
  });
  window.addEventListener('resize', hideNavTooltip, { passive: true });

  const supportThread = document.querySelector('[data-support-thread]');
  if (supportThread) {
    const ticketId = Number(supportThread.dataset.ticketId || 0);
    const pollUrl = supportThread.dataset.pollUrl || '/support-thread';
    let lastMessageId = Number(supportThread.dataset.lastMessageId || 0);
    let supportPollBusy = false;
    let pollDelay = 15000;
    let timer = 0;
    const dateFormatter = new Intl.DateTimeFormat(undefined, {
      year: 'numeric', month: 'short', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit', timeZoneName: 'short'
    });

    const messageNode = (message) => {
      const article = document.createElement('article');
      article.className = `support-message ${message.sender_is_admin ? 'is-admin' : 'is-user'}`;
      const head = document.createElement('div');
      head.className = 'support-message-head';
      const strong = document.createElement('strong');
      strong.textContent = message.sender_is_admin ? t('SUPPORT','الدعم') : (message.username || t('USER','مستخدم'));
      const time = document.createElement('time');
      const epoch = Number(message.created_at_epoch || 0);
      time.textContent = epoch > 0 ? dateFormatter.format(new Date(epoch * 1000)) : t('Unknown','غير معروف');
      head.append(strong, time);
      article.appendChild(head);
      if (message.message) {
        const p = document.createElement('p');
        p.textContent = message.message;
        article.appendChild(p);
      }
      if (message.attachment_url && message.attachment_name) {
        const link = document.createElement('a');
        link.className = 'app-button tool-button secondary';
        link.href = message.attachment_url;
        link.textContent = `${t('DOWNLOAD','تنزيل')} ${message.attachment_name} ↓`;
        article.appendChild(link);
      }
      return article;
    };

    const schedule = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(refreshSupportThread, pollDelay);
    };

    const refreshSupportThread = async () => {
      if (supportPollBusy || document.hidden || ticketId <= 0) { schedule(); return; }
      supportPollBusy = true;
      try {
        const separator = pollUrl.includes('?') ? '&' : '?';
        const endpoint = `${pollUrl}${separator}id=${encodeURIComponent(ticketId)}&after=${encodeURIComponent(lastMessageId)}`;
        const response = await fetch(endpoint, {
          method: 'GET',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) throw new Error(`Support poll failed (${response.status})`);
        const payload = await response.json();
        if (!payload?.ok) throw new Error('Support poll response invalid');
        const messages = Array.isArray(payload.messages) ? payload.messages : [];
        if (messages.length) {
          const fragment = document.createDocumentFragment();
          messages.forEach((message) => fragment.appendChild(messageNode(message)));
          supportThread.appendChild(fragment);
          supportThread.setAttribute('aria-live', 'polite');
          lastMessageId = Number(payload.last_message_id || lastMessageId);
          supportThread.dataset.lastMessageId = String(lastMessageId);
        }
        pollDelay = 15000;
      } catch (_) {
        pollDelay = Math.min(60000, Math.max(20000, pollDelay * 2));
      } finally {
        supportPollBusy = false;
        schedule();
      }
    };
    schedule();
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        window.clearTimeout(timer);
        refreshSupportThread();
      }
    });
  }
})();


/* Customer address-book editor: external script to comply with storefront CSP. */
(()=>{
  'use strict';
  const form=document.querySelector('[data-address-form]');
  if(!(form instanceof HTMLFormElement))return;
  const heading=document.querySelector('[data-address-heading]');
  const reset=()=>{form.reset();form.elements.namedItem('id').value='0';if(heading)heading.textContent=t('Add delivery address','إضافة عنوان توصيل');};
  document.querySelectorAll('[data-address-new]').forEach(b=>b.addEventListener('click',()=>{
    reset();document.getElementById('address-editor')?.scrollIntoView({behavior:'smooth',block:'start'});
  }));
  document.querySelectorAll('[data-address-edit]').forEach(b=>b.addEventListener('click',()=>{
    reset();let row;try{row=JSON.parse(b.dataset.addressJson||'{}')}catch(_){return}
    for(const [key,val] of Object.entries(row)){
      const field=form.elements.namedItem(key);
      if(!field)continue;
      if(field.type==='checkbox')field.checked=!!Number(val);
      else field.value=val??'';
    }
    if(heading)heading.textContent=t('Edit delivery address','تعديل عنوان التوصيل');
    document.getElementById('address-editor')?.scrollIntoView({behavior:'smooth',block:'start'});
  }));
  document.querySelectorAll('.account-address-actions form').forEach(f=>{
    if(f.querySelector('input[name="action"]')?.value==='delete')f.addEventListener('submit',e=>{
      if(!window.confirm(t('Remove this saved address?','هل تريد حذف هذا العنوان المحفوظ؟')))e.preventDefault();
    });
  });
})();

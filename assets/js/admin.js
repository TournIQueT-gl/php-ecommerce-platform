(() => {
  'use strict';

  const body = document.body;
  const adminText = (english, arabic) => document.documentElement.lang === 'ar' ? arabic : english;
  const toggle = document.querySelector('[data-admin-menu-toggle]');
  const closeTargets = document.querySelectorAll('[data-admin-menu-close]');
  const sidebar = document.getElementById('admin-sidebar');
  const sidebarBackdrop = document.querySelector('[data-admin-menu-close].app-sidebar-backdrop');
  const collapseToggle = document.querySelector('[data-admin-sidebar-collapse]');
  let menuPreviousFocus = null;
  let desktopCollapsed = false;
  const collapseKey = 'auraa-admin-sidebar-collapsed';

  const readCollapsePreference = () => {const cookie=document.cookie.split('; ').find(x=>x.startsWith('auraa_admin_sidebar_collapsed='));if(cookie)return cookie.endsWith('=1');try{const value=localStorage.getItem(collapseKey);if(value==='1'||value==='0')return value==='1';}catch(_){}return false;};
  const setDesktopCollapsed = (collapsed, persist = true) => {
    desktopCollapsed = Boolean(collapsed);
    const active = window.innerWidth > 980 && desktopCollapsed;
    body.classList.toggle('admin-sidebar-collapsed', active);
    document.documentElement.classList.remove('pref-admin-collapsed');
    collapseToggle?.setAttribute('aria-pressed', String(active));
    collapseToggle?.setAttribute('aria-label', active ? adminText('Expand admin navigation', 'توسيع قائمة الإدارة') : adminText('Collapse admin navigation', 'طي قائمة الإدارة'));
    collapseToggle?.setAttribute('title', active ? adminText('Expand navigation', 'توسيع القائمة') : adminText('Collapse navigation', 'طي القائمة'));
    const icon = collapseToggle?.querySelector('span');
    if (icon) icon.textContent = active ? '›' : '‹';
    if (persist) { try { localStorage.setItem(collapseKey, desktopCollapsed ? '1' : '0'); } catch (_) {} document.cookie = 'auraa_admin_sidebar_collapsed=' + (desktopCollapsed ? '1' : '0') + '; path=/; max-age=31536000; samesite=lax'; }
  };
  const sidebarFocusables = () => sidebar
    ? Array.from(sidebar.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter((el) => !el.hasAttribute('hidden') && el.getClientRects().length > 0)
    : [];

  const setMenu = (open) => {
    const wasOpen = body.classList.contains('admin-sidebar-open');
    body.classList.toggle('admin-sidebar-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    toggle?.setAttribute('aria-label', open ? adminText('Close admin navigation', 'إغلاق قائمة الإدارة') : adminText('Open admin navigation', 'فتح قائمة الإدارة'));
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

  toggle?.addEventListener('click', () => setMenu(!body.classList.contains('admin-sidebar-open')));
  closeTargets.forEach(el => el.addEventListener('click', () => setMenu(false)));
  sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setMenu(false)));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') setMenu(false);
    if (event.key !== 'Tab' || !body.classList.contains('admin-sidebar-open') || window.innerWidth > 980) return;
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
  document.querySelector('[data-admin-sidebar-more]')?.addEventListener('click', () => setDesktopCollapsed(false));
  window.addEventListener('resize', () => {
    if (window.innerWidth > 980) setMenu(false);
    setDesktopCollapsed(desktopCollapsed, false);
  }, { passive: true });
  setMenu(false);
  desktopCollapsed = readCollapsePreference();
  setDesktopCollapsed(desktopCollapsed, false);


  const initNavGroups = () => {
    document.querySelectorAll('[data-admin-nav-group]').forEach((group) => {
      if (group.dataset.groupBound === '1') return;
      group.dataset.groupBound = '1';
      const button = group.querySelector('[data-admin-nav-group-toggle]');
      const groupKey = group.dataset.groupKey || 'group';
      // v4.2.7 deliberately versions this preference so old "everything open"
      // localStorage state cannot recreate the oversized sidebar.
      const key = `auraa-admin-nav-v427:${groupKey}`;
      const forceOpen = group.classList.contains('is-current-group') || groupKey === 'store';
      let collapsed = false;
      try { collapsed = !forceOpen && localStorage.getItem(key) !== 'open'; } catch (_) { collapsed = !forceOpen; }
      const apply = (next) => {
        collapsed = forceOpen ? false : next;
        group.classList.toggle('is-collapsed', collapsed);
        button?.setAttribute('aria-expanded', String(!collapsed));
        const icon = button?.querySelector('i');
        if (icon) icon.textContent = collapsed ? '+' : '−';
      };
      apply(collapsed);
      button?.addEventListener('click', () => {
        if (forceOpen) return;
        apply(!collapsed);
        try { localStorage.setItem(key, collapsed ? 'closed' : 'open'); } catch (_) {}
      });
    });
  };

  initNavGroups();

  let browserTimezone = 'UTC';
  try {
    browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch (_) {}

  const closeAdminRowMenus = (except = null) => {
    document.querySelectorAll('details.admin-row-menu[open]').forEach((details) => {
      if (details !== except) details.removeAttribute('open');
    });
  };

  const positionAdminRowMenu = (details) => {
    if (!(details instanceof HTMLDetailsElement)) return;
    const panel = details.querySelector(':scope > div');
    const summary = details.querySelector(':scope > summary');
    if (!(panel instanceof HTMLElement) || !(summary instanceof HTMLElement)) return;
    if (window.innerWidth <= 760) {
      panel.style.removeProperty('left'); panel.style.removeProperty('top'); panel.style.removeProperty('width');
      return;
    }
    const rect = summary.getBoundingClientRect();
    const width = Math.max(188, Math.min(260, panel.scrollWidth || 188));
    const workspace = document.querySelector('.admin-workspace');
    const workspaceLeft = workspace instanceof HTMLElement ? workspace.getBoundingClientRect().left : 0;
    const minLeft = Math.max(12, Math.round(workspaceLeft + 12));
    const left = Math.max(minLeft, Math.min(window.innerWidth - width - 12, rect.right - width));
    const availableBelow = window.innerHeight - rect.bottom - 12;
    const estimatedHeight = Math.min(320, panel.scrollHeight || 120);
    const top = availableBelow >= estimatedHeight ? rect.bottom + 7 : Math.max(12, rect.top - estimatedHeight - 7);
    panel.style.left = `${Math.round(left)}px`;
    panel.style.top = `${Math.round(top)}px`;
    panel.style.width = `${Math.round(width)}px`;
  };

  const enhanceAdminContent = (root = document) => {
    root.querySelectorAll('[data-browser-timezone]').forEach(input => {
      input.value = browserTimezone;
    });

    root.querySelectorAll('input[type="datetime-local"][data-utc-datetime]').forEach((input) => {
      if (!(input instanceof HTMLInputElement) || input.dataset.utcDatetimeBound === '1') return;
      input.dataset.utcDatetimeBound = '1';
      const raw=(input.dataset.utcDatetime||'').trim();if(!raw)return;
      const iso=raw.includes('T')?raw:`${raw.replace(' ','T')}Z`;const date=new Date(iso.endsWith('Z')||/[+-]\d{2}:?\d{2}$/.test(iso)?iso:`${iso}Z`);if(Number.isNaN(date.getTime()))return;
      const pad=(n)=>String(n).padStart(2,'0');
      input.value=`${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    });

    root.querySelectorAll('img[data-admin-image]').forEach((img) => {
      if (!(img instanceof HTMLImageElement) || img.dataset.adminImageBound === '1') return;
      img.dataset.adminImageBound = '1';
      const fallback = img.nextElementSibling;
      const fail = () => {
        img.hidden = true;
        if (fallback instanceof HTMLElement && fallback.matches('[data-admin-image-fallback]')) fallback.hidden = false;
      };
      img.addEventListener('error', fail, { once: true });
      if (img.complete && img.naturalWidth === 0) fail();
    });

    root.querySelectorAll('details.admin-row-menu').forEach((details) => {
      if (!(details instanceof HTMLDetailsElement) || details.dataset.rowMenuBound === '1') return;
      details.dataset.rowMenuBound = '1';
      details.addEventListener('toggle', () => {
        if (!details.open) return;
        closeAdminRowMenus(details);
        window.requestAnimationFrame(() => positionAdminRowMenu(details));
      });
    });

    // On narrow screens, turn wide admin tables into readable record cards.
    root.querySelectorAll('table.admin-table').forEach((table) => {
      const headers = Array.from(table.querySelectorAll('thead th')).map((th) => (th.textContent || '').trim());
      if (!headers.length) return;

      table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = Array.from(row.children).filter((cell) => cell instanceof HTMLTableCellElement);
        if (cells.length === 1 && Number(cells[0].colSpan || 1) > 1) {
          row.classList.add('admin-mobile-empty-row');
          return;
        }
        cells.forEach((cell, index) => {
          if (!cell.dataset.label && headers[index]) cell.dataset.label = headers[index];
        });
      });
      table.classList.add('admin-table-stack-ready');
      table.closest('.admin-table-wrap')?.classList.add('admin-table-wrap-stack-ready');
    });
  };

  enhanceAdminContent();
  document.addEventListener('pointerdown', (event) => {
    const target = event.target;
    if (!(target instanceof Node) || target.closest?.('details.admin-row-menu')) return;
    closeAdminRowMenus();
  });
  window.addEventListener('resize', () => closeAdminRowMenus(), { passive: true });
  window.addEventListener('scroll', () => closeAdminRowMenus(), { passive: true, capture: true });
  if (location.hash) {
    const hashTarget = document.getElementById(location.hash.slice(1));
    if (hashTarget instanceof HTMLDetailsElement) hashTarget.open = true;
  }

  let requestSequence = 0;
  let activeController = null;

  const formUrl = (form) => {
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    url.search = '';
    const data = new FormData(form);
    data.forEach((value, key) => {
      if (typeof value !== 'string') return;
      if (value !== '') url.searchParams.append(key, value);
    });
    return url;
  };

  const loadAdminUrl = async (url, pushState = true) => {
    const currentMain = document.querySelector('.admin-main');
    if (!currentMain) {
      window.location.assign(url);
      return;
    }

    const sequence = ++requestSequence;
    activeController?.abort();
    activeController = new AbortController();
    currentMain.setAttribute('aria-busy', 'true');
    currentMain.classList.add('admin-main-loading');

    try {
      const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: activeController.signal
      });
      if (!response.ok) throw new Error(`Search request failed (${response.status})`);
      const html = await response.text();
      if (sequence !== requestSequence) return;

      const doc = new DOMParser().parseFromString(html, 'text/html');
      const nextMain = doc.querySelector('.admin-main');
      if (!nextMain) throw new Error('Search response is missing the admin content region.');

      currentMain.innerHTML = nextMain.innerHTML;
      currentMain.removeAttribute('aria-busy');
      currentMain.classList.remove('admin-main-loading');
      enhanceAdminContent(currentMain);
      if (window.AuraaUI?.enhance) window.AuraaUI.enhance(currentMain);
      document.dispatchEvent(new CustomEvent('auraa:content-updated', { detail: { root: currentMain } }));

      if (doc.title) document.title = doc.title;
      if (pushState) history.pushState({ adminSearch: true }, '', url);
    } catch (error) {
      if (error?.name === 'AbortError') return;
      window.location.assign(url);
    }
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form.admin-search') || form.method.toLowerCase() !== 'get') return;
    event.preventDefault();
    loadAdminUrl(formUrl(form).toString(), true);
  });

  // Filter dropdowns submit immediately without a full page refresh.
  document.addEventListener('change', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLSelectElement)) return;
    const form = field.closest('form.admin-search');
    if (form) form.requestSubmit();
  });

  // Keep typing responsive; search after a short pause rather than on every key.
  let searchTimer = 0;
  document.addEventListener('input', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement) || field.name !== 'q') return;
    const form = field.closest('form.admin-search');
    if (!form) return;
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => form.requestSubmit(), 320);
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.admin-pagination a, form.admin-search + .clear-search, .admin-search .clear-search');
    if (!(link instanceof HTMLAnchorElement)) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
    event.preventDefault();
    loadAdminUrl(link.href, true);
  });

  // Visitor intelligence remote lookup is never automatic on page load. The
  // adaptive scheduler handles pending IPs (CLI when available, otherwise the
  // safe web fallback). This button remains an explicit immediate Admin batch.
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-intelligence-resolve-button]');
    if (!(button instanceof HTMLButtonElement)) return;
    event.preventDefault();
    if (button.disabled) return;
    const endpoint = button.dataset.endpoint || '';
    const csrf = button.dataset.csrf || '';
    const panel = button.closest('.analytics-intelligence-status');
    const status = panel?.querySelector('[data-intelligence-resolver-status]');
    if (!endpoint || !csrf) return;

    const original = button.textContent || 'Resolve pending now';
    button.disabled = true;
    button.textContent = 'Resolving…';
    if (status) status.textContent = 'Manual batch running…';
    try {
      const body = new URLSearchParams({ csrf_token: csrf });
      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
      });
      const data = await response.json();
      if (!response.ok || !data?.ok) throw new Error(data?.error || `Resolver failed (${response.status})`);
      const unresolved = Number(data.stats?.pending || 0) + Number(data.stats?.failed || 0) + Number(data.stats?.unqueued || 0);
      if (status) status.textContent = Number(data.processed || 0) > 0
        ? `${Number(data.resolved || 0)} resolved · ${unresolved} remaining`
        : 'Up to date';
      if (unresolved <= 0) {
        button.textContent = 'Up to date';
        button.disabled = true;
      } else {
        button.textContent = 'Resolve next batch';
        button.disabled = false;
      }
    } catch (error) {
      if (status) status.textContent = 'Manual resolver unavailable';
      button.textContent = original;
      button.disabled = false;
    }
  });

  window.addEventListener('popstate', () => {
    // Homepage Builder owns section-level navigation and editor swapping.
    if(document.querySelector('.homepage-builder-shell'))return;
    loadAdminUrl(window.location.href, false);
  });
})();

/* Homepage builder interactions */
(() => {
  'use strict';
  let draggedCard = null;
  let dragList = null;
  let dragOriginalOrder = [];
  // One request at a time: rapid drag or move actions must not race on the server.
  let builderOrderBusy = false;
  const setBuilderOrderBusy = (busy) => {
    builderOrderBusy = busy;
    const shell = document.querySelector('.homepage-builder-shell');
    if (shell instanceof HTMLElement) {
      shell.dataset.orderSaving = busy ? '1' : '0';
      shell.setAttribute('aria-busy', busy ? 'true' : 'false');
      shell.querySelectorAll('.homepage-order-button button, .homepage-drag-handle').forEach((control) => {
        if (control instanceof HTMLButtonElement) control.disabled = busy;
        if (control instanceof HTMLElement) control.setAttribute('aria-disabled', busy ? 'true' : 'false');
      });
    }
  };

  const restoreOrder = (list, selector, ids) => {
    if (!(list instanceof HTMLElement) || !Array.isArray(ids)) return;
    const map = new Map(Array.from(list.querySelectorAll(selector)).map((el) => [el.dataset.id || '', el]));
    ids.forEach((id) => { const el = map.get(String(id)); if (el) list.append(el); });
  };

  const serializeOrder = (list, selector) => Array.from(list.querySelectorAll(selector))
    .map((el) => el.dataset.id || '')
    .filter(Boolean)
    .join(',');

  const refreshBuilderPreview = () => {
    const frame = document.querySelector('[data-builder-preview-frame]');
    if (!(frame instanceof HTMLIFrameElement)) return;
    try { const u = new URL(frame.src, location.href); const hash=u.hash; u.hash=''; u.searchParams.set('_preview_refresh', String(Date.now())); u.hash=hash; frame.src=u.toString(); } catch (_) { frame.src=frame.src; }
  };

  const builderOrderStatus = (message, isError = false) => {
    let status = document.querySelector('[data-builder-order-status]');
    if (!(status instanceof HTMLElement)) {
      status = document.createElement('span'); status.dataset.builderOrderStatus='1'; status.className='homepage-builder-order-status'; status.setAttribute('role','status');status.setAttribute('aria-live','polite');
      document.querySelector('.homepage-builder-toolbar-actions')?.prepend(status);
    }
    if (!(status instanceof HTMLElement)) return;
    status.textContent = message; status.classList.toggle('is-error', isError); status.hidden=false;
    window.clearTimeout(Number(status.dataset.timer||0));
    status.dataset.timer=String(window.setTimeout(()=>{status.hidden=true;},2400));
  };

  const saveBuilderOrder = async (form) => {
    if (!(form instanceof HTMLFormElement)) return false;
    const data = new FormData(form); data.set('_ajax','builder-order');
    try {
      const physical = new URL(form.getAttribute('action') || 'homepage.php', location.href);
      const submit = (url) => fetch(url, {method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'PerfumeStore-Builder'}});
      let response = await submit(physical.href);
      // Do not read form.action here: input[name=action] shadows the DOM action property.
      // Reorder is idempotent; hosts can differ on which physical/clean PHP route accepts POST.
      if(response.status===404 && physical.pathname.endsWith('.php')){
        const clean=new URL(physical.href);clean.pathname=clean.pathname.slice(0,-4);
        console.warn('[Builder reorder] Physical endpoint returned 404:',physical.pathname,'; retrying',clean.pathname);
        response=await submit(clean.href);
      }
      const raw = await response.text(); let payload=null; try{payload=JSON.parse(raw);}catch(_){}
      if (!response.ok || !payload?.ok){
        console.error('[Builder reorder] POST failed',response.status,response.url,(raw||'').slice(0,240));
        throw new Error(payload?.message || `Could not save order (HTTP ${response.status} at ${new URL(response.url).pathname}). Check Network → Response.`);
      }
      builderOrderStatus(payload.message || 'Order saved.'); refreshBuilderPreview(); return true;
    } catch (error) { builderOrderStatus(error instanceof Error ? error.message : 'Could not save order.', true); return false; }
  };

  document.addEventListener('click', (event) => {
    const mediaSwitch = event.target.closest('[data-media-target-switch]');
    if (mediaSwitch) {
      const library = mediaSwitch.closest('.homepage-media-library');
      if (!library) return;
      const picker = library.querySelector('[data-media-picker]');
      if (!picker) return;
      picker.dataset.mediaTarget = mediaSwitch.dataset.mediaTargetSwitch || 'media_image_path';
      library.querySelectorAll('[data-media-target-switch]').forEach((btn) => btn.classList.toggle('is-active', btn === mediaSwitch));
      return;
    }

    const mediaButton = event.target.closest('[data-media-path]');
    if (mediaButton) {
      const picker = mediaButton.closest('[data-media-picker]');
      const library = mediaButton.closest('.homepage-media-library');
      if (!picker || !library) return;
      const targetName = picker.dataset.mediaTarget || 'media_image_path';
      library.dataset.pendingMediaPath = mediaButton.dataset.mediaPath || '';
      library.dataset.pendingMediaUrl = mediaButton.dataset.mediaUrl || '';
      library.dataset.pendingMediaTarget = targetName;
      picker.querySelectorAll('[data-media-path]').forEach((btn) => btn.classList.toggle('is-selected', btn === mediaButton));
      const useButton = library.querySelector('[data-media-library-use]');
      if (useButton instanceof HTMLButtonElement) useButton.disabled = !library.dataset.pendingMediaPath;
      const status = library.querySelector('[data-media-selection]');
      if (status) status.textContent = `${targetName === 'media_mobile_image_path' ? 'Mobile' : 'Main'} image selected. Choose “Use selected image” to apply it.`;
      return;
    }
  });

  document.addEventListener('input', (event) => {
    const search = event.target.closest('[data-picker-search]');
    if (!(search instanceof HTMLInputElement)) return;
    const select = document.getElementById(search.dataset.pickerSearch || '');
    if (!(select instanceof HTMLSelectElement)) return;
    const q = search.value.trim().toLowerCase();
    Array.from(select.options).forEach((option) => {
      const hay = (option.dataset.search || option.textContent || '').toLowerCase();
      option.hidden = q !== '' && !hay.includes(q);
    });
  });

  document.addEventListener('dragstart', (event) => {
    const handle = event.target.closest('.homepage-drag-handle');
    if (!handle) return;
    if (builderOrderBusy) { event.preventDefault(); return; }
    const card = handle.closest('[data-homepage-section-card],[data-homepage-item-card]');
    if (!card) return;
    draggedCard = card;
    dragList = card.parentElement;
    const sectionMode = card.matches('[data-homepage-section-card]');
    dragOriginalOrder = dragList ? Array.from(dragList.querySelectorAll(sectionMode ? '[data-homepage-section-card]' : '[data-homepage-item-card]')).map((el) => el.dataset.id || '').filter(Boolean) : [];
    card.classList.add('is-dragging');
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', card.dataset.id || '');
    }
  });

  document.addEventListener('dragover', (event) => {
    if (!draggedCard || !dragList) return;
    const target = event.target.closest('[data-homepage-section-card],[data-homepage-item-card]');
    if (!target || target === draggedCard || target.parentElement !== dragList) return;
    event.preventDefault();
    const rect = target.getBoundingClientRect();
    const before = event.clientY < rect.top + rect.height / 2;
    dragList.insertBefore(draggedCard, before ? target : target.nextSibling);
  });

  // Dropping on whitespace at the end of the list must still be accepted.
  document.addEventListener('drop', (event) => {
    if (draggedCard && dragList && dragList.contains(event.target)) event.preventDefault();
  });

  document.addEventListener('dragend', async () => {
    if (!draggedCard || !dragList) return;
    const card=draggedCard; const list=dragList;
    card.classList.remove('is-dragging'); draggedCard=null; dragList=null;
    if(builderOrderBusy) { restoreOrder(list,card.matches('[data-homepage-section-card]')?'[data-homepage-section-card]':'[data-homepage-item-card]',dragOriginalOrder);dragOriginalOrder=[];return; }
    const sectionMode=card.matches('[data-homepage-section-card]');
    const form=sectionMode?document.querySelector('[data-section-reorder-form]'):(list.querySelector('[data-item-reorder-form]')||document.querySelector('[data-item-reorder-form]'));
    const input=form?.querySelector('[data-reorder-order]');
    if (form instanceof HTMLFormElement && input instanceof HTMLInputElement) {
      const selector=sectionMode?'[data-homepage-section-card]':'[data-homepage-item-card]';
      input.value=serializeOrder(list,selector);
      if(input.value===dragOriginalOrder.join(',')){dragOriginalOrder=[];return;}
      const previousOrder=[...dragOriginalOrder];
      setBuilderOrderBusy(true);
      builderOrderStatus('Saving order…');
      try {
        const ok=await saveBuilderOrder(form);
        if(!ok)restoreOrder(list,selector,previousOrder);
      } finally { dragOriginalOrder=[];setBuilderOrderBusy(false); }
    }
  });

  // AJAX create for homepage Brands and Notes. Only re-render the content panel,
  // leaving the section settings, list position and preview in place.
  document.addEventListener('submit', async (event) => {
    const form=event.target instanceof Element ? event.target.closest('form[data-homepage-ajax-add]') : null;
    if(!(form instanceof HTMLFormElement))return;
    event.preventDefault();
    if(form.dataset.saving==='1')return;
    const button=form.querySelector('button[type="submit"]');
    form.dataset.saving='1';if(button instanceof HTMLButtonElement){button.disabled=true;button.textContent='Adding…';}
    const data=new FormData(form);data.set('_ajax','builder-content');
    try{
      const endpoint=new URL(form.getAttribute('action')||'homepage.php',location.href);
      const response=await fetch(endpoint.href,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'PerfumeStore-Builder'},body:data});
      const result=await response.json().catch(()=>null);
      if(!response.ok||!result?.ok)throw new Error(result?.message||`Could not add content (HTTP ${response.status}).`);
      const refresh=new URL(result.refresh_url||location.href,location.href);
      const updated=await fetch(refresh.href,{credentials:'same-origin',cache:'no-store'});
      if(!updated.ok)throw new Error('Saved, but the updated content could not be loaded. Open this section again.');
      const next=new DOMParser().parseFromString(await updated.text(),'text/html').querySelector('.homepage-builder-content');
      const current=document.querySelector('.homepage-builder-content');
      if(!(next instanceof HTMLElement)||!(current instanceof HTMLElement))throw new Error('Saved. Refresh the builder to see the new content.');
      current.replaceWith(next);
      document.dispatchEvent(new CustomEvent('auraa:content-updated',{detail:{root:next}}));
      if(window.AuraaUI?.enhance)window.AuraaUI.enhance(next);
      builderOrderStatus(result.message||'Content added.');refreshBuilderPreview();
    }catch(error){builderOrderStatus(error instanceof Error?error.message:'Could not add content.',true);}
    finally{form.dataset.saving='';if(form.isConnected&&button instanceof HTMLButtonElement){button.disabled=false;button.textContent='Add content';}}
  });

  document.addEventListener('submit', async (event) => {
    const form=event.target.closest('form.homepage-order-button');
    if (!(form instanceof HTMLFormElement)) return;
    event.preventDefault();
    if(builderOrderBusy)return;
    const submitter=event.submitter;
    const action=form.querySelector('input[name="action"]')?.value||'';
    const direction=form.querySelector('input[name="direction"]')?.value||'';
    const id=form.querySelector('input[name="id"]')?.value||'';
    const selector=action==='move_section'?'[data-homepage-section-card]':'[data-homepage-item-card]';
    const card=document.querySelector(`${selector}[data-id="${CSS.escape(id)}"]`);
    if (!(card instanceof HTMLElement)) { builderOrderStatus('Refresh the builder and try again.',true); return; }
    const sibling=direction==='up'?card.previousElementSibling:card.nextElementSibling;
    if (!(sibling instanceof HTMLElement) || !sibling.matches(selector)) { builderOrderStatus('Already at the edge.'); return; }
    const parent=card.parentElement; if(!parent)return;
    const originalOrder=Array.from(parent.querySelectorAll(selector)).map((el)=>el.dataset.id||'').filter(Boolean);
    if(direction==='up')parent.insertBefore(card,sibling);else parent.insertBefore(sibling,card);
    setBuilderOrderBusy(true);
    builderOrderStatus('Saving order…');
    try {
      const ok=await saveBuilderOrder(form);
      if(!ok)restoreOrder(parent,selector,originalOrder);
    } finally {
      setBuilderOrderBusy(false);
      if(submitter instanceof HTMLButtonElement||submitter instanceof HTMLInputElement){submitter.classList.remove('is-loading');submitter.removeAttribute('aria-busy');}
    }
  });
})();

/* Admin ergonomics: searchable native selects + settings tabs. */
(() => {
  'use strict';

  const initSettingsTabs = (root = document) => {
    root.querySelectorAll('[data-settings-tabs]').forEach((tabs) => {
      if (tabs.dataset.bound === '1') return;
      tabs.dataset.bound = '1';
      const buttons = Array.from(tabs.querySelectorAll('[data-settings-tab]'));
      const form = tabs.closest('[data-settings-shell]') || document;
      const panels = Array.from(form.querySelectorAll('[data-settings-panel]'));
      if (!buttons.length || !panels.length) return;
      const activeInput = form.querySelector('[data-settings-active-tab]');
      const activate = (name, updateHash = false) => {
        if (activeInput instanceof HTMLInputElement) activeInput.value = name;
        buttons.forEach((button) => {
          const active = button.dataset.settingsTab === name;
          button.classList.toggle('is-active', active);
          button.setAttribute('aria-selected', String(active));
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.settingsPanel !== name; });
        if (updateHash && history.replaceState) history.replaceState(null, '', `#${encodeURIComponent(name)}`);
      };
      const hash = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
      const requested = new URL(window.location.href).searchParams.get('tab') || hash;
      const initial = buttons.some((button) => button.dataset.settingsTab === requested) ? requested : (buttons[0]?.dataset.settingsTab || 'general');
      activate(initial, false);
      buttons.forEach((button) => button.addEventListener('click', () => activate(button.dataset.settingsTab || 'general', true)));
    });
  };

  document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-select-filter]');
    if (!(input instanceof HTMLInputElement)) return;
    const select = document.getElementById(input.dataset.selectFilter || '');
    if (!(select instanceof HTMLSelectElement)) return;
    const query = input.value.trim().toLowerCase();
    Array.from(select.options).forEach((option, index) => {
      if (index === 0) { option.hidden = false; return; }
      const hay = (option.dataset.search || option.textContent || '').toLowerCase();
      option.hidden = query !== '' && !hay.includes(query);
    });
    const firstVisible = Array.from(select.options).find((option, index) => index > 0 && !option.hidden);
    if (query !== '' && firstVisible && (!select.value || select.selectedOptions[0]?.hidden)) select.value = firstVisible.value;
  });

  initSettingsTabs();
  document.addEventListener('auraa:content-updated', (event) => initSettingsTabs(event.detail?.root || document));
})();

// Searchable checkbox groups for large option sets.
document.querySelectorAll('[data-checkbox-filter]').forEach(function(input){
  if(input.dataset.checkboxFilterBound==='1')return;
  input.dataset.checkboxFilterBound='1';
  var selector=input.getAttribute('data-checkbox-filter');
  var root=selector?document.querySelector(selector):null;
  if(!root)return;
  input.addEventListener('input',function(){
    var q=(input.value||'').trim().toLowerCase();
    root.querySelectorAll('[data-filter-item]').forEach(function(item){
      var checked=!!item.querySelector('input:checked');
      item.hidden=!!q&&!checked&&!item.textContent.toLowerCase().includes(q);
    });
  });
});



/* Order workflow guardrails: keep the visible controls inside valid COD states. */
(() => {
  'use strict';
  const shippedStates = ['shipped', 'out_for_delivery', 'fulfilled', 'returned'];

  const setAllowedOptions = (select, allowed, preferred) => {
    if (!(select instanceof HTMLSelectElement)) return;
    const allowedSet = new Set(allowed);
    Array.from(select.options).forEach((option) => {
      option.disabled = !allowedSet.has(option.value);
    });
    if (!allowedSet.has(select.value)) {
      const next = allowedSet.has(preferred) ? preferred : allowed.find((value) => Array.from(select.options).some((option) => option.value === value));
      if (next) select.value = next;
    }
  };

  const syncOrderControls = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const order = form.querySelector('select[name="status"]');
    const payment = form.querySelector('select[name="payment_status"]');
    const fulfillment = form.querySelector('select[name="fulfillment_status"]');
    if (!(order instanceof HTMLSelectElement) || !(payment instanceof HTMLSelectElement) || !(fulfillment instanceof HTMLSelectElement)) return;

    const currentOrder = form.dataset.currentOrderStatus || 'pending';
    const currentPayment = form.dataset.currentPaymentStatus || 'pending';
    const currentFulfillment = form.dataset.currentFulfillmentStatus || 'unfulfilled';
    const paymentMethod = form.dataset.paymentMethod || 'cod';
    const guidance = form.querySelector('[data-order-state-guidance]');
    const nextAction = form.querySelector('[data-order-next-action]');
    const terminal = currentOrder === 'cancelled' || currentOrder === 'completed' || currentFulfillment === 'returned';

    if (terminal) {
      setAllowedOptions(order, [currentOrder], currentOrder);
      setAllowedOptions(payment, [currentPayment], currentPayment);
      setAllowedOptions(fulfillment, [currentFulfillment], currentFulfillment);
      if (guidance) guidance.textContent = currentOrder === 'cancelled'
        ? 'Cancelled orders are locked. Create a new order if the customer wants to purchase again.'
        : 'Completed/returned orders are locked here. Use Returns & Refunds for post-delivery changes.';
      if (nextAction) nextAction.hidden = true;
      return;
    }

    const cancellationLocked = shippedStates.includes(currentFulfillment);
    let selectedFulfillment = fulfillment.value;
    const transferWaiting = paymentMethod === 'manual_transfer' && payment.value !== 'paid';

    if (transferWaiting) {
      if (cancellationLocked) {
        setAllowedOptions(fulfillment, [currentFulfillment], currentFulfillment);
        setAllowedOptions(order, [currentOrder], currentOrder);
        setAllowedOptions(payment, ['pending', 'paid', 'failed'], currentPayment);
        if (guidance) guidance.textContent = 'This transfer order already entered delivery while payment is still pending. Verify the money and mark transfer received before any further fulfillment update.';
      } else {
        setAllowedOptions(fulfillment, ['unfulfilled'], 'unfulfilled');
        setAllowedOptions(order, ['pending', 'confirmed', 'cancelled'], currentOrder === 'confirmed' ? 'confirmed' : 'pending');
        setAllowedOptions(payment, currentPayment === 'paid' ? ['paid'] : ['pending', 'paid', 'failed'], currentPayment);
        if (guidance) guidance.textContent = 'Waiting for Bank transfer / InstaPay. Verify the money first, then use Mark transfer received before preparing or shipping the order.';
      }
      if (nextAction) nextAction.hidden = true;
      return;
    }

    if (order.value === 'cancelled' && !cancellationLocked) {
      fulfillment.value = 'unfulfilled';
      payment.value = 'pending';
      selectedFulfillment = 'unfulfilled';
    }

    let allowedOrder = ['pending', 'confirmed', 'processing'];
    let preferredOrder = order.value;
    if (selectedFulfillment === 'fulfilled') {
      allowedOrder = ['completed'];
      preferredOrder = 'completed';
    } else if (['preparing', 'shipped', 'out_for_delivery'].includes(selectedFulfillment)) {
      allowedOrder = ['confirmed', 'processing'];
      preferredOrder = 'processing';
    } else if (!cancellationLocked) {
      allowedOrder.push('cancelled');
    }
    setAllowedOptions(order, allowedOrder, preferredOrder);

    if (order.value === 'cancelled') {
      setAllowedOptions(fulfillment, ['unfulfilled'], 'unfulfilled');
      setAllowedOptions(payment, ['pending'], 'pending');
    } else if (order.value === 'completed' || fulfillment.value === 'fulfilled') {
      setAllowedOptions(fulfillment, ['fulfilled'], 'fulfilled');
      setAllowedOptions(payment, ['paid'], 'paid');
    } else {
      const currentRank = { unfulfilled: 0, preparing: 1, shipped: 2, out_for_delivery: 3 }[currentFulfillment] ?? 0;
      const fulfillmentOptions = ['unfulfilled', 'preparing', 'shipped', 'out_for_delivery'].filter((value) => ({ unfulfilled: 0, preparing: 1, shipped: 2, out_for_delivery: 3 }[value] ?? 0) >= currentRank);
      setAllowedOptions(fulfillment, fulfillmentOptions, currentFulfillment);
      const allowedPayment = paymentMethod === 'manual_transfer'
        ? (currentPayment === 'paid' ? ['paid'] : ['pending', 'paid', 'failed'])
        : (order.value === 'processing' ? ['pending'] : ['pending', 'failed']);
      setAllowedOptions(payment, allowedPayment, currentPayment === 'paid' ? 'paid' : 'pending');
    }

    if (nextAction) nextAction.hidden = order.value === 'cancelled';
    if (guidance) {
      guidance.textContent = cancellationLocked
        ? 'Cancellation is locked after shipment. Complete delivery, then use Returns & Refunds if needed.'
        : (paymentMethod === 'manual_transfer'
          ? 'Manual transfer can be marked paid before delivery after funds are actually received. Cancelling before shipment restores inventory.'
          : 'Only valid Cash on Delivery state combinations are selectable. Cancelling before shipment restores inventory.');
    }
  };

  const initOrderForms = (root = document) => {
    root.querySelectorAll('form.admin-order-update-form').forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      syncOrderControls(form);
      if (form.dataset.orderStateBound === '1') return;
      form.dataset.orderStateBound = '1';
      form.querySelectorAll('select[name="status"],select[name="payment_status"],select[name="fulfillment_status"]').forEach((select) => {
        select.addEventListener('change', () => syncOrderControls(form));
      });
    });
  };

  document.addEventListener('click',(event)=>{
    const preset=event.target.closest('[data-order-preset]');
    if(!preset)return;
    const form=preset.closest('form.admin-order-update-form');
    if(!(form instanceof HTMLFormElement))return;
    const order=form.querySelector('select[name="status"]');
    const payment=form.querySelector('select[name="payment_status"]');
    const fulfillment=form.querySelector('select[name="fulfillment_status"]');
    if(order instanceof HTMLSelectElement)order.value=preset.dataset.orderStatus||order.value;
    if(payment instanceof HTMLSelectElement)payment.value=preset.dataset.paymentStatus||payment.value;
    if(fulfillment instanceof HTMLSelectElement)fulfillment.value=preset.dataset.fulfillmentStatus||fulfillment.value;
    syncOrderControls(form);
    const advanced=form.querySelector('.admin-order-advanced');
    if(advanced instanceof HTMLDetailsElement)advanced.open=true;
    form.querySelector('button[type="submit"]')?.focus();
  });

  document.addEventListener('submit',(event)=>{
    const form=event.target;
    if(!(form instanceof HTMLFormElement)||!form.classList.contains('admin-order-update-form'))return;
    const status=form.querySelector('select[name="status"]');
    const submitter=event.submitter instanceof HTMLButtonElement?event.submitter:form.querySelector('button[type="submit"]');
    const current=form.dataset.currentOrderStatus||'';
    if(status instanceof HTMLSelectElement&&status.value==='cancelled'&&current!=='cancelled'&&submitter instanceof HTMLButtonElement){
      submitter.dataset.requireReauth='1';
      submitter.dataset.confirm='Cancel this order before shipment and restore its inventory?';
      submitter.dataset.confirmTone='danger';
    }else if(submitter instanceof HTMLButtonElement){
      delete submitter.dataset.requireReauth;
      delete submitter.dataset.confirm;
      delete submitter.dataset.confirmTone;
    }
  },true);

  initOrderForms();
  document.addEventListener('auraa:content-updated', (event) => initOrderForms(event.detail?.root || document));
})();

/* Returns: one-click safe refund maximum calculated by the server. */
document.addEventListener('click',(event)=>{
  const button=event.target.closest('[data-return-use-max]');
  if(!(button instanceof HTMLButtonElement))return;
  const form=button.closest('form');
  const input=form?.querySelector('[data-return-refund-input]');
  if(input instanceof HTMLInputElement){
    input.value=button.dataset.value||'0.00';
    input.focus();
  }
});

/* Focused admin workflow: order drawer. */
(() => {
  'use strict';
  const openRequestedOrder = () => {
    const id = new URL(window.location.href).searchParams.get('order');
    if (!id) return;
    const dialog = document.getElementById(`order-drawer-${id}`);
    if (dialog instanceof HTMLDialogElement && !dialog.open) {
      dialog.showModal();
      document.body.classList.add('admin-dialog-open');
    }
  };
  document.addEventListener('click',(event)=>{
    const open=event.target.closest('[data-order-drawer-open]');
    if(open){const dialog=document.getElementById(open.dataset.orderDrawerOpen||'');if(dialog instanceof HTMLDialogElement){dialog.showModal();document.body.classList.add('admin-dialog-open');}return;}
    const close=event.target.closest('[data-order-drawer-close]');
    if(close){const dialog=close.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();return;}
  });
  document.addEventListener('close',(event)=>{
    if(event.target instanceof HTMLDialogElement&&event.target.classList.contains('admin-order-drawer')){
      document.body.classList.remove('admin-dialog-open');
      const url=new URL(window.location.href);
      if(url.searchParams.has('order')){url.searchParams.delete('order');history.replaceState(history.state,'',url);}
    }
  },true);
  document.addEventListener('click',(event)=>{const dialog=event.target;if(dialog instanceof HTMLDialogElement&&dialog.classList.contains('admin-order-drawer')){const rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close();}});
  openRequestedOrder();
  document.addEventListener('auraa:content-updated',openRequestedOrder);
  // Catalog-data tabs keep the owner focused on one taxonomy at a time.
  // Search/status filtering is intentionally client-side: taxonomy records are
  // small reference data and filtering them should feel instant.
  const taxonomyTabs=[...document.querySelectorAll('[data-taxonomy-tab]')];
  const taxonomyPanels=[...document.querySelectorAll('[data-taxonomy-panel]')];
  if(taxonomyTabs.length&&taxonomyPanels.length){
    const createType=document.querySelector('[data-taxonomy-create-type]');
    const search=document.querySelector('[data-taxonomy-search]');
    const statusFilter=document.querySelector('[data-taxonomy-status-filter]');
    const applyTaxonomyFilter=(panel)=>{
      if(!(panel instanceof HTMLElement))return;
      const q=(search instanceof HTMLInputElement?search.value:'').trim().toLowerCase();
      const status=statusFilter instanceof HTMLSelectElement?statusFilter.value:'';
      let visible=0;
      panel.querySelectorAll('[data-taxonomy-record]').forEach((record)=>{
        if(!(record instanceof HTMLElement))return;
        const hay=(record.dataset.taxonomySearchValue||record.textContent||'').toLowerCase();
        const rowStatus=record.dataset.taxonomyStatus||'';
        const show=(!q||hay.includes(q))&&(!status||status===rowStatus);
        record.hidden=!show;
        if(show)visible++;
      });
      const count=panel.querySelector('[data-taxonomy-visible-count]');
      if(count)count.textContent=String(visible);
      const empty=panel.querySelector('[data-taxonomy-no-results]');
      if(empty)empty.hidden=visible!==0;
    };
    const activePanel=()=>taxonomyPanels.find(panel=>panel.classList.contains('is-active'))||taxonomyPanels[0];
    const activateTaxonomy=type=>{
      const valid=taxonomyTabs.some(btn=>btn.dataset.taxonomyTab===type)?type:taxonomyTabs[0].dataset.taxonomyTab;
      taxonomyTabs.forEach(btn=>{const active=btn.dataset.taxonomyTab===valid;btn.classList.toggle('is-active',active);btn.setAttribute('aria-selected',active?'true':'false');});
      taxonomyPanels.forEach(panel=>panel.classList.toggle('is-active',panel.dataset.taxonomyPanel===valid));
      if(createType instanceof HTMLSelectElement)createType.value=valid;
      const createImage=document.querySelector('[data-taxonomy-create-image]');
      if(createImage instanceof HTMLElement)createImage.hidden=valid==='family';
      if(search instanceof HTMLInputElement)search.value='';
      if(statusFilter instanceof HTMLSelectElement)statusFilter.value='';
      applyTaxonomyFilter(activePanel());
      history.replaceState(null,'',`${location.pathname}${location.search}#${valid}`);
    };
    taxonomyTabs.forEach(btn=>btn.addEventListener('click',()=>activateTaxonomy(btn.dataset.taxonomyTab||'')));
    createType?.addEventListener('change',()=>activateTaxonomy(createType.value||''));
    search?.addEventListener('input',()=>applyTaxonomyFilter(activePanel()));
    statusFilter?.addEventListener('change',()=>applyTaxonomyFilter(activePanel()));
    activateTaxonomy(location.hash.replace(/^#/,''));
  }

})();


/* Copy one-time admin values without making the workflow depend on Clipboard API support. */
(() => {
  'use strict';
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-value]');
    if (!(button instanceof HTMLElement)) return;
    const value = button.getAttribute('data-copy-value') || '';
    if (!value) return;
    const original = button.textContent || 'Copy';
    let copied = false;
    try {
      if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(value); copied = true; }
    } catch (_) {}
    if (!copied) {
      const input = button.parentElement?.querySelector('input[readonly],textarea[readonly]');
      if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
        input.focus(); input.select();
        try { copied = document.execCommand('copy'); } catch (_) {}
      }
    }
    button.textContent = copied ? 'Copied' : 'Select & copy';
    window.setTimeout(() => { button.textContent = original; }, 1800);
  });
})();

// Keep the owner/admin notification badge fresh while the admin panel is open.
// Order notifications are written synchronously at checkout; this lightweight
// same-origin poll makes them visible without forcing the administrator to reload.
(()=>{
  const bell=document.querySelector('[data-admin-notification-bell]');
  if(!bell)return;
  const endpoint=bell.dataset.navStateUrl||'';
  if(!endpoint)return;
  let running=false;
  let previousUnread=Number.parseInt(bell.querySelector('.app-notification-count')?.textContent||'0',10)||0;
  let toastTimer=0;
  const showLiveNotice=(count,href)=>{
    let toast=document.querySelector('[data-admin-live-notification]');
    if(!toast){
      toast=document.createElement('div');
      toast.className='admin-live-notification';
      toast.dataset.adminLiveNotification='';
      toast.setAttribute('role','status');
      toast.setAttribute('aria-live','polite');
      document.body.appendChild(toast);
    }
    toast.innerHTML='';
    const copy=document.createElement('span');
    copy.textContent=count===1?'New admin notification received.':`${count} new admin notifications received.`;
    const link=document.createElement('a');
    link.href=href||bell.href;
    link.textContent='View';
    toast.append(copy,link);
    toast.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer=window.setTimeout(()=>toast.classList.remove('is-visible'),6000);
  };
  const refresh=async()=>{
    if(running||document.hidden)return;
    running=true;
    try{
      const response=await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      if(!response.ok)return;
      const data=await response.json();
      if(!data||data.ok!==true)return;
      const unread=Math.max(0,Number.parseInt(data.unread,10)||0);
      const nextHref=typeof data.href==='string'&&data.href?data.href:bell.href;
      if(unread>previousUnread)showLiveNotice(unread-previousUnread,nextHref);
      previousUnread=unread;
      bell.classList.toggle('has-unread',unread>0);
      if(nextHref)bell.href=nextHref;
      bell.setAttribute('aria-label',unread>0?`Notifications · ${unread} unread`:'Notifications');
      let count=bell.querySelector('.app-notification-count');
      if(unread>0){
        if(!count){count=document.createElement('span');count.className='app-notification-count';bell.appendChild(count);}
        count.textContent=String(Math.min(99,unread));
      }else if(count){count.remove();}
    }catch(_err){
      // The bell is non-critical UI. A transient network/session failure should
      // never interrupt the administrator's current task.
    }finally{running=false;}
  };
  window.setTimeout(refresh,2500);
  window.setInterval(refresh,20000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();

/* v4.2.4 bulk-selection workflow. Checkboxes may live in table/card rows while
   their POST inputs are associated with one external bulk form via form=id. */
(() => {
  'use strict';
  const initBulkForms = (root = document) => {
    root.querySelectorAll('[data-bulk-form]').forEach((form) => {
      if (!(form instanceof HTMLFormElement) || form.dataset.bulkBound === '1') return;
      form.dataset.bulkBound = '1';
      const panel = form.closest('.admin-panel') || form.parentElement || document;
      const formId = form.id;
      if (!formId) return;
      const items = () => Array.from(panel.querySelectorAll(`[data-bulk-item][form="${CSS.escape(formId)}"]`)).filter((el) => el instanceof HTMLInputElement && !el.disabled && !el.closest('[hidden]') && el.getClientRects().length > 0);
      const selectAll = panel.querySelector('[data-bulk-select-all]');
      const count = form.querySelector('[data-bulk-count]');
      const action = form.querySelector('[data-bulk-action]');
      const apply = form.querySelector('[data-bulk-apply]');

      const sync = () => {
        const rows = items();
        const checked = rows.filter((box) => box.checked);
        if (count) count.textContent = `${checked.length} selected`;
        form.classList.toggle('has-selection', checked.length > 0);
        rows.forEach((box) => box.closest('[data-bulk-row],[data-media-card],[data-taxonomy-record]')?.classList.toggle('is-bulk-selected', box.checked));
        if (selectAll instanceof HTMLInputElement) {
          selectAll.checked = rows.length > 0 && checked.length === rows.length;
          selectAll.indeterminate = checked.length > 0 && checked.length < rows.length;
        }
        if (apply instanceof HTMLButtonElement) apply.disabled = checked.length === 0 || (action instanceof HTMLSelectElement && action.value === '');
        configureApply(checked.length);
      };

      const configureApply = (selectedCount) => {
        if (!(apply instanceof HTMLElement)) return;
        const kind = form.dataset.bulkKind || 'items';
        const value = action instanceof HTMLSelectElement ? action.value : (form.dataset.bulkAction || 'delete');
        delete apply.dataset.requireReauth;
        delete apply.dataset.confirm;
        delete apply.dataset.confirmTone;
        if (!value || selectedCount <= 0) return;
        if (value === 'delete') {
          apply.dataset.requireReauth = '1';
          apply.dataset.confirmTone = 'danger';
          apply.dataset.confirm = `Permanently delete ${selectedCount} selected ${kind}? This cannot be undone.`;
        } else if (value === 'archive') {
          apply.dataset.confirmTone = 'warning';
          apply.dataset.confirm = `Archive ${selectedCount} selected ${kind}? They will stop appearing publicly but can be restored.`;
        } else if (value === 'restore') {
          apply.dataset.confirm = `Restore ${selectedCount} selected ${kind}?`;
        } else if (value === 'disable') {
          apply.dataset.confirmTone = 'warning';
          apply.dataset.confirm = `Disable ${selectedCount} selected ${kind}?`;
        } else if (value === 'enable') {
          apply.dataset.confirm = `Enable ${selectedCount} selected ${kind}?`;
        }
      };

      selectAll?.addEventListener('change', () => {
        if (!(selectAll instanceof HTMLInputElement)) return;
        items().forEach((box) => { box.checked = selectAll.checked; });
        sync();
      });
      panel.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches(`[data-bulk-item][form="${CSS.escape(formId)}"]`)) sync();
      });
      action?.addEventListener('change', sync);
      form.addEventListener('submit', (event) => {
        const checked = items().filter((box) => box.checked);
        if (!checked.length) { event.preventDefault(); return; }
        if (action instanceof HTMLSelectElement && !action.value) { event.preventDefault(); action.focus(); }
      });
      sync();
    });
  };
  initBulkForms();
  document.addEventListener('auraa:content-updated', (event) => initBulkForms(event.detail?.root || document));
})();

/* v4.2.4 collapsed-navigation labels and bulk checkbox behavior */
(() => {
  'use strict';
  const body = document.body;
  if (!body?.classList.contains('admin-page')) return;

  const tooltip = document.createElement('div');
  tooltip.className = 'admin-nav-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  document.body.appendChild(tooltip);

  const hideNavTooltip = () => tooltip.classList.remove('is-visible');
  const showNavTooltip = (link) => {
    if (!body.classList.contains('admin-sidebar-collapsed') || window.innerWidth <= 980) return;
    const label = link?.dataset?.label || link?.getAttribute?.('title') || '';
    if (!label) return;
    const rect = link.getBoundingClientRect();
    tooltip.textContent = label;
    tooltip.style.left = `${Math.round(rect.right + 10)}px`;
    tooltip.style.top = `${Math.round(rect.top + rect.height / 2)}px`;
    tooltip.style.transform = 'translateY(-50%)';
    tooltip.classList.add('is-visible');
  };
  document.addEventListener('pointerover', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.admin-side-link[data-label]') : null;
    if (link) showNavTooltip(link);
  });
  document.addEventListener('pointerout', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.admin-side-link[data-label]') : null;
    if (link) hideNavTooltip();
  });
  document.addEventListener('focusin', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.admin-side-link[data-label]') : null;
    if (link) showNavTooltip(link);
  });
  document.addEventListener('focusout', (event) => {
    if (event.target instanceof Element && event.target.closest('.admin-side-link[data-label]')) hideNavTooltip();
  });
  window.addEventListener('resize', hideNavTooltip, { passive: true });
  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('.admin-taxonomy-bulk-check,.admin-shipping-bulk-check')) event.stopPropagation();
  });
})();

/* v4.2.7 browser-side storefront image preparation.
   This is intentionally independent from PHP GD so XAMPP/shared hosts cannot
   accidentally store multi-megabyte PNG/JPG files as storefront masters. */
(()=>{
  'use strict';
  if(!document.body?.classList.contains('admin-page'))return;

  const fileInputsForForm=(form)=>[...form.querySelectorAll('input[type="file"][accept*="image/"]')]
    .filter(input=>input.name!=='diagnostics'&&input.files&&input.files.length);
  const skipSubmitter=(submitter)=>{
    if(!(submitter instanceof HTMLButtonElement)&&!(submitter instanceof HTMLInputElement))return false;
    const value=(submitter.value||'').toLowerCase();
    return /delete|remove|archive|restore|move|bulk/.test(value)||submitter.hasAttribute('formnovalidate');
  };
  const planFor=(input,form)=>{
    const name=(input.name||'').toLowerCase();
    const folder=(form.querySelector('select[name="folder"]')?.value||'').toLowerCase();
    const isHomepage=name.includes('mobile_image')||name.includes('image_upload')&&form.closest('.homepage-content-editor')||folder==='homepage';
    return isHomepage
      ? {max:1920,quality:.80,target:750*1024}
      : {max:1400,quality:.80,target:450*1024};
  };
  const decodeImage=async(file)=>{
    if('createImageBitmap' in window){
      try{return await createImageBitmap(file,{imageOrientation:'from-image'});}catch(_){
        try{return await createImageBitmap(file);}catch(__){}
      }
    }
    return await new Promise((resolve,reject)=>{
      const img=new Image(),url=URL.createObjectURL(file);
      img.onload=()=>{URL.revokeObjectURL(url);resolve(img);};
      img.onerror=()=>{URL.revokeObjectURL(url);reject(new Error('Image decode failed'));};
      img.src=url;
    });
  };
  const blobFromCanvas=(canvas,quality)=>new Promise(resolve=>canvas.toBlob(resolve,'image/webp',quality));
  const prepareFile=async(file,plan)=>{
    if(!(file instanceof File)||file.type==='image/gif')return file;
    if(!/^image\/(?:jpeg|png|webp)$/i.test(file.type))return file;
    let image=null;
    try{
      image=await decodeImage(file);
      const width=image.width||image.naturalWidth||0,height=image.height||image.naturalHeight||0;
      if(!width||!height)return file;
      const scale=Math.min(1,plan.max/Math.max(width,height));
      const targetWidth=Math.max(1,Math.round(width*scale)),targetHeight=Math.max(1,Math.round(height*scale));
      const canvas=document.createElement('canvas');canvas.width=targetWidth;canvas.height=targetHeight;
      const ctx=canvas.getContext('2d',{alpha:true});if(!ctx)return file;
      ctx.drawImage(image,0,0,targetWidth,targetHeight);
      let blob=null;
      for(const quality of [plan.quality,.74,.68,.62]){
        blob=await blobFromCanvas(canvas,quality);
        if(blob&&blob.size<=plan.target)break;
      }
      if(!blob||blob.size<=0)return file;
      // Do not replace an already-small WEBP with something materially larger.
      if(file.type==='image/webp'&&file.size<=blob.size&&file.size<=plan.target)return file;
      const base=(file.name||'image').replace(/\.[^.]+$/,'')||'image';
      return new File([blob],`${base}.webp`,{type:'image/webp',lastModified:file.lastModified||Date.now()});
    }finally{
      if(image&&typeof image.close==='function')try{image.close();}catch(_){}
    }
  };
  const restoreSubmitter=(submitter)=>{
    if(submitter instanceof HTMLButtonElement){
      submitter.disabled=false;
      if(submitter.dataset.originalLabel!==undefined)submitter.textContent=submitter.dataset.originalLabel;
      delete submitter.dataset.originalLabel;
    }else if(submitter instanceof HTMLInputElement){
      submitter.disabled=false;
      if(submitter.dataset.originalLabel!==undefined)submitter.value=submitter.dataset.originalLabel;
      delete submitter.dataset.originalLabel;
    }
  };
  const statusFor=(form)=>{
    let status=form.querySelector('[data-client-image-status]');
    if(!status){
      status=document.createElement('div');status.className='admin-client-image-status';status.dataset.clientImageStatus='';
      status.setAttribute('role','status');status.setAttribute('aria-live','polite');
      form.appendChild(status);
    }
    return status;
  };
  const formatBytes=(bytes)=>bytes>=1024*1024?`${(bytes/(1024*1024)).toFixed(1)} MB`:`${Math.max(1,Math.round(bytes/1024))} KB`;

  document.addEventListener('change',(event)=>{
    const input=event.target;
    if(input instanceof HTMLInputElement&&input.type==='file'&&input.accept.includes('image/')){
      const form=input.form;if(form)delete form.dataset.clientImagePrepared;
    }
  });
  document.addEventListener('submit',async(event)=>{
    const form=event.target;
    if(!(form instanceof HTMLFormElement)||form.dataset.clientImagePrepared==='1'||skipSubmitter(event.submitter))return;
    const inputs=fileInputsForForm(form);if(!inputs.length)return;
    if(typeof DataTransfer==='undefined'||typeof HTMLCanvasElement==='undefined')return;
    event.preventDefault();event.stopImmediatePropagation();
    const submitter=event.submitter;
    const status=statusFor(form);
    const originalBytes=inputs.reduce((sum,input)=>sum+[...input.files].reduce((a,file)=>a+file.size,0),0);
    status.textContent=`Optimizing ${inputs.reduce((sum,input)=>sum+input.files.length,0)} image${inputs.reduce((sum,input)=>sum+input.files.length,0)===1?'':'s'} before upload…`;
    form.setAttribute('aria-busy','true');
    if(submitter instanceof HTMLButtonElement||submitter instanceof HTMLInputElement){submitter.disabled=true;submitter.dataset.originalLabel=submitter.value||submitter.textContent||'';if(submitter instanceof HTMLButtonElement)submitter.textContent='Optimizing images…';}
    try{
      let preparedBytes=0;
      for(const input of inputs){
        const transfer=new DataTransfer(),plan=planFor(input,form);
        for(const file of [...input.files]){
          const prepared=await prepareFile(file,plan);
          transfer.items.add(prepared);preparedBytes+=prepared.size;
        }
        input.files=transfer.files;
      }
      status.textContent=`Images ready: ${formatBytes(originalBytes)} → ${formatBytes(preparedBytes)} before upload.`;
      form.dataset.clientImagePrepared='1';
      restoreSubmitter(submitter);
      form.removeAttribute('aria-busy');
      if(submitter instanceof HTMLButtonElement||submitter instanceof HTMLInputElement)form.requestSubmit(submitter);else form.requestSubmit();
    }catch(_){
      status.textContent='Browser compression was unavailable. The server will validate the original image.';
      form.dataset.clientImagePrepared='1';
      restoreSubmitter(submitter);
      form.removeAttribute('aria-busy');
      if(submitter instanceof HTMLButtonElement||submitter instanceof HTMLInputElement)form.requestSubmit(submitter);else form.requestSubmit();
    }
  },true);
})();


/* v4.2.8 UX finish: photo previews, gallery manager, Media Library modal and dirty-state feedback. */
(()=>{
  'use strict';
  const bytes=(value)=>value>=1024*1024?`${(value/(1024*1024)).toFixed(1)} MB`:`${Math.max(1,Math.round(value/1024))} KB`;
  const markDirty=(form)=>{
    if(!(form instanceof HTMLFormElement))return;
    form.dataset.isDirty='1';
    const indicator=form.querySelector('[data-unsaved-indicator]');
    if(indicator instanceof HTMLElement)indicator.hidden=false;
  };
  const clearDirty=(form)=>{
    if(!(form instanceof HTMLFormElement))return;
    delete form.dataset.isDirty;
    const indicator=form.querySelector('[data-unsaved-indicator]');
    if(indicator instanceof HTMLElement)indicator.hidden=true;
  };
  const previewFile=(file,img,meta)=>{
    if(!(file instanceof File)||!(img instanceof HTMLImageElement))return;
    const url=URL.createObjectURL(file);img.hidden=false;img.src=url;
    const sibling=img.nextElementSibling;if(sibling instanceof HTMLElement)sibling.hidden=true;
    img.onload=()=>{
      if(meta instanceof HTMLElement){meta.hidden=false;meta.textContent=`Selected · ${img.naturalWidth} × ${img.naturalHeight}px · ${bytes(file.size)} · optimized before upload`;}
      URL.revokeObjectURL(url);
    };
  };

  const updateGalleryOrder=(manager)=>{
    const hidden=manager.querySelector('[data-gallery-order]');
    if(!(hidden instanceof HTMLInputElement))return;
    hidden.value=JSON.stringify([...manager.querySelectorAll('[data-gallery-item]')]
      .filter(card=>!card.classList.contains('is-removed'))
      .map(card=>card.dataset.galleryPath||'').filter(Boolean));
  };
  const updatePhotoCount=(manager)=>{
    const mainRemove=manager.closest('form')?.querySelector('input[name="remove_main_image"]');
    const mainImg=manager.querySelector('[data-main-photo-image]');
    const mainUpload=manager.querySelector('[data-live-main-upload]');
    const hasMain=(mainUpload instanceof HTMLInputElement&&mainUpload.files?.length>0)||((mainImg instanceof HTMLImageElement&&!mainImg.hidden)&&!(mainRemove instanceof HTMLInputElement&&mainRemove.checked));
    const existing=[...manager.querySelectorAll('[data-gallery-item]')].filter(card=>!card.classList.contains('is-removed')).length;
    const galleryUpload=manager.querySelector('[data-live-gallery-upload]');
    const pending=galleryUpload instanceof HTMLInputElement?(galleryUpload.files?.length||0):0;
    const count=(hasMain?1:0)+existing+pending;
    const label=manager.querySelector('[data-product-photo-count]');if(label)label.textContent=`${count} / ${manager.dataset.maxPhotos||6}`;
    const empty=manager.querySelector('[data-gallery-empty]');if(empty instanceof HTMLElement)empty.hidden=(existing+pending)>0;
  };
  const renderPendingGallery=(manager,input)=>{
    const pending=manager.querySelector('[data-gallery-pending]');if(!(pending instanceof HTMLElement)||!(input instanceof HTMLInputElement))return;
    pending.innerHTML='';
    [...(input.files||[])].forEach((file,index)=>{
      const card=document.createElement('article');card.className='admin-gallery-card is-pending';
      const thumb=document.createElement('div');thumb.className='admin-gallery-thumb';const img=document.createElement('img');img.alt='Pending gallery image';thumb.appendChild(img);
      const copy=document.createElement('small');copy.className='admin-gallery-pending-copy';copy.textContent=`Pending ${index+1} · ${bytes(file.size)}`;
      const remove=document.createElement('button');remove.type='button';remove.className='admin-gallery-pending-remove';remove.dataset.pendingGalleryRemove=String(index);remove.textContent='Remove';
      card.append(thumb,copy,remove);pending.appendChild(card);previewFile(file,img,null);
    });
    updatePhotoCount(manager);
  };
  const trimGalleryFiles=(manager,input)=>{
    if(!(input instanceof HTMLInputElement)||typeof DataTransfer==='undefined')return;
    const mainRemove=input.form?.querySelector('input[name="remove_main_image"]');
    const mainImg=manager.querySelector('[data-main-photo-image]');const mainUpload=manager.querySelector('[data-live-main-upload]');
    const hasMain=(mainUpload instanceof HTMLInputElement&&mainUpload.files?.length>0)||((mainImg instanceof HTMLImageElement&&!mainImg.hidden)&&!(mainRemove instanceof HTMLInputElement&&mainRemove.checked));
    const existing=[...manager.querySelectorAll('[data-gallery-item]')].filter(card=>!card.classList.contains('is-removed')).length;
    const allowed=Math.max(0,Number(manager.dataset.maxPhotos||6)-(hasMain?1:0)-existing);
    if((input.files?.length||0)<=allowed)return;
    const transfer=new DataTransfer();[...input.files].slice(0,allowed).forEach(file=>transfer.items.add(file));input.files=transfer.files;
    const status=input.form?.querySelector('[data-client-image-status]')||document.createElement('div');
    if(!status.isConnected){status.className='admin-client-image-status';status.dataset.clientImageStatus='';input.form?.appendChild(status);}
    status.textContent=allowed>0?`Only ${allowed} more gallery photo${allowed===1?'':'s'} can be added. Extra selections were ignored.`:'This product already has 6 photos. Remove one before adding another.';
  };

  const bindPhotoManagers=(root=document)=>{
    root.querySelectorAll('[data-product-photo-manager]').forEach(manager=>{
      if(!(manager instanceof HTMLElement)||manager.dataset.photoBound==='1')return;manager.dataset.photoBound='1';
      const form=manager.closest('form');const mainInput=manager.querySelector('[data-live-main-upload]');const galleryInput=manager.querySelector('[data-live-gallery-upload]');
      if(mainInput instanceof HTMLInputElement)mainInput.addEventListener('change',()=>{
        const file=mainInput.files?.[0];const img=manager.querySelector('[data-main-photo-image]');const meta=manager.querySelector('[data-main-photo-pending]');if(file&&img instanceof HTMLImageElement)previewFile(file,img,meta);updatePhotoCount(manager);markDirty(form);
      });
      if(galleryInput instanceof HTMLInputElement)galleryInput.addEventListener('change',()=>{trimGalleryFiles(manager,galleryInput);renderPendingGallery(manager,galleryInput);markDirty(form);});
      manager.addEventListener('click',(event)=>{
        const button=event.target.closest('button');if(!(button instanceof HTMLButtonElement))return;
        if(button.matches('[data-pending-gallery-remove]')){
          if(!(galleryInput instanceof HTMLInputElement)||typeof DataTransfer==='undefined')return;
          const index=Number(button.dataset.pendingGalleryRemove||-1);const transfer=new DataTransfer();
          [...(galleryInput.files||[])].forEach((file,i)=>{if(i!==index)transfer.items.add(file);});galleryInput.files=transfer.files;renderPendingGallery(manager,galleryInput);markDirty(form);return;
        }
        const card=button.closest('[data-gallery-item]');if(!(card instanceof HTMLElement))return;
        if(button.matches('[data-gallery-remove]')){const box=card.querySelector('.admin-gallery-remove-input');card.classList.toggle('is-removed');if(box instanceof HTMLInputElement)box.checked=card.classList.contains('is-removed');const promote=manager.querySelector('[data-gallery-promote]');if(card.classList.contains('is-removed')&&promote instanceof HTMLInputElement&&promote.value===(card.dataset.galleryPath||'')){promote.value='';card.classList.remove('is-promoted');}updateGalleryOrder(manager);updatePhotoCount(manager);markDirty(form);return;}
        const dir=button.dataset.galleryMove;if(dir){const sibling=dir==='left'?card.previousElementSibling:card.nextElementSibling;if(sibling instanceof HTMLElement&&sibling.matches('[data-gallery-item]')){if(dir==='left')card.parentElement?.insertBefore(card,sibling);else card.parentElement?.insertBefore(sibling,card);}updateGalleryOrder(manager);markDirty(form);return;}
        if(button.matches('[data-gallery-set-main]')){if(card.classList.contains('is-removed'))return;const promote=manager.querySelector('[data-gallery-promote]');if(promote instanceof HTMLInputElement)promote.value=card.dataset.galleryPath||'';manager.querySelectorAll('[data-gallery-item]').forEach(item=>item.classList.toggle('is-promoted',item===card));const img=card.querySelector('img');const main=manager.querySelector('[data-main-photo-image]');if(img instanceof HTMLImageElement&&main instanceof HTMLImageElement){main.hidden=false;main.src=img.currentSrc||img.src;const holder=main.nextElementSibling;if(holder instanceof HTMLElement)holder.hidden=true;}const meta=manager.querySelector('[data-main-photo-pending]');if(meta instanceof HTMLElement){meta.hidden=false;meta.textContent='This gallery image will become the main image after Save.';}markDirty(form);return;}
      });
      let dragged=null;
      manager.addEventListener('dragstart',(event)=>{const card=event.target.closest?.('[data-gallery-item]');if(card instanceof HTMLElement){dragged=card;card.classList.add('is-dragging');event.dataTransfer?.setData('text/plain',card.dataset.galleryPath||'');}});
      manager.addEventListener('dragover',(event)=>{if(!dragged)return;const target=event.target.closest?.('[data-gallery-item]');if(!(target instanceof HTMLElement)||target===dragged)return;event.preventDefault();const rect=target.getBoundingClientRect();target.parentElement?.insertBefore(dragged,event.clientX<rect.left+rect.width/2?target:target.nextSibling);});
      manager.addEventListener('dragend',()=>{if(dragged){dragged.classList.remove('is-dragging');dragged=null;updateGalleryOrder(manager);markDirty(form);}});
      form?.querySelector('input[name="remove_main_image"]')?.addEventListener('change',()=>{updatePhotoCount(manager);markDirty(form);});
      updateGalleryOrder(manager);updatePhotoCount(manager);
    });
  };

  const bindHomepagePreviews=(root=document)=>{
    root.querySelectorAll('[data-homepage-upload-preview]').forEach(input=>{
      if(!(input instanceof HTMLInputElement)||input.dataset.previewBound==='1')return;input.dataset.previewBound='1';
      input.addEventListener('change',()=>{const file=input.files?.[0];if(!file)return;const form=input.form;const key=input.dataset.homepageUploadPreview||'media_image_path';const libraryValue=form?.querySelector(`input[name="${CSS.escape(key)}"]`);if(libraryValue instanceof HTMLInputElement)libraryValue.value='';const removeName=key==='media_mobile_image_path'?'remove_mobile_image':'remove_image';const remove=form?.querySelector(`input[name="${removeName}"]`);if(remove instanceof HTMLInputElement)remove.checked=false;const slot=form?.querySelector(`[data-homepage-live-preview="${CSS.escape(key)}"]`);const img=slot?.querySelector('img');const meta=form?.querySelector(`[data-homepage-upload-meta="${CSS.escape(key)}"]`);if(img instanceof HTMLImageElement)previewFile(file,img,meta);markDirty(form);window.setTimeout(()=>document.dispatchEvent(new CustomEvent('homepage-media-preview-updated')),20);});
    });
  };

  let activeMediaLibrary=null;
  const globalMediaDialog=()=>document.querySelector('[data-global-media-picker]');
  const resetGlobalMediaDialog=(dialog)=>{
    if(!(dialog instanceof HTMLDialogElement))return;
    dialog.dataset.mediaTarget='media_image_path';
    delete dialog.dataset.pendingMediaPath;delete dialog.dataset.pendingMediaUrl;
    dialog.querySelectorAll('[data-media-path]').forEach(button=>{button.classList.remove('is-selected');button.hidden=false;});
    dialog.querySelectorAll('[data-media-target-switch]').forEach(button=>button.classList.toggle('is-active',button.dataset.mediaTargetSwitch==='media_image_path'));
    const search=dialog.querySelector('[data-media-library-search]');if(search instanceof HTMLInputElement)search.value='';
    const selection=dialog.querySelector('[data-global-media-selection]');if(selection)selection.textContent='No image selected.';
    const use=dialog.querySelector('[data-media-library-use]');if(use instanceof HTMLButtonElement)use.disabled=true;
  };
  document.addEventListener('click',(event)=>{
    const open=event.target.closest('[data-media-library-open]');
    if(open){
      activeMediaLibrary=open.closest('[data-media-library]');
      const dialog=globalMediaDialog();
      if(!(activeMediaLibrary instanceof HTMLElement)||!(dialog instanceof HTMLDialogElement))return;
      resetGlobalMediaDialog(dialog);
      const mobileTab=dialog.querySelector('[data-media-mobile-tab]');
      if(mobileTab instanceof HTMLElement)mobileTab.hidden=activeMediaLibrary.dataset.mediaHasMobile!=='1';
      if(!dialog.open)dialog.showModal();
      const search=dialog.querySelector('[data-media-library-search]');if(search instanceof HTMLInputElement)setTimeout(()=>search.focus(),0);
      return;
    }
    const mediaSwitch=event.target.closest('[data-media-target-switch]');
    if(mediaSwitch){
      const dialog=mediaSwitch.closest('[data-global-media-picker]');if(!(dialog instanceof HTMLDialogElement))return;
      const targetName=mediaSwitch.dataset.mediaTargetSwitch||'media_image_path';dialog.dataset.mediaTarget=targetName;
      dialog.querySelectorAll('[data-media-target-switch]').forEach(button=>button.classList.toggle('is-active',button===mediaSwitch));
      return;
    }
    const mediaButton=event.target.closest('[data-global-media-picker] [data-media-path]');
    if(mediaButton){
      const dialog=mediaButton.closest('[data-global-media-picker]');if(!(dialog instanceof HTMLDialogElement))return;
      dialog.dataset.pendingMediaPath=mediaButton.dataset.mediaPath||'';dialog.dataset.pendingMediaUrl=mediaButton.dataset.mediaUrl||'';
      dialog.querySelectorAll('[data-media-path]').forEach(button=>button.classList.toggle('is-selected',button===mediaButton));
      const selection=dialog.querySelector('[data-global-media-selection]');if(selection)selection.textContent='Image selected. Apply it to this field.';
      const use=dialog.querySelector('[data-media-library-use]');if(use instanceof HTMLButtonElement)use.disabled=!dialog.dataset.pendingMediaPath;
      return;
    }
    const close=event.target.closest('[data-media-library-close]');
    if(close){const dialog=close.closest('dialog');if(dialog instanceof HTMLDialogElement)dialog.close();activeMediaLibrary=null;return;}
    const use=event.target.closest('[data-media-library-use]');
    if(use){
      const dialog=use.closest('[data-global-media-picker]');if(!(dialog instanceof HTMLDialogElement)||!(activeMediaLibrary instanceof HTMLElement))return;
      const targetName=dialog.dataset.mediaTarget||'media_image_path';const path=dialog.dataset.pendingMediaPath||'';if(!path)return;
      const target=activeMediaLibrary.querySelector(`input[name="${CSS.escape(targetName)}"]`);if(!(target instanceof HTMLInputElement))return;
      target.value=path;
      const form=activeMediaLibrary.closest('form');const uploadName=targetName==='media_mobile_image_path'?'mobile_image_upload':'image_upload';const upload=form?.querySelector(`input[name="${uploadName}"]`);if(upload instanceof HTMLInputElement)upload.value='';const removeName=targetName==='media_mobile_image_path'?'remove_mobile_image':'remove_image';const remove=form?.querySelector(`input[name="${removeName}"]`);if(remove instanceof HTMLInputElement)remove.checked=false;const preview=form?.querySelector(`[data-homepage-live-preview="${CSS.escape(targetName)}"]`);const img=preview?.querySelector('img');
      if(img instanceof HTMLImageElement&&dialog.dataset.pendingMediaUrl){img.src=dialog.dataset.pendingMediaUrl;img.hidden=false;const fallback=img.nextElementSibling;if(fallback instanceof HTMLElement)fallback.hidden=true;}document.dispatchEvent(new CustomEvent('homepage-media-preview-updated'));
      const status=activeMediaLibrary.querySelector('[data-media-selection]');if(status)status.textContent=`${targetName==='media_mobile_image_path'?'Mobile':'Main'} image selected · not saved yet.`;
      markDirty(form);dialog.close();activeMediaLibrary=null;return;
    }
  });
  document.addEventListener('input',(event)=>{const search=event.target.closest('[data-media-library-search]');if(!(search instanceof HTMLInputElement))return;const dialog=search.closest('[data-global-media-picker]');const q=search.value.trim().toLowerCase();dialog?.querySelectorAll('[data-media-path]').forEach(button=>{button.hidden=q!==''&&!((button.dataset.mediaSearch||'').includes(q));});});
  document.addEventListener('cancel',(event)=>{if(event.target instanceof HTMLDialogElement&&event.target.matches('[data-global-media-picker]'))activeMediaLibrary=null;});

  const bindDirtyForms=(root=document)=>{
    root.querySelectorAll('[data-dirty-form]').forEach(form=>{
      if(!(form instanceof HTMLFormElement)||form.dataset.dirtyBound==='1')return;form.dataset.dirtyBound='1';
      const dirty=(event)=>{if(event.target instanceof HTMLInputElement&&event.target.type==='hidden')return;markDirty(form);};
      form.addEventListener('input',dirty);form.addEventListener('change',dirty);form.addEventListener('submit',()=>clearDirty(form));
    });
  };
  window.addEventListener('beforeunload',(event)=>{if(!document.querySelector('form[data-is-dirty="1"]'))return;event.preventDefault();event.returnValue='';});
  bindPhotoManagers();bindHomepagePreviews();bindDirtyForms();
  document.addEventListener('auraa:content-updated',(event)=>{const root=event.detail?.root||document;bindPhotoManagers(root);bindHomepagePreviews(root);bindDirtyForms(root);});
})();


/* v4.2.9 genuine mobile admin filters + compact bulk behavior. */
(()=>{
  'use strict';
  const closeSheet=(sheet)=>{if(!(sheet instanceof HTMLElement))return;sheet.classList.remove('is-mobile-open');document.body.classList.remove('admin-filter-open');};
  document.addEventListener('click',(event)=>{
    const open=event.target instanceof Element?event.target.closest('[data-admin-filter-open]'):null;
    if(open){const id=open.getAttribute('data-admin-filter-open');const sheet=id?document.getElementById(id):null;if(sheet instanceof HTMLElement){sheet.classList.add('is-mobile-open');document.body.classList.add('admin-filter-open');setTimeout(()=>sheet.querySelector('input,select')?.focus(),0);}return;}
    const close=event.target instanceof Element?event.target.closest('[data-admin-filter-close]'):null;
    if(close){closeSheet(close.closest('[data-admin-filter-sheet]'));return;}
  });
  document.addEventListener('keydown',(event)=>{if(event.key==='Escape')document.querySelectorAll('[data-admin-filter-sheet].is-mobile-open').forEach(closeSheet);});
  window.addEventListener('resize',()=>{if(window.innerWidth>780)document.querySelectorAll('[data-admin-filter-sheet].is-mobile-open').forEach(closeSheet);},{passive:true});
})();

/* v4.2.9 Builder + Inventory UX: exact preview, on-demand products, row-level stock control. */
(()=>{
  'use strict';

  document.querySelectorAll('[data-homepage-product-picker]').forEach((picker)=>{
    if(!(picker instanceof HTMLElement)||picker.dataset.bound==='1')return;picker.dataset.bound='1';
    const search=picker.querySelector('[data-homepage-product-search]');
    const hidden=picker.querySelector('[data-homepage-product-id]');
    const results=picker.querySelector('[data-homepage-product-results]');
    const picked=picker.querySelector('[data-homepage-product-picked]');
    const pickedTitle=picker.querySelector('[data-homepage-product-picked-title]');
    const pickedMeta=picker.querySelector('[data-homepage-product-picked-meta]');
    const pickedMedia=picker.querySelector('.homepage-product-picked-media');
    const searchShell=picker.querySelector('[data-homepage-product-search-shell]');
    const clear=picker.querySelector('[data-homepage-product-clear]');
    if(!(search instanceof HTMLInputElement)||!(hidden instanceof HTMLInputElement)||!(results instanceof HTMLElement))return;
    let timer=0,controller=null;
    const hideResults=()=>{results.hidden=true;results.innerHTML='';};
    const showSearch=()=>{if(picked instanceof HTMLElement)picked.hidden=true;if(searchShell instanceof HTMLElement)searchShell.hidden=false;hidden.value='';search.value='';hideResults();search.focus();};
    const choose=(item)=>{
      hidden.value=String(item.id||'');search.value='';hideResults();
      if(pickedTitle)pickedTitle.textContent=[item.brand_name,item.name].filter(Boolean).join(' · ');
      if(pickedMeta)pickedMeta.textContent=[item.variants,`${Number.parseInt(item.stock_qty,10)||0} in stock`,item.sellable===false?'NOT CURRENTLY SELLABLE':''].filter(Boolean).join(' · ');
      if(pickedMedia instanceof HTMLElement){pickedMedia.innerHTML='';if(item.image_url){const img=document.createElement('img');img.src=item.image_url;img.alt='';pickedMedia.appendChild(img);}else{const span=document.createElement('span');span.textContent='PRODUCT';pickedMedia.appendChild(span);}}
      if(searchShell instanceof HTMLElement)searchShell.hidden=true;if(picked instanceof HTMLElement)picked.hidden=false;
      picker.closest('form')?.dispatchEvent(new Event('input',{bubbles:true}));
    };
    const render=(items)=>{
      results.innerHTML='';
      if(!items.length){const empty=document.createElement('div');empty.className='admin-async-empty';empty.textContent='No matching products.';results.appendChild(empty);results.hidden=false;return;}
      items.forEach((item)=>{
        const button=document.createElement('button');button.type='button';button.className='admin-async-result homepage-product-result';
        const media=document.createElement('span');media.className='homepage-product-result-media';if(item.image_url){const img=document.createElement('img');img.src=item.image_url;img.alt='';media.appendChild(img);}else media.textContent='PRODUCT';
        const copy=document.createElement('span');const title=document.createElement('strong');title.textContent=[item.brand_name,item.name].filter(Boolean).join(' · ');const meta=document.createElement('small');meta.textContent=[item.variants,`${Number.parseInt(item.stock_qty,10)||0} in stock`,item.sellable===false?'NOT CURRENTLY SELLABLE':''].filter(Boolean).join(' · ');copy.append(title,meta);
        button.append(media,copy);button.addEventListener('click',()=>choose(item));results.appendChild(button);
      });results.hidden=false;
    };
    search.addEventListener('input',()=>{
      hidden.value='';window.clearTimeout(timer);const q=search.value.trim();if(q.length<2){hideResults();return;}
      timer=window.setTimeout(async()=>{controller?.abort();controller=new AbortController();try{const url=new URL(search.dataset.endpoint||'',location.href);url.searchParams.set('q',q);const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal,cache:'no-store'});if(!response.ok)throw new Error('search failed');const data=await response.json();render(data?.ok&&Array.isArray(data.items)?data.items:[]);}catch(err){if(err?.name!=='AbortError'){results.innerHTML='';const error=document.createElement('div');error.className='admin-async-empty';error.textContent='Product search is temporarily unavailable. Try again.';results.appendChild(error);results.hidden=false;}}},180);
    });
    search.addEventListener('keydown',(event)=>{if(event.key==='Escape')hideResults();});
    clear?.addEventListener('click',showSearch);
    document.addEventListener('pointerdown',(event)=>{if(!picker.contains(event.target))hideResults();});
    picker.closest('form')?.addEventListener('submit',(event)=>{if(hidden.value)return;event.preventDefault();search.setCustomValidity('Choose a product from the search results.');if(searchShell instanceof HTMLElement)searchShell.hidden=false;if(picked instanceof HTMLElement)picked.hidden=true;search.reportValidity();search.focus();window.setTimeout(()=>search.setCustomValidity(''),0);});
  });

  if(document.querySelector('[data-inventory-standalone]'))return;
  const dialog=document.querySelector('[data-inventory-adjust-dialog]');
  const updateInventorySummary=(summary)=>{if(!summary)return;const keys=['active','healthy','low','out'];document.querySelectorAll('.inventory-metrics>a').forEach((card,index)=>{const key=keys[index];const strong=card.querySelector('strong');if(strong&&key in summary)strong.textContent=Number(summary[key]||0).toLocaleString();});};
  const updateInventoryVariant=(item)=>{if(!item)return;document.querySelectorAll(`[data-inventory-open-adjust][data-variant-id="${CSS.escape(String(item.id))}"]`).forEach((button)=>{button.dataset.stock=String(item.stock_qty);button.dataset.threshold=String(item.low_stock_threshold);});const row=document.querySelector(`[data-inventory-row][data-variant-id="${CSS.escape(String(item.id))}"]`);if(row){const stock=row.querySelector('[data-inventory-row-stock]');const threshold=row.querySelector('[data-inventory-row-threshold]');const state=row.querySelector('[data-inventory-row-state]');if(stock)stock.textContent=String(item.stock_qty);if(threshold)threshold.textContent=String(item.low_stock_threshold);if(state){state.textContent=item.state||'';state.className=`inventory-state is-${item.state_class||'muted'}`;}row.classList.toggle('has-problem',item.state_class==='out');row.classList.toggle('needs-attention',item.state_class==='low');}};
  const inventoryToast=(message,error=false)=>{let toast=document.querySelector('[data-admin-live-toast]');if(!(toast instanceof HTMLElement)){toast=document.createElement('div');toast.dataset.adminLiveToast='';toast.className='admin-live-toast';document.body.append(toast);}toast.textContent=message;toast.classList.toggle('is-error',error);toast.classList.add('is-visible');window.clearTimeout(Number(toast.dataset.timer||0));toast.dataset.timer=String(window.setTimeout(()=>toast.classList.remove('is-visible'),2600));};
  if(dialog instanceof HTMLDialogElement){
    const id=dialog.querySelector('[data-inventory-dialog-id]');const product=dialog.querySelector('[data-inventory-dialog-product]');const brand=dialog.querySelector('[data-inventory-dialog-brand]');const variant=dialog.querySelector('[data-inventory-dialog-variant]');const stock=dialog.querySelector('[data-inventory-dialog-stock]');const projected=dialog.querySelector('[data-inventory-dialog-projected]');const thresholdLabel=dialog.querySelector('[data-inventory-dialog-threshold-label]');const threshold=dialog.querySelector('[data-inventory-dialog-threshold]');const quantity=dialog.querySelector('[data-inventory-dialog-quantity]');const quantityLabel=dialog.querySelector('[data-inventory-quantity-label]');const quantityHint=dialog.querySelector('[data-inventory-quantity-hint]');const note=dialog.querySelector('[data-inventory-dialog-note]');const reasonHint=dialog.querySelector('[data-inventory-reason-hint]');const form=dialog.querySelector('[data-inventory-adjust-form]');const errorBox=dialog.querySelector('[data-inventory-dialog-error]');
    let currentStock=0;
    const showError=(message='')=>{if(!(errorBox instanceof HTMLElement))return;errorBox.textContent=message;errorBox.hidden=message==='';};
    const operation=()=>dialog.querySelector('input[name="stock_operation"]:checked')?.value||'receive';
    const refresh=()=>{const qty=Math.max(0,Number.parseInt(quantity instanceof HTMLInputElement?quantity.value:'0',10)||0);const op=operation();const negative=op==='correction_remove'||op==='damage';const next=op==='set_count'?qty:currentStock+(negative?-qty:qty);if(projected){projected.textContent=String(next);projected.classList.toggle('is-negative',next<0);}if(quantityLabel)quantityLabel.textContent=op==='set_count'?'Counted stock':'Quantity';if(quantityHint)quantityHint.textContent=op==='set_count'?'Enter the total bottles physically counted.':'Use 0 when only changing the alert threshold.';if(note instanceof HTMLInputElement){note.required=op==='set_count'?next!==currentStock:(qty>0&&op!=='receive');note.setAttribute('aria-required',note.required?'true':'false');}if(reasonHint)reasonHint.textContent=op==='receive'?'optional for supplier receiving':(op==='set_count'?'required when the physical count changes stock':'required for corrections / write-offs');if(quantity instanceof HTMLInputElement)quantity.setCustomValidity(next<0?'This change would make stock negative. Reduce the quantity.':'');};
    const fill=(item)=>{if(!item)return;if(id instanceof HTMLInputElement)id.value=String(item.id||'');if(product)product.textContent=item.product_name||'Product';if(brand)brand.textContent=item.brand_name||'';if(variant)variant.textContent=[item.label,item.sku].filter(Boolean).join(' · ');currentStock=Number.parseInt(item.stock_qty,10)||0;if(stock)stock.textContent=String(currentStock);if(thresholdLabel)thresholdLabel.textContent=String(item.low_stock_threshold||0);if(threshold instanceof HTMLInputElement)threshold.value=String(item.low_stock_threshold||0);if(quantity instanceof HTMLInputElement)quantity.value='1';const receive=dialog.querySelector('input[name="stock_operation"][value="receive"]');if(receive instanceof HTMLInputElement)receive.checked=true;if(note instanceof HTMLInputElement){note.value='';note.required=false;}refresh();};
    const open=async(button)=>{showError('');const provisional={id:button.dataset.variantId,product_name:button.dataset.product,brand_name:button.dataset.brand,label:button.dataset.label,sku:button.dataset.sku,stock_qty:button.dataset.stock,low_stock_threshold:button.dataset.threshold};fill(provisional);dialog.showModal();document.body.classList.add('ui-modal-open');const submit=form?.querySelector('button[type="submit"]');if(submit instanceof HTMLButtonElement){submit.disabled=true;submit.textContent='Loading live stock…';}try{const endpoint=new URL(form?.dataset.inventoryStateEndpoint||'',location.href);endpoint.searchParams.set('variant_id',button.dataset.variantId||'');const response=await fetch(endpoint.toString(),{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});const data=await response.json().catch(()=>null);if(!response.ok||!data?.ok)throw new Error(data?.message||'Could not load current stock.');fill(data.variant);updateInventoryVariant(data.variant);updateInventorySummary(data.summary);}catch(error){showError(error instanceof Error?error.message:'Could not load current stock.');}finally{if(submit instanceof HTMLButtonElement){submit.disabled=false;submit.textContent='Save inventory change';}window.setTimeout(()=>quantity instanceof HTMLInputElement&&quantity.focus(),40);}};
    document.addEventListener('click',(event)=>{const button=event.target.closest('[data-inventory-open-adjust]');if(button instanceof HTMLButtonElement)open(button);});
    dialog.querySelectorAll('input[name="stock_operation"]').forEach((radio)=>radio.addEventListener('change',()=>{if(quantity instanceof HTMLInputElement)quantity.value=operation()==='set_count'?String(currentStock):'1';refresh();showError('');}));quantity?.addEventListener('input',()=>{refresh();showError('');});
    const close=()=>{dialog.close();document.body.classList.remove('ui-modal-open');showError('');};dialog.querySelectorAll('[data-inventory-dialog-close]').forEach((button)=>button.addEventListener('click',close));dialog.addEventListener('cancel',()=>document.body.classList.remove('ui-modal-open'));dialog.addEventListener('click',(event)=>{if(event.target===dialog)close();});
    form?.addEventListener('submit',async(event)=>{event.preventDefault();showError('');if(!(quantity instanceof HTMLInputElement)||!(threshold instanceof HTMLInputElement))return;refresh();if(!quantity.checkValidity()){quantity.reportValidity();return;}const qty=Math.max(0,Number.parseInt(quantity.value,10)||0);const noStockChange=operation()==='set_count'?qty===currentStock:qty===0;if(noStockChange&&threshold.value===thresholdLabel?.textContent){quantity.setCustomValidity(operation()==='set_count'?'The counted stock already matches the saved stock.':'Enter a quantity or change the low-stock alert threshold.');quantity.reportValidity();window.setTimeout(()=>quantity.setCustomValidity(''),0);return;}const submit=form.querySelector('button[type="submit"]');if(submit instanceof HTMLButtonElement){submit.disabled=true;submit.textContent='Saving…';}form.setAttribute('aria-busy','true');try{const data=new FormData(form);data.set('_ajax','inventory');const response=await fetch(form.getAttribute('action')||'inventory.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:data});const payload=await response.json().catch(()=>null);if(!response.ok||!payload?.ok)throw new Error(payload?.message||`Inventory could not be updated (HTTP ${response.status}).`);(payload.variants||[]).forEach(updateInventoryVariant);updateInventorySummary(payload.summary);inventoryToast(payload.message||'Inventory updated.');close();}catch(error){showError(error instanceof Error?error.message:'Inventory could not be updated.');}finally{form.removeAttribute('aria-busy');if(submit instanceof HTMLButtonElement){submit.disabled=false;submit.textContent='Save inventory change';}}});
  }
  const bulkDialog=document.querySelector('[data-inventory-bulk-dialog]');
  if(bulkDialog instanceof HTMLDialogElement){const bulkForm=bulkDialog.querySelector('[data-inventory-bulk-form]');const error=bulkDialog.querySelector('[data-inventory-bulk-error]');const show=(message='')=>{if(error instanceof HTMLElement){error.textContent=message;error.hidden=message==='';}};const close=()=>{bulkDialog.close();document.body.classList.remove('ui-modal-open');show('');};document.addEventListener('click',(event)=>{if(event.target.closest('[data-inventory-open-bulk]')){bulkDialog.showModal();document.body.classList.add('ui-modal-open');bulkDialog.querySelector('input[name="bulk_value[]"]')?.focus();}});bulkDialog.querySelectorAll('[data-inventory-bulk-close]').forEach((button)=>button.addEventListener('click',close));bulkDialog.addEventListener('cancel',()=>document.body.classList.remove('ui-modal-open'));bulkForm?.addEventListener('submit',async(event)=>{event.preventDefault();show('');const submit=bulkForm.querySelector('button[type="submit"]');if(submit instanceof HTMLButtonElement){submit.disabled=true;submit.textContent='Saving…';}try{const data=new FormData(bulkForm);data.set('_ajax','inventory');const response=await fetch(bulkForm.getAttribute('action')||'inventory.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:data});const payload=await response.json().catch(()=>null);if(!response.ok||!payload?.ok)throw new Error(payload?.message||`Bulk inventory could not be saved (HTTP ${response.status}).`);(payload.variants||[]).forEach(updateInventoryVariant);updateInventorySummary(payload.summary);bulkForm.reset();inventoryToast(payload.message||'Bulk inventory updated.');close();}catch(err){show(err instanceof Error?err.message:'Bulk inventory could not be saved.');}finally{if(submit instanceof HTMLButtonElement){submit.disabled=false;submit.textContent='Save bulk inventory';}}});}
})();

/* Repair 8: editable storefront navigation/footer + builder preview viewport controls. */
(() => {
  'use strict';
  const targetOptions={home:'Home',catalog:'All perfumes',men:'Men',women:'Women',unisex:'Unisex',niche:'Niche',arabian:'Arabian perfumes',gifts:'Gifts',offers:'Offers',new:'New arrivals',bestselling:'Best sellers',wishlist:'Wishlist',account:'Account',support:'Support',shipping:'Shipping & delivery',returns:'Returns & refunds',faq:'FAQ',privacy:'Privacy',custom:'Custom URL'};

  const syncCustom=(row)=>{
    const select=row?.querySelector('[data-link-target]');
    const custom=row?.querySelector('.admin-link-custom');
    if(!(select instanceof HTMLSelectElement)||!(custom instanceof HTMLElement))return;
    custom.hidden=select.value!=='custom';
    const input=custom.querySelector('input'); if(input instanceof HTMLInputElement)input.required=select.value==='custom';
  };
  const createRow=(prefix)=>{
    const row=document.createElement('div'); row.className='admin-link-builder-row'; row.dataset.linkRow='';
    const drag=document.createElement('span');drag.className='admin-link-drag';drag.title='Reorder';drag.textContent='↕';row.append(drag);
    const label=document.createElement('label');label.className='app-field admin-field';label.innerHTML='<span>Label</span>';const labelInput=document.createElement('input');labelInput.name=`${prefix}_label[]`;labelInput.maxLength=50;labelInput.required=true;label.append(labelInput);row.append(label);
    const dest=document.createElement('label');dest.className='app-field admin-field';dest.innerHTML='<span>Destination</span>';const select=document.createElement('select');select.name=`${prefix}_target[]`;select.dataset.linkTarget='';Object.entries(targetOptions).forEach(([value,text])=>{const option=document.createElement('option');option.value=value;option.textContent=String(text);select.append(option);});dest.append(select);row.append(dest);
    const custom=document.createElement('label');custom.className='app-field admin-field admin-link-custom';custom.hidden=true;custom.innerHTML='<span>Custom URL</span>';const url=document.createElement('input');url.name=`${prefix}_url[]`;url.maxLength=500;url.placeholder='/page or https://...';custom.append(url);row.append(custom);
    const actions=document.createElement('div');actions.className='admin-link-row-actions';actions.innerHTML='<button class="app-button admin-button quiet small" type="button" data-link-up aria-label="Move up">↑</button><button class="app-button admin-button quiet small" type="button" data-link-down aria-label="Move down">↓</button><button class="app-button admin-button danger small" type="button" data-link-remove>Delete</button>';row.append(actions);
    select.addEventListener('change',()=>syncCustom(row));syncCustom(row);return row;
  };
  document.addEventListener('change',(event)=>{const select=event.target.closest('[data-link-target]');if(select)syncCustom(select.closest('[data-link-row]'));});
  document.addEventListener('click',(event)=>{
    const add=event.target.closest('[data-link-add]');if(add){const prefix=add.dataset.linkPrefix||'';const builder=document.querySelector(`[data-link-builder][data-link-prefix="${CSS.escape(prefix)}"]`);if(builder){const row=createRow(prefix);builder.append(row);row.querySelector('input')?.focus();}return;}
    const control=event.target.closest('[data-link-up],[data-link-down],[data-link-remove]');if(!control)return;const row=control.closest('[data-link-row]');const builder=row?.closest('[data-link-builder]');if(!row||!builder)return;
    if(control.matches('[data-link-remove]')){if((builder.dataset.linkPrefix||'')==='navigation'&&builder.querySelectorAll('[data-link-row]').length<=1){window.alert('Keep at least one header navigation link.');return;}row.remove();return;}
    if(control.matches('[data-link-up]')&&row.previousElementSibling)builder.insertBefore(row,row.previousElementSibling);
    if(control.matches('[data-link-down]')&&row.nextElementSibling)builder.insertBefore(row.nextElementSibling,row);
  });
  document.querySelectorAll('[data-link-row]').forEach(syncCustom);

  const previewDialog=document.querySelector('[data-builder-preview-dialog]');
  const previewFrame=document.querySelector('[data-builder-preview-frame]');
  const previewCanvas=document.querySelector('[data-builder-preview-canvas]');
  const previewViewport=document.querySelector('[data-builder-preview-viewport]');
  const previewSizes={desktop:{width:1440,height:900},tablet:{width:768,height:1024},mobile:{width:390,height:844}};
  let previewSize='desktop';
  const focusPreview=()=>{if(!(previewFrame instanceof HTMLIFrameElement))return;try{const doc=previewFrame.contentDocument;const hash=new URL(previewFrame.src,location.href).hash;if(hash){const target=doc?.querySelector(hash);target?.scrollIntoView({block:'start',behavior:'instant'});}}catch(_){} };
  const sizePreview=()=>{
    if(!(previewCanvas instanceof HTMLElement)||!(previewViewport instanceof HTMLElement)||!(previewFrame instanceof HTMLIFrameElement))return;
    const size=previewSizes[previewSize]||previewSizes.desktop;
    const available=Math.max(280,previewCanvas.clientWidth-40);
    const scale=Math.min(1,available/size.width);
    previewViewport.style.width=`${size.width}px`;
    previewViewport.style.height=`${size.height}px`;
    previewViewport.style.transform=`scale(${scale})`;
    previewViewport.style.transformOrigin='top center';
    previewFrame.style.width=`${size.width}px`;
    previewFrame.style.height=`${size.height}px`;
    previewCanvas.style.minHeight=`${Math.ceil(size.height*scale)+32}px`;
    previewCanvas.dataset.previewSize=previewSize;
  };
  const refreshPreview=()=>{if(!(previewFrame instanceof HTMLIFrameElement))return;try{const u=new URL(previewFrame.src,location.href);u.searchParams.set('_preview_refresh',String(Date.now()));previewFrame.src=u.toString();}catch(_){previewFrame.src=previewFrame.src;}};
  document.querySelectorAll('[data-builder-preview-open]').forEach((button)=>button.addEventListener('click',()=>{if(!(previewDialog instanceof HTMLDialogElement))return;previewDialog.showModal();document.body.classList.add('ui-modal-open');requestAnimationFrame(sizePreview);}));
  document.querySelectorAll('[data-builder-preview-close]').forEach((button)=>button.addEventListener('click',()=>{if(previewDialog instanceof HTMLDialogElement)previewDialog.close();document.body.classList.remove('ui-modal-open');}));
  previewDialog?.addEventListener('cancel',()=>document.body.classList.remove('ui-modal-open'));
  previewDialog?.addEventListener('click',(event)=>{if(event.target===previewDialog){previewDialog.close();document.body.classList.remove('ui-modal-open');}});
  document.querySelectorAll('[data-builder-preview-size]').forEach((button)=>button.addEventListener('click',()=>{previewSize=button.dataset.builderPreviewSize||'desktop';document.querySelectorAll('[data-builder-preview-size]').forEach((b)=>b.classList.toggle('is-active',b===button));sizePreview();}));
  document.querySelector('[data-builder-preview-refresh]')?.addEventListener('click',refreshPreview);
  previewFrame?.addEventListener('load',()=>window.setTimeout(()=>{focusPreview();sizePreview();},30));
  window.addEventListener('resize',()=>{if(previewDialog instanceof HTMLDialogElement&&previewDialog.open)sizePreview();});

  const syncFocalEditor=(editor)=>{
    if(!(editor instanceof HTMLElement))return;
    const x=editor.querySelector('[data-focal-x]'),y=editor.querySelector('[data-focal-y]'),point=editor.querySelector('[data-focal-point]'),image=editor.querySelector('[data-focal-image]'),empty=editor.querySelector('[data-focal-empty]'),zoom=editor.querySelector('[data-crop-zoom]'),zoomOut=editor.querySelector('[data-crop-zoom-output]');
    if(!(x instanceof HTMLInputElement)||!(y instanceof HTMLInputElement))return;
    const xv=Math.max(0,Math.min(100,Number(x.value)||50)),yv=Math.max(0,Math.min(100,Number(y.value)||50)),zv=zoom instanceof HTMLInputElement?Math.max(100,Math.min(220,Number(zoom.value)||100)):100;
    x.value=String(Math.round(xv));y.value=String(Math.round(yv));if(zoom instanceof HTMLInputElement)zoom.value=String(Math.round(zv));if(zoomOut)zoomOut.textContent=`${Math.round(zv)}%`;
    if(point instanceof HTMLElement){point.style.left=`${xv}%`;point.style.top=`${yv}%`;}
    if(image instanceof HTMLImageElement){image.style.objectPosition=`${xv}% ${yv}%`;image.style.transform=`scale(${zv/100})`;image.style.transformOrigin=`${xv}% ${yv}%`;}
    if(empty instanceof HTMLElement)empty.hidden=!!(image instanceof HTMLImageElement&&image.src);
  };
  const syncFocalSource=(editor)=>{
    if(!(editor instanceof HTMLElement))return;const source=editor.dataset.focalSource||'';const image=editor.querySelector('[data-focal-image]');if(!(image instanceof HTMLImageElement))return;
    const root=editor.closest('form')||document;
    const sourceImg=root.querySelector(`[data-homepage-live-preview="${CSS.escape(source)}"] img`);
    let src=sourceImg instanceof HTMLImageElement?(sourceImg.currentSrc||sourceImg.src||''):'';
    if(!src&&editor.dataset.focalFallbackSource){const fallback=root.querySelector(`[data-homepage-live-preview="${CSS.escape(editor.dataset.focalFallbackSource)}"] img`);src=fallback instanceof HTMLImageElement?(fallback.currentSrc||fallback.src||''):'';}
    image.src=src;image.hidden=src==='';syncFocalEditor(editor);
  };
  const initializeFocalEditors=(root=document)=>root.querySelectorAll('[data-focal-editor]').forEach((editor)=>{
    if(editor.dataset.focalBound==='1')return;editor.dataset.focalBound='1';
    syncFocalSource(editor);const canvas=editor.querySelector('[data-focal-canvas]');
    const setFromPointer=(event)=>{if(!(canvas instanceof HTMLElement))return;const rect=canvas.getBoundingClientRect();if(!rect.width||!rect.height)return;const x=editor.querySelector('[data-focal-x]'),y=editor.querySelector('[data-focal-y]');if(!(x instanceof HTMLInputElement)||!(y instanceof HTMLInputElement))return;x.value=String(Math.round(Math.max(0,Math.min(1,(event.clientX-rect.left)/rect.width))*100));y.value=String(Math.round(Math.max(0,Math.min(1,(event.clientY-rect.top)/rect.height))*100));syncFocalEditor(editor);};
    canvas?.addEventListener('pointerdown',(event)=>{event.preventDefault();setFromPointer(event);canvas.setPointerCapture?.(event.pointerId);});canvas?.addEventListener('pointermove',(event)=>{if(event.buttons===1)setFromPointer(event);});
    editor.querySelectorAll('[data-focal-x],[data-focal-y],[data-crop-zoom]').forEach(input=>input.addEventListener('input',()=>syncFocalEditor(editor)));
    editor.querySelectorAll('[data-crop-zoom-step]').forEach(button=>button.addEventListener('click',()=>{const zoom=editor.querySelector('[data-crop-zoom]');if(!(zoom instanceof HTMLInputElement))return;zoom.value=String(Math.max(100,Math.min(220,(Number(zoom.value)||100)+Number(button.dataset.cropZoomStep||0))));syncFocalEditor(editor);}));
    editor.querySelector('[data-crop-fit]')?.addEventListener('click',()=>{const zoom=editor.querySelector('[data-crop-zoom]');if(zoom instanceof HTMLInputElement)zoom.value='100';syncFocalEditor(editor);});
    editor.querySelector('[data-crop-fill]')?.addEventListener('click',()=>{const zoom=editor.querySelector('[data-crop-zoom]');if(zoom instanceof HTMLInputElement)zoom.value='125';syncFocalEditor(editor);});
    editor.querySelector('[data-crop-reset]')?.addEventListener('click',()=>{const x=editor.querySelector('[data-focal-x]'),y=editor.querySelector('[data-focal-y]'),zoom=editor.querySelector('[data-crop-zoom]');if(x instanceof HTMLInputElement)x.value='50';if(y instanceof HTMLInputElement)y.value='50';if(zoom instanceof HTMLInputElement)zoom.value='100';syncFocalEditor(editor);});
  });
  initializeFocalEditors();
  document.addEventListener('auraa:content-updated',(event)=>initializeFocalEditors(event.detail?.root instanceof Element?event.detail.root:document));
  document.addEventListener('homepage-media-preview-updated',()=>document.querySelectorAll('[data-focal-editor]').forEach(syncFocalSource));
})();

/* Repair 11 — Store Designer content builders + understandable popular-search chips. */
(()=>{
  'use strict';
  const esc=(value)=>String(value??'');
  const moveRow=(button,dir)=>{const row=button.closest('[data-page-block-row],[data-faq-row]');if(!row)return;const sibling=dir<0?row.previousElementSibling:row.nextElementSibling;if(!sibling)return;row.parentElement?.insertBefore(dir<0?row:sibling,dir<0?sibling:row);};
  document.addEventListener('click',e=>{
    const remove=e.target.closest('[data-block-remove]');if(remove){const row=remove.closest('[data-page-block-row],[data-faq-row]');if(row&&confirm('Remove this content section?'))row.remove();return;}
    const up=e.target.closest('[data-block-up]');if(up){moveRow(up,-1);return;}
    const down=e.target.closest('[data-block-down]');if(down){moveRow(down,1);return;}
    const add=e.target.closest('[data-page-block-add]');if(add){
      const prefix=add.dataset.pagePrefix||'';const builder=add.previousElementSibling;if(!(builder instanceof HTMLElement)||!prefix)return;
      const row=document.createElement('div');row.className='public-page-block-row';row.dataset.pageBlockRow='1';row.innerHTML=`<span class="admin-link-drag">↕</span><label class="app-field admin-field"><span>Section heading</span><input name="${esc(prefix)}_block_heading[]" maxlength="120"></label><label class="app-field admin-field"><span>Section content</span><textarea name="${esc(prefix)}_block_body[]" rows="3" maxlength="1800"></textarea></label><div class="admin-link-row-actions"><button class="app-button admin-button quiet small" type="button" data-block-up>↑</button><button class="app-button admin-button quiet small" type="button" data-block-down>↓</button><button class="app-button admin-button danger small" type="button" data-block-remove>Delete</button></div>`;builder.appendChild(row);row.querySelector('input')?.focus();return;
    }
    const faqAdd=e.target.closest('[data-faq-add]');if(faqAdd){
      const builder=faqAdd.previousElementSibling;if(!(builder instanceof HTMLElement))return;const row=document.createElement('div');row.className='public-page-block-row';row.dataset.faqRow='1';row.innerHTML='<span class="admin-link-drag">↕</span><label class="app-field admin-field"><span>Question</span><input name="faq_question[]" maxlength="180"></label><label class="app-field admin-field"><span>Answer</span><textarea name="faq_answer[]" rows="3" maxlength="1800"></textarea></label><div class="admin-link-row-actions"><button class="app-button admin-button quiet small" type="button" data-block-up>↑</button><button class="app-button admin-button quiet small" type="button" data-block-down>↓</button><button class="app-button admin-button danger small" type="button" data-block-remove>Delete</button></div>';builder.appendChild(row);row.querySelector('input')?.focus();return;
    }
  });
  const suggestionBuilder=document.querySelector('[data-search-suggestion-builder]');
  if(suggestionBuilder){
    const chips=suggestionBuilder.querySelector('[data-search-suggestion-chips]'),input=suggestionBuilder.querySelector('[data-search-suggestion-input]'),store=suggestionBuilder.querySelector('[data-search-suggestion-store]');
    const sync=()=>{if(!(store instanceof HTMLTextAreaElement)||!(chips instanceof HTMLElement))return;store.value=[...chips.querySelectorAll('[data-search-chip] b')].map(el=>el.textContent?.trim()||'').filter(Boolean).slice(0,8).join('\n');};
    const addValue=()=>{if(!(input instanceof HTMLInputElement)||!(chips instanceof HTMLElement))return;const value=input.value.trim();if(!value)return;if([...chips.querySelectorAll('b')].some(el=>(el.textContent||'').toLowerCase()===value.toLowerCase())){input.value='';return;}if(chips.querySelectorAll('[data-search-chip]').length>=8)return;const chip=document.createElement('span');chip.dataset.searchChip='1';const b=document.createElement('b');b.textContent=value;const button=document.createElement('button');button.type='button';button.setAttribute('aria-label','Remove suggestion');button.textContent='×';chip.append(b,button);chips.appendChild(chip);input.value='';sync();};
    suggestionBuilder.querySelector('[data-search-suggestion-add]')?.addEventListener('click',addValue);input?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();addValue();}});chips?.addEventListener('click',e=>{const button=e.target.closest('button');if(button){button.closest('[data-search-chip]')?.remove();sync();}});sync();
  }
})();

/* Repair 12: additional product sizes before the first Save. */
(()=>{'use strict';document.addEventListener('click',e=>{
 const add=e.target.closest('[data-add-product-variant]');if(add){const root=add.closest('[data-product-variant-batch]'),slot=root?.querySelector('[data-product-extra-variants]'),tmpl=root?.querySelector('[data-product-variant-template]');if(!slot||!tmpl||slot.children.length>=19)return;slot.append(tmpl.content.cloneNode(true));slot.lastElementChild?.querySelector('input')?.focus();return;}
 const remove=e.target.closest('[data-remove-product-variant]');if(remove)remove.closest('.product-variant-batch-row')?.remove();
});})();

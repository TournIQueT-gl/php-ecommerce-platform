(()=>{
  let uiLabels={};
  try{uiLabels=JSON.parse(document.getElementById('storefront-ui-translations')?.textContent||'{}');}catch(_e){}
  const tr=(key)=>typeof uiLabels[key]==='string'?uiLabels[key]:key;
  const body=document.body;
  const header=document.querySelector('[data-store-header]');
  const menu=document.querySelector('[data-store-nav]');
  const backdrop=document.querySelector('[data-store-menu-backdrop]');
  const openMenu=document.querySelector('[data-store-menu]');
  const closeMenu=document.querySelector('[data-store-menu-close]');
  const search=document.querySelector('[data-store-search]');
  const searchBackdrop=document.querySelector('[data-store-search-backdrop]');
  const searchOpeners=[...document.querySelectorAll('[data-store-search-open]')];
  const mobileSearchTab=document.querySelector('[data-mobile-search-tab]');
  const mobileNavMq=window.matchMedia('(max-width:900px)');
  // Desktop search is a popover anchored to the visible header search control.
  // Mobile/tablet keep the existing modal drawer behavior.
  const desktopSearchTrigger=document.querySelector('.store-desktop-search');
  const desktopSearchInput=document.querySelector('[data-desktop-search-input]');
  const positionDesktopSearch=()=>{
    if(!search||mobileNavMq.matches||!desktopSearchTrigger)return;
    const rect=desktopSearchTrigger.getBoundingClientRect();
    const margin=16;
    const width=Math.min(Math.max(rect.width,360),window.innerWidth-margin*2);
    const left=Math.max(margin,Math.min(rect.left-(width-rect.width)/2,window.innerWidth-width-margin));
    const top=Math.max(margin,rect.bottom+8);
    search.style.setProperty('--store-search-popover-top',`${Math.round(top)}px`);
    search.style.setProperty('--store-search-popover-left',`${Math.round(left)}px`);
    search.style.setProperty('--store-search-popover-width',`${Math.round(width)}px`);
  };
  let menuOpener=null,searchOpener=null,filterOpener=null;
  const focusables=(root)=>root?[...root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')].filter(el=>!el.hidden&&el.getAttribute('aria-hidden')!=='true'):[];
  const trapFocus=(root,e)=>{
    if(e.key!=='Tab'||!root)return;
    const items=focusables(root);if(!items.length)return;
    const first=items[0],last=items[items.length-1];
    if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
    else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
  };
  const bindStoreImageFallbacks=(root=document)=>{
    root.querySelectorAll('[data-store-image-fallback-source]').forEach(img=>{
      if(!(img instanceof HTMLImageElement)||img.dataset.fallbackBound==='1')return;
      img.dataset.fallbackBound='1';
      const fallback=img.nextElementSibling;
      const fail=()=>{img.hidden=true;if(fallback instanceof HTMLElement&&fallback.matches('[data-store-image-fallback]'))fallback.hidden=false;};
      img.addEventListener('error',fail,{once:true});
      if(img.complete&&img.naturalWidth===0)fail();
    });
  };
  bindStoreImageFallbacks();
  // Category/brand tab state is scoped to each menu; switching tabs does not
  // change stored navigation settings or produce duplicate top-level links.
  document.querySelectorAll('[data-nav-index-tabs]').forEach(tabs=>{
    const panel=tabs.parentElement;if(!panel)return;
    const activate=(kind)=>{
      tabs.querySelectorAll('[data-nav-index-tab]').forEach(button=>{
        const active=button.dataset.navIndexTab===kind;
        button.setAttribute('aria-selected',active?'true':'false');button.tabIndex=active?0:-1;
      });
      panel.querySelectorAll('[data-nav-index-panel]').forEach(el=>{el.hidden=el.dataset.navIndexPanel!==kind;});
    };
    tabs.querySelectorAll('[data-nav-index-tab]').forEach(button=>button.addEventListener('click',()=>activate(button.dataset.navIndexTab)));
  });
  const getSortMenu=()=>document.querySelector('[data-sort-menu]');
  const getSortToggle=()=>document.querySelector('[data-sort-toggle]');
  const setSort=(open)=>{
    const sortMenu=getSortMenu(),sortToggle=getSortToggle();
    if(!sortMenu)return;
    sortMenu.classList.toggle('is-open',open);
    sortToggle?.setAttribute('aria-expanded',open?'true':'false');
  };

  const setSearchExpanded=(open)=>searchOpeners.forEach(el=>el.setAttribute('aria-expanded',open?'true':'false'));
  if(search){search.inert=!search.classList.contains('is-open');search.setAttribute('aria-hidden',search.classList.contains('is-open')?'false':'true');}
  const syncResponsiveNav=()=>{
    if(!menu)return;
    if(mobileNavMq.matches){
      const open=menu.classList.contains('is-open');
      menu.setAttribute('aria-hidden',open?'false':'true');
      menu.inert=!open;
    }else{
      menu.inert=false;
      menu.classList.remove('is-open');
      menu.removeAttribute('aria-hidden');
      backdrop?.classList.remove('is-open');
      body.classList.remove('store-menu-open');
      openMenu?.setAttribute('aria-expanded','false');
      menuOpener=null;
      if(!search?.classList.contains('is-open'))body.style.overflow='';
    }
  };
  const footerGroups=[...document.querySelectorAll('.store-footer-group')];
  const syncFooter=()=>{footerGroups.forEach(el=>{el.open=!mobileNavMq.matches;});};
  syncFooter();
  if(typeof mobileNavMq.addEventListener==='function')mobileNavMq.addEventListener('change',syncFooter);
  else if(typeof mobileNavMq.addListener==='function')mobileNavMq.addListener(syncFooter);
  syncResponsiveNav();
  if(typeof mobileNavMq.addEventListener==='function')mobileNavMq.addEventListener('change',syncResponsiveNav);
  else if(typeof mobileNavMq.addListener==='function')mobileNavMq.addListener(syncResponsiveNav);

  const setMenu=(open)=>{
    if(!menu)return;
    if(!open)menu.querySelectorAll('details[open]').forEach(details=>{details.open=false;});
    open=!!open&&mobileNavMq.matches;
    if(open)menuOpener=document.activeElement;
    if(open && search){
      search.classList.remove('is-open');
      search.setAttribute('aria-hidden','true');
      search.inert=true;
      searchBackdrop?.classList.remove('is-open');
      mobileSearchTab?.classList.remove('is-active');
      setSearchExpanded(false);
      body.classList.remove('store-search-open');
      searchOpener=null;
    }
    menu.classList.toggle('is-open',open);
    if(mobileNavMq.matches){menu.setAttribute('aria-hidden',open?'false':'true');menu.inert=!open;}
    else{menu.removeAttribute('aria-hidden');menu.inert=false;}
    backdrop?.classList.toggle('is-open',open);
    body.classList.toggle('store-menu-open',open);
    body.style.overflow=open?'hidden':'';
    openMenu?.setAttribute('aria-expanded',open?'true':'false');
    if(open)setTimeout(()=>focusables(menu)[0]?.focus(),40);
    else if(menuOpener instanceof HTMLElement){const target=menuOpener;menuOpener=null;target.focus();}
  };

  const setSearch=(open,opener=null,restoreFocus=true)=>{
    if(!search)return;
    // Dismissing an open suggestion list must also stop its pending request:
    // otherwise a late network response could reopen a box the user closed.
    if(!open){window.clearTimeout(searchTimer);searchAbort?.abort();}
    if(open){
      const candidate=opener instanceof HTMLElement?opener:document.activeElement;
      searchOpener=candidate instanceof HTMLElement&&candidate.closest('[data-store-nav]')?(mobileSearchTab||openMenu):candidate;
    }
    if(open && menu){
      menu.classList.remove('is-open');
      if(mobileNavMq.matches){menu.setAttribute('aria-hidden','true');menu.inert=true;}else{menu.removeAttribute('aria-hidden');menu.inert=false;}
      backdrop?.classList.remove('is-open');
      body.classList.remove('store-menu-open');
      openMenu?.setAttribute('aria-expanded','false');
      menuOpener=null;
    }
    if(open){
      if(!mobileNavMq.matches && desktopSearchInput && searchInput)searchInput.value=desktopSearchInput.value;
      if(mobileNavMq.matches && searchInput && desktopSearchInput)searchInput.value=desktopSearchInput.value||searchInput.value;
      positionDesktopSearch();
    }
    search.classList.toggle('is-open',open);
    search.setAttribute('aria-hidden',open?'false':'true');
    // This is a non-modal search popover on desktop. On mobile/tablet it
    // remains a modal drawer with focus trapping and scroll lock.
    search.setAttribute('aria-modal',mobileNavMq.matches?'true':'false');
    search.inert=!open;
    searchBackdrop?.classList.toggle('is-open',open);
    mobileSearchTab?.classList.toggle('is-active',open);
    setSearchExpanded(open);
    body.classList.toggle('store-search-open',open);
    body.style.overflow=open&&mobileNavMq.matches?'hidden':'';
    if(open)setTimeout(()=> (mobileNavMq.matches?search.querySelector('[data-store-search-input]'):desktopSearchInput)?.focus(),40);
    else if(searchOpener instanceof HTMLElement){const target=searchOpener;searchOpener=null;if(restoreFocus)target.focus();}
  };
  // Sticky headers, different theme densities and browser zoom change the
  // trigger's screen position. Reposition without closing an open popover.
  const syncSearchPosition=()=>{if(search?.classList.contains('is-open')&&!mobileNavMq.matches)positionDesktopSearch();};
  window.addEventListener('scroll',syncSearchPosition,{passive:true});
  window.addEventListener('resize',syncSearchPosition,{passive:true});
  const onSearchModeChange=()=>{if(search?.classList.contains('is-open'))setSearch(false);};
  if(typeof mobileNavMq.addEventListener==='function')mobileNavMq.addEventListener('change',onSearchModeChange);
  else if(typeof mobileNavMq.addListener==='function')mobileNavMq.addListener(onSearchModeChange);

  document.addEventListener('click',e=>{
    const toggle=e.target.closest?.('[data-sort-toggle]');
    if(toggle){e.stopPropagation();setSort(!getSortMenu()?.classList.contains('is-open'));return;}
    const sortMenu=getSortMenu();
    if(sortMenu&&e.target instanceof Node&&!sortMenu.contains(e.target))setSort(false);
  });
  openMenu?.addEventListener('click',()=>{setSort(false);setMenu(true);});
  closeMenu?.addEventListener('click',()=>setMenu(false));
  backdrop?.addEventListener('click',()=>setMenu(false));
  // Clicking into the real desktop search input must NOT open an empty panel.
  // Desktop suggestions appear only after an actual query returns matches.
  searchOpeners.forEach(el=>el.addEventListener('click',()=>{
    if(!mobileNavMq.matches&&el===desktopSearchTrigger)return;
    setSort(false);setSearch(true,el);
  }));
  // The slash shown inside the desktop search control must be a real shortcut.
  document.addEventListener('keydown',e=>{
    if(e.key!=='/'||e.ctrlKey||e.metaKey||e.altKey||mobileNavMq.matches||e.defaultPrevented)return;
    if(e.target?.closest?.('input,textarea,select,[contenteditable=true]'))return;
    e.preventDefault();desktopSearchInput?.focus();
  });
  document.querySelector('[data-store-search-close]')?.addEventListener('click',()=>setSearch(false));
  searchBackdrop?.addEventListener('click',()=>setSearch(false));
  document.addEventListener('focusin',e=>{
    if(!mobileNavMq.matches&&search?.classList.contains('is-open')&&
       !search.contains(e.target)&&!searchOpeners.some(el=>el.contains(e.target))){
      setSearch(false,null,false);
    }
  });

  document.querySelectorAll('.store-mobile-tabs a').forEach(el=>el.addEventListener('click',()=>{
    setMenu(false);
    setSearch(false);
  }));

  document.addEventListener('keydown',e=>{
    if(e.key==='Tab'){
      if(menu?.classList.contains('is-open'))trapFocus(menu,e);
      else if(search?.classList.contains('is-open')&&mobileNavMq.matches)trapFocus(search,e);
      else if(typeof filterPanel!=='undefined'&&filterPanel?.classList.contains('is-open'))trapFocus(filterPanel,e);
    }
    if(e.key==='Escape'){
      if(search?.classList.contains('is-open'))e.preventDefault();
      setSort(false);
      setMenu(false);
      setSearch(false);
      if(typeof setFilter==='function')setFilter(false);
    }
  });

  const searchInput=document.querySelector('[data-store-search-input]');
  const searchLive=document.querySelector('[data-store-search-live]');
  const searchStatus=document.querySelector('[data-store-search-status]');
  let searchTimer=0,searchAbort=null;
  const renderSearchResults=(items,query)=>{
    if(!searchLive)return;
    searchLive.replaceChildren();
    if(!items.length){
      // No empty desktop popup: Enter still searches the full catalog.
      if(!mobileNavMq.matches){searchLive.hidden=true;setSearch(false,null,false);return;}
      searchLive.hidden=false;
      const empty=document.createElement('div');empty.className='store-search-empty';empty.textContent=`${tr('No matches for')} “${query}”. ${tr('Press Enter to search the full catalog.')}`;searchLive.appendChild(empty);
      if(searchStatus)searchStatus.textContent=tr('No instant matches. Press Enter for full results.');
      return;
    }
    const label=document.createElement('small');label.className='store-search-live-label';label.textContent=tr('QUICK RESULTS');searchLive.appendChild(label);
    items.forEach(item=>{
      const a=document.createElement('a');a.className='store-search-result';a.href=String(item.url||'#');
      if(item.image){const img=document.createElement('img');img.src=String(item.image);img.alt='';img.loading='lazy';a.appendChild(img);}else{const mark=document.createElement('span');mark.className='store-search-result-mark';mark.textContent=String(item.type||'').slice(0,1).toUpperCase();a.appendChild(mark);}
      const copy=document.createElement('span');const kind=document.createElement('small');kind.textContent=tr(String(item.type||'Result')).toUpperCase();const strong=document.createElement('strong');strong.textContent=String(item.label||'');copy.append(kind,strong);if(item.meta){const meta=document.createElement('em');meta.textContent=String(item.meta);copy.appendChild(meta);}a.appendChild(copy);searchLive.appendChild(a);
    });
    const form=searchInput?.closest('form');
    if(form){const all=document.createElement('a');all.className='store-search-view-all';const target=new URL(form.getAttribute('action')||location.href,location.href);target.searchParams.set('q',query);all.href=target.toString();all.textContent=`${tr('View all results for')} “${query}”`;searchLive.appendChild(all);}
    searchLive.hidden=false;
    if(searchStatus)searchStatus.textContent=`${items.length} ${tr(items.length===1?'Quick result available.':'quick results available.')}`;
  };
  const loadSearchSuggestions=()=>{
    if(!searchInput||!search)return;
    const query=(searchInput.value||'').trim();
    window.clearTimeout(searchTimer);searchAbort?.abort();
    if(query.length<3){
      if(searchLive){searchLive.hidden=true;searchLive.replaceChildren();}
      if(searchStatus)searchStatus.textContent='';
      if(!mobileNavMq.matches&&search.classList.contains('is-open'))setSearch(false,null,false);
      return;
    }
    searchTimer=window.setTimeout(async()=>{
      const endpoint=search.dataset.searchEndpoint;if(!endpoint)return;
      searchAbort=new AbortController();if(searchStatus)searchStatus.textContent=tr('Searching…');
      try{
        const url=new URL(endpoint,location.href);url.searchParams.set('q',query);
        const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal:searchAbort.signal});
        if(!response.ok)throw new Error('Search request failed');
        const data=await response.json();
        // Ignore obsolete responses when a newer query was typed or the user
        // dismissed suggestions during the request.
        const items=Array.isArray(data?.items)?data.items:[];
        if(!mobileNavMq.matches){
          if((desktopSearchInput?.value||'').trim()!==query)return;
          if(items.length){renderSearchResults(items,query);setSearch(true,desktopSearchInput);}
          else{renderSearchResults([],query);}
        }else{renderSearchResults(items,query);}
      }catch(err){if(err?.name==='AbortError')return;if(searchLive)searchLive.hidden=true;if(searchStatus)searchStatus.textContent=tr('Instant results are unavailable. Press Enter to search the catalog.');}
    },180);
  };
  searchInput?.addEventListener('input',()=>{if(mobileNavMq.matches||!desktopSearchInput)loadSearchSuggestions();});
  desktopSearchInput?.addEventListener('input',()=>{
    if(mobileNavMq.matches||!searchInput)return;
    searchInput.value=desktopSearchInput.value;
    // Type directly into the header. No panel on click, focus, or 1-2 chars.
    // Existing instant search still works after three characters.
    loadSearchSuggestions();
  });
  // Keyboard navigation works against whichever search field is currently visible.
  desktopSearchInput?.addEventListener('keydown',e=>{
    if(e.key==='ArrowDown'){
      const links=searchResultLinks();if(links.length){e.preventDefault();links[0].focus();}
    }
  });
  const searchResultLinks=()=>searchLive&&!searchLive.hidden?[...searchLive.querySelectorAll('a[href]')]:[];
  searchInput?.addEventListener('keydown',e=>{
    const links=searchResultLinks();
    if(e.key==='ArrowDown'&&links.length){e.preventDefault();links[0].focus();}
    else if(e.key==='ArrowUp'&&links.length){e.preventDefault();links[links.length-1].focus();}
  });
  searchLive?.addEventListener('keydown',e=>{
    const links=searchResultLinks();if(!links.length)return;
    const index=links.indexOf(document.activeElement);
    if(e.key==='ArrowDown'){
      e.preventDefault();links[Math.min(links.length-1,Math.max(0,index+1))]?.focus();
    }else if(e.key==='ArrowUp'){
      e.preventDefault();
      if(index<=0)searchInput?.focus();else links[index-1]?.focus();
    }else if(e.key==='Home'){
      e.preventDefault();links[0]?.focus();
    }else if(e.key==='End'){
      e.preventDefault();links[links.length-1]?.focus();
    }else if(e.key==='Escape'){
      e.preventDefault();e.stopPropagation();setSearch(false,null,false);
      (mobileNavMq.matches?searchInput:desktopSearchInput)?.focus();
    }
  });
  const filterPanel=document.querySelector('[data-catalog-filter]');
  const getFilterToggle=()=>document.querySelector('[data-filter-toggle]');
  const filterBackdrop=document.querySelector('[data-filter-backdrop]');
  const filterForm=document.querySelector('#catalog-filter-form');
  const getFilterApplies=()=>[...document.querySelectorAll('[data-filter-apply]')];
  const filterScroll=document.querySelector('[data-filter-scroll]');
  const catalogFilterMq=window.matchMedia('(max-width:900px)');
  const syncFilterAccessibility=()=>{
    if(!filterPanel)return;
    if(catalogFilterMq.matches){
      const open=filterPanel.classList.contains('is-open');
      filterPanel.setAttribute('aria-hidden',open?'false':'true');
      filterPanel.inert=!open;
    }else{
      filterPanel.inert=false;
      filterPanel.classList.remove('is-open');
      filterPanel.removeAttribute('aria-hidden');
      filterBackdrop?.classList.remove('is-open');
      getFilterToggle()?.setAttribute('aria-expanded','false');
      body.classList.remove('catalog-filter-open');
      document.documentElement.classList.remove('catalog-filter-open');
      if(!menu?.classList.contains('is-open')&&!search?.classList.contains('is-open'))body.style.overflow='';
      filterOpener=null;
    }
  };
  syncFilterAccessibility();
  if(typeof catalogFilterMq.addEventListener==='function')catalogFilterMq.addEventListener('change',syncFilterAccessibility);
  else if(typeof catalogFilterMq.addListener==='function')catalogFilterMq.addListener(syncFilterAccessibility);
  const setFilter=(open)=>{
    if(!filterPanel)return;
    const mobile=catalogFilterMq.matches;
    open=!!open&&mobile;
    if(open)filterOpener=document.activeElement;
    if(open){
      setSort(false);
      setMenu(false);
      setSearch(false);
    }
    filterPanel.classList.toggle('is-open',open);
    if(mobile){filterPanel.setAttribute('aria-hidden',open?'false':'true');filterPanel.inert=!open;}
    else{filterPanel.removeAttribute('aria-hidden');filterPanel.inert=false;}
    filterBackdrop?.classList.toggle('is-open',open);
    getFilterToggle()?.setAttribute('aria-expanded',open?'true':'false');
    body.classList.toggle('catalog-filter-open',open);
    document.documentElement.classList.toggle('catalog-filter-open',open);
    if(mobile){
      body.style.overflow=open?'hidden':'';
      if(open){
        if(filterScroll)filterScroll.scrollTop=0;
        setTimeout(()=>filterPanel.querySelector('[data-filter-close]')?.focus(),40);
      }
    }
    if(!open&&filterOpener instanceof HTMLElement){const target=filterOpener;filterOpener=null;target.focus();}
  };
  document.querySelector('[data-filter-close]')?.addEventListener('click',()=>setFilter(false));
  filterBackdrop?.addEventListener('click',()=>setFilter(false));
  const updateFilterApplyLabel=()=>{
    const form=document.querySelector('#catalog-filter-form');
    const filterApplies=getFilterApplies();
    if(!(form instanceof HTMLFormElement)||!filterApplies.length)return;
    const data=new FormData(form);
    let count=0;
    for(const [key,value] of data.entries()){
      if(['sort','q','collection'].includes(key))continue;
      if(String(value).trim()!=='' && String(value)!=='0')count++;
    }
    filterApplies.forEach(button=>{button.textContent=count>0?`${button.dataset.labelApply||tr('APPLY FILTERS')} · ${count}`:(button.dataset.labelShow||tr('SHOW ALL PRODUCTS'));});
  };

  let catalogAbort=null;
  const syncCatalogHead=(doc)=>{
    const currentHero=document.querySelector('.catalog-hero');
    const incomingHero=doc.querySelector('.catalog-hero');
    if(currentHero&&incomingHero)currentHero.innerHTML=incomingHero.innerHTML;
  };
  const syncCatalogFilter=(doc)=>{
    const currentForm=document.querySelector('#catalog-filter-form');
    const incomingForm=doc.querySelector('#catalog-filter-form');
    if(!(currentForm instanceof HTMLFormElement)||!(incomingForm instanceof HTMLFormElement))return;
    const currentScroll=currentForm.querySelector('[data-filter-scroll]');
    const incomingScroll=incomingForm.querySelector('[data-filter-scroll]');
    if(currentScroll&&incomingScroll)currentScroll.innerHTML=incomingScroll.innerHTML;
    ['.catalog-filter-actions-top','.catalog-filter-actions-bottom'].forEach(selector=>{
      const current=currentForm.querySelector(selector),incoming=incomingForm.querySelector(selector);
      if(current&&incoming)current.innerHTML=incoming.innerHTML;
    });
    ['q','sort','collection'].forEach(name=>{
      currentForm.querySelectorAll(`input[type="hidden"][name="${name}"]`).forEach(input=>input.remove());
      const incomingInput=incomingForm.querySelector(`input[type="hidden"][name="${name}"]`);
      if(incomingInput){
        const clone=document.importNode(incomingInput,true);
        currentForm.insertBefore(clone,currentForm.firstChild);
      }
    });
  };
  const syncCatalogMeta=(doc,url)=>{
    if(doc.title)document.title=doc.title;
    const incomingCanonical=doc.querySelector('link[rel="canonical"]');
    const currentCanonical=document.querySelector('link[rel="canonical"]');
    if(incomingCanonical&&currentCanonical)currentCanonical.setAttribute('href',incomingCanonical.getAttribute('href')||url);
    const incomingRobots=doc.querySelector('meta[name="robots"]');
    const currentRobots=document.querySelector('meta[name="robots"]');
    if(incomingRobots&&currentRobots)currentRobots.setAttribute('content',incomingRobots.getAttribute('content')||'index,follow');
  };
  const updateCatalog=async(url,{push=true,scroll=true}={})=>{
    if(!window.fetch){window.location.href=url;return;}
    const results=document.querySelector('.catalog-results');
    if(!results){window.location.href=url;return;}
    catalogAbort?.abort();
    catalogAbort=new AbortController();
    results.classList.add('is-updating');results.setAttribute('aria-busy','true');
    try{
      const response=await fetch(url,{credentials:'same-origin',headers:{'X-Requested-With':'PerfumeStore-Catalog'},signal:catalogAbort.signal});
      if(!response.ok)throw new Error('Catalog request failed');
      const html=await response.text();
      const doc=new DOMParser().parseFromString(html,'text/html');
      const incomingResults=doc.querySelector('.catalog-results');
      if(!incomingResults)throw new Error('Catalog response is incomplete');
      syncCatalogHead(doc);
      syncCatalogFilter(doc);
      // Reuse unchanged product-card DOM nodes so applying a filter does not
      // unnecessarily destroy/redecode every image that remains in the results.
      const reusable=new Map([...results.querySelectorAll('.product-card[data-product-id][data-product-card-key]')].map(card=>[
        `${card.getAttribute('data-product-id')}:${card.getAttribute('data-product-card-key')}`,card
      ]));
      const incomingClone=document.importNode(incomingResults,true);
      incomingClone.querySelectorAll('.product-card[data-product-id][data-product-card-key]').forEach(card=>{
        const key=`${card.getAttribute('data-product-id')}:${card.getAttribute('data-product-card-key')}`;
        const existing=reusable.get(key);
        if(!existing)return;
        const incomingReturn=card.querySelector('input[name="return_to"]')?.value;
        const existingReturn=existing.querySelector('input[name="return_to"]');
        if(existingReturn instanceof HTMLInputElement&&typeof incomingReturn==='string')existingReturn.value=incomingReturn;
        card.replaceWith(existing);
      });
      results.replaceChildren(...incomingClone.childNodes);
      syncCatalogMeta(doc,url);
      if(push)history.pushState({auraaCatalog:true},'',url);
      setSort(false);setFilter(false);updateFilterApplyLabel();
      document.dispatchEvent(new CustomEvent('auraa:catalog-updated',{detail:{root:results}}));
      if(scroll&&results.getBoundingClientRect().top<80)results.scrollIntoView({behavior:'smooth',block:'start'});
    }catch(err){
      if(err?.name==='AbortError')return;
      window.location.href=url;
    }finally{
      results.classList.remove('is-updating');results.removeAttribute('aria-busy');
    }
  };
  const catalogUrlFromForm=form=>{
    const action=form.getAttribute('action')||location.pathname;
    const url=new URL(action,location.href);
    const data=new FormData(form);
    data.forEach((value,key)=>{
      const text=String(value).trim();
      if(text!==''&&text!=='0')url.searchParams.append(key,text);
    });
    return url.toString();
  };

  document.addEventListener('click',e=>{
    const filterOpen=e.target.closest?.('[data-filter-toggle]');
    if(filterOpen){e.preventDefault();setFilter(true);return;}
    const sortLink=e.target.closest?.('[data-sort-popover] a');
    if(sortLink){e.preventDefault();setSort(false);updateCatalog(sortLink.href);return;}
    const catalogLink=e.target.closest?.('.catalog-filter-chips a,.catalog-results-head a,.catalog-pagination a,.catalog-filter-actions a');
    if(catalogLink&&catalogLink instanceof HTMLAnchorElement){
      e.preventDefault();if(catalogLink.closest('.catalog-filter-actions'))setFilter(false);updateCatalog(catalogLink.href);return;
    }
    const loadLink=e.target.closest?.('[data-load-more]');
    if(!(loadLink instanceof HTMLAnchorElement)||!window.fetch)return;
    e.preventDefault();
    const url=loadLink.getAttribute('href');
    const grid=document.querySelector('[data-product-grid]');
    if(!url||!grid||loadLink.classList.contains('is-loading'))return;
    loadLink.classList.add('is-loading');loadLink.setAttribute('aria-busy','true');
    const original=loadLink.querySelector('span')?.textContent||tr('LOAD MORE');
    if(loadLink.querySelector('span'))loadLink.querySelector('span').textContent=tr('LOADING…');
    fetch(url,{credentials:'same-origin',headers:{'X-Requested-With':'PerfumeStore-Catalog'}}).then(response=>{
      if(!response.ok)throw new Error('Catalog request failed');return response.text();
    }).then(html=>{
      const doc=new DOMParser().parseFromString(html,'text/html');
      const incoming=doc.querySelectorAll('[data-product-grid] .product-card');
      incoming.forEach(card=>grid.appendChild(document.importNode(card,true)));
      const next=doc.querySelector('[data-load-more]');
      const pagination=doc.querySelector('.catalog-pagination');
      const currentPagination=document.querySelector('.catalog-pagination');
      if(currentPagination){
        if(pagination)currentPagination.replaceWith(document.importNode(pagination,true));else currentPagination.remove();
      }
      const loaded=grid.querySelectorAll('.product-card').length;
      const total=Number(grid.getAttribute('data-total')||loaded);
      const progress=document.querySelector('[data-results-progress]');
      if(progress)progress.textContent=`${total.toLocaleString()} ${tr('results')} · ${tr('showing')} 1–${Math.min(loaded,total)}`;
      const progressLabel=document.querySelector('.catalog-paging-progress span');
      if(progressLabel)progressLabel.textContent=`${Math.min(loaded,total).toLocaleString()} ${tr('of')} ${total.toLocaleString()} ${tr('products')}`;
      const bar=document.querySelector('.catalog-paging-progress i');
      if(bar)bar.style.setProperty('--progress',`${Math.min(100,(loaded/Math.max(1,total))*100)}%`);
      if(next){
        loadLink.setAttribute('href',next.getAttribute('href')||url);
        loadLink.setAttribute('data-next-page',next.getAttribute('data-next-page')||'');
        if(loadLink.querySelector('span'))loadLink.querySelector('span').textContent=original;
        loadLink.classList.remove('is-loading');loadLink.removeAttribute('aria-busy');
      }else loadLink.remove();
      document.dispatchEvent(new CustomEvent('auraa:catalog-updated',{detail:{root:grid}}));
    }).catch(()=>{window.location.href=url;});
  });

  document.addEventListener('submit',e=>{
    const form=e.target;
    if(!(form instanceof HTMLFormElement)||form.id!=='catalog-filter-form')return;
    if(!window.fetch)return;
    e.preventDefault();setFilter(false);updateCatalog(catalogUrlFromForm(form));
  });
  document.addEventListener('change',e=>{
    if(e.target instanceof Element&&e.target.closest('#catalog-filter-form'))updateFilterApplyLabel();
  });
  document.addEventListener('input',e=>{
    const target=e.target;
    if(!(target instanceof HTMLInputElement))return;
    if(target.matches('[data-brand-filter-search]')){
      const q=target.value.trim().toLowerCase();
      document.querySelectorAll('[data-brand-option]').forEach(row=>{const label=(row.textContent||'').trim().toLowerCase();row.hidden=q!==''&&!label.includes(q);});
    }else if(target.matches('[data-note-filter-search]')){
      const q=target.value.trim().toLowerCase();
      document.querySelectorAll('[data-note-option]').forEach(option=>{const text=(option.textContent||'').toLowerCase();option.hidden=q!==''&&!text.includes(q);});
    }
  });
  window.addEventListener('popstate',()=>{
    if(document.querySelector('.catalog-results'))updateCatalog(location.href,{push:false,scroll:false});
  });

  // Delegation supports thumbnails replaced by SKU-specific media.
  document.addEventListener('click',event=>{
    const btn=event.target.closest('[data-gallery-thumb]');if(!btn)return;
    const src=btn.getAttribute('data-gallery-src');const main=document.querySelector('[data-gallery-main]');
    if(src&&main){main.src=src;main.removeAttribute('srcset');}
    document.querySelectorAll('[data-gallery-thumb]').forEach(x=>{x.classList.remove('is-active');x.setAttribute('aria-pressed','false');});
    btn.classList.add('is-active');btn.setAttribute('aria-pressed','true');
  });
  // Only URLs supplied by the server-side SKU map are used; never interpolate user text.
  const skuMediaSource=document.getElementById('alpha73-sku-media');
  if(skuMediaSource){
    let skuMedia={};try{skuMedia=JSON.parse(skuMediaSource.textContent||'{}');}catch{}
    const showSkuMedia=id=>{
      const photos=skuMedia[String(id)];
      if(!Array.isArray(photos)||!photos.length)return;
      const rail=document.querySelector('.product-thumbs');const main=document.querySelector('[data-gallery-main]');
      if(!rail||!main)return;
      rail.replaceChildren();
      photos.forEach((photo,i)=>{
        if(!photo || typeof photo.src!=='string' || typeof photo.thumb!=='string')return;
        const btn=document.createElement('button');btn.type='button';btn.className='product-thumb'+(i===0?' is-active':'');btn.setAttribute('data-gallery-thumb','');btn.dataset.gallerySrc=photo.src;btn.setAttribute('aria-pressed',i===0?'true':'false');
        const img=document.createElement('img');img.src=photo.thumb;img.alt='Product view '+(i+1);img.loading='lazy';btn.append(img);rail.append(btn);
      });
      main.src=photos[0].src;main.removeAttribute('srcset');main.hidden=false;const fallback=main.closest('.product-main-media')?.querySelector('[data-store-image-fallback]');if(fallback)fallback.hidden=true;
    };
    document.addEventListener('commerce:variant-selected',event=>showSkuMedia(event.detail?.id));
    // Legacy single-axis variants do not emit on initial page load.
    if(!document.getElementById('alpha6-variant-data'))showSkuMedia(document.querySelector('[data-selected-variant]')?.value||0);
  }

  document.querySelectorAll('[data-variant]').forEach(btn=>btn.addEventListener('click',()=>{
    document.querySelectorAll('[data-variant]').forEach(x=>{x.classList.remove('is-active');x.setAttribute('aria-pressed','false');});
    btn.classList.add('is-active');btn.setAttribute('aria-pressed','true');
    const price=btn.getAttribute('data-price'),target=document.querySelector('[data-product-price]'),hidden=document.querySelector('[data-selected-variant]');
    if(price&&target)target.textContent=price;
    const stickyPrice=document.querySelector('[data-sticky-product-price]');
    if(price&&stickyPrice)stickyPrice.textContent=price;
    const regular=document.querySelector('[data-product-regular]');
    const discount=document.querySelector('[data-product-discount]');
    if(regular){regular.textContent=btn.dataset.regular||'';regular.hidden=!btn.dataset.regular;}
    if(discount){const off=Number(btn.dataset.discount||0);discount.textContent=off?`${off}% OFF`:'';discount.hidden=!off;}
    if(hidden)hidden.value=btn.getAttribute('data-variant-id')||'0';
    document.dispatchEvent(new CustomEvent('commerce:variant-selected',{detail:{id:Number(btn.getAttribute('data-variant-id')||0)}}));
  }));
  document.querySelectorAll('[data-qty-minus]').forEach(btn=>btn.addEventListener('click',()=>{
    const input=btn.parentElement?.querySelector('[data-qty]');
    if(input){
      input.textContent=String(Math.max(1,Number(input.textContent||1)-1));
      const hidden=document.querySelector('[data-selected-qty]');
      if(hidden)hidden.value=input.textContent;
    }
  }));
  document.querySelectorAll('[data-qty-plus]').forEach(btn=>btn.addEventListener('click',()=>{
    const input=btn.parentElement?.querySelector('[data-qty]');
    if(input){
      input.textContent=String(Math.min(10,Number(input.textContent||1)+1));
      const hidden=document.querySelector('[data-selected-qty]');
      if(hidden)hidden.value=input.textContent;
    }
  }));

  // Persist quantity changes through the existing CSRF-protected cart endpoint. A single
  // in-flight request and a dirty flag prevent slower responses overwriting newer edits.
  const cartForm=document.querySelector('[data-cart-live-form]');
  if(cartForm && window.fetch){
    let timer=0,saving=false,dirty=false,failed=false;
    const status=document.querySelector('[data-cart-live-status]');
    const checkout=document.querySelector('.cart-checkout-button');
    const announce=(message,isError=false)=>{if(status){status.textContent=message;status.classList.toggle('is-error',isError);}};
    const blockCheckout=(blocked)=>{if(checkout){checkout.setAttribute('aria-disabled',blocked?'true':'false');checkout.classList.toggle('is-pending',blocked);}};
    checkout?.addEventListener('click',event=>{if(saving||dirty||failed){event.preventDefault();announce(tr('Please wait until your bag is saved.'),true);}});
    const save=async()=>{
      if(saving||!dirty)return;
      saving=true;dirty=false;blockCheckout(true);announce(tr('Saving bag…'));
      const url=new URL(cartForm.getAttribute('action')||'cart.php',document.baseURI);url.searchParams.set('_ajax','cart');
      try{
        const response=await fetch(url.toString(),{method:'POST',body:new FormData(cartForm),credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'PerfumeStore-Cart'}});
        const json=await response.json();
        if(!response.ok||json.ok!==true)throw new Error(String(json.message||tr('Could not update your bag.')));
        failed=false;
        for(const row of cartForm.querySelectorAll('[data-cart-line]')){
          const item=json.lines?.[row.dataset.cartLine];
          if(!item)continue;
          const total=row.querySelector('[data-cart-line-total]');if(total)total.textContent=String(item.total);
          // Do not replace input values while the customer is still making changes.
          if(!dirty){const input=row.querySelector('[data-cart-qty-input]');if(input)input.value=String(item.quantity);}
        }
        for(const selector of ['[data-cart-summary-subtotal]','[data-cart-summary-total]']){
          const node=document.querySelector(selector);if(node)node.textContent=String(json.subtotal_display||'');
        }
        const label=document.querySelector('[data-cart-count-label]');
        if(label){const count=Number(json.cart_count)||0;label.textContent=`${count} ${count===1?label.dataset.single:label.dataset.plural}`;}
        document.querySelectorAll('.store-cart-link b, .store-bag-count, .store-mobile-bag-tab b, .store-cart-count').forEach(node=>{node.textContent=String(json.cart_count||0);});
        announce(tr('Bag saved.'));
      }catch(error){failed=true;announce(String(error?.message||tr('Could not update your bag.')),true);}
      finally{
        saving=false;
        if(dirty){clearTimeout(timer);timer=setTimeout(save,180);}
        else blockCheckout(failed);
      }
    };
    cartForm.addEventListener('submit',event=>{if(saving){event.preventDefault();announce(tr('Saving bag…'));}});
    cartForm.addEventListener('change',event=>{
      if(!(event.target instanceof HTMLInputElement)||!event.target.matches('[data-cart-qty-input]'))return;
      event.target.value=String(Math.max(1,Math.min(10,Number(event.target.value)||1)));
      dirty=true;failed=false;blockCheckout(true);announce(tr('Saving bag…'));
      clearTimeout(timer);timer=setTimeout(save,200);
    });
    // Native Update Bag still works without JS and remains the recovery action.
  }

  document.querySelectorAll('[data-cart-qty-minus]').forEach(btn=>btn.addEventListener('click',()=>{
    const input=btn.parentElement?.querySelector('[data-cart-qty-input]');
    if(input instanceof HTMLInputElement){input.value=String(Math.max(1,Number(input.value||1)-1));input.dispatchEvent(new Event('change',{bubbles:true}));}
  }));
  document.querySelectorAll('[data-cart-qty-plus]').forEach(btn=>btn.addEventListener('click',()=>{
    const input=btn.parentElement?.querySelector('[data-cart-qty-input]');
    if(input instanceof HTMLInputElement){input.value=String(Math.min(10,Number(input.value||1)+1));input.dispatchEvent(new Event('change',{bubbles:true}));}
  }));

  const storefrontArabic=()=>document.documentElement.lang.toLowerCase().startsWith('ar');
  const storefrontLabel=(english,arabic)=>storefrontArabic()?arabic:english;
  const setWishlistButton=(form,saved)=>{
    const button=form.id==='wishlist-product-form'?document.querySelector(`[form="${form.id}"][data-wishlist-button]`):form.querySelector('[data-wishlist-button]');
    if(!button)return;
    button.classList.toggle('is-saved',saved);
    button.setAttribute('aria-pressed',saved?'true':'false');
    button.setAttribute('aria-label',saved?storefrontLabel('Remove from wishlist','إزالة من المفضلة'):storefrontLabel('Save to wishlist','أضف إلى المفضلة'));
  };
  const setWishlistProductState=(productId,saved,scope=document)=>{
    scope.querySelectorAll('[data-wishlist-form]').forEach(form=>{
      const id=Number(form.querySelector('[name=product_id]')?.value||0);
      if(id===Number(productId))setWishlistButton(form,saved);
    });
  };
  const wishlistToast=(message,error=false)=>{
    let toast=document.querySelector('[data-wishlist-toast]');
    if(!toast){toast=document.createElement('div');toast.dataset.wishlistToast='';toast.className='wishlist-toast';toast.setAttribute('role','status');toast.setAttribute('aria-live','polite');document.body.appendChild(toast);}
    toast.textContent=message;toast.classList.toggle('is-error',error);toast.classList.add('is-visible');
    window.clearTimeout(wishlistToast.timer);wishlistToast.timer=window.setTimeout(()=>toast.classList.remove('is-visible'),2200);
  };
  wishlistToast.timer=0;
  const syncWishlistState=async(root=document)=>{
    const forms=[...root.querySelectorAll('[data-wishlist-form]')];
    const ids=[...new Set(forms.map(f=>Number(f.querySelector('[name=product_id]')?.value||0)).filter(Boolean))];
    if(!ids.length)return;
    try{
      const endpoint=forms[0].getAttribute('action')||`${location.pathname.replace(/[^/]*$/,'')}wishlist`;
      const url=new URL(endpoint,location.href);url.searchParams.set('wishlist_state','1');url.searchParams.set('ids',ids.join(','));
      const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'PerfumeStore-Wishlist'}});
      if(!response.ok)return;
      const data=await response.json();if(!data?.authenticated)return;
      const saved=new Set((data.saved||[]).map(Number));forms.forEach(form=>setWishlistButton(form,saved.has(Number(form.querySelector('[name=product_id]')?.value||0))));
    }catch(_){/* No-JS form fallback remains available when JavaScript is absent. */}
  };
  document.addEventListener('submit',async e=>{
    const form=e.target.closest?.('[data-wishlist-form]');
    if(!(form instanceof HTMLFormElement)||!window.fetch)return;
    e.preventDefault();
    const button=form.id==='wishlist-product-form'?document.querySelector(`[form="${form.id}"][data-wishlist-button]`):form.querySelector('[data-wishlist-button]');
    if(button?.dataset.busy==='1')return;
    const productId=Number(form.querySelector('[name=product_id]')?.value||0);
    const wasSaved=button?.getAttribute('aria-pressed')==='true';
    const optimisticSaved=!wasSaved;
    if(button)button.dataset.busy='1';
    if(productId>0)setWishlistProductState(productId,optimisticSaved);
    try{
      const endpoint=form.getAttribute('action')||location.href;
      const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',redirect:'follow',headers:{Accept:'application/json','X-Requested-With':'PerfumeStore-Wishlist'},body:new FormData(form)});
      const contentType=(response.headers.get('content-type')||'').toLowerCase();
      if(response.redirected && /login(?:\.php)?(?:[?#]|$)/i.test(response.url)){window.location.href=response.url;return;}
      if(!contentType.includes('application/json')){
        if(productId>0)setWishlistProductState(productId,wasSaved);
        wishlistToast('Wishlist could not be updated. Please try again.',true);
        return;
      }
      let data=null;
      try{data=await response.json();}catch(_){
        if(productId>0)setWishlistProductState(productId,wasSaved);
        wishlistToast('Wishlist could not be updated. Please try again.',true);
        return;
      }
      if(response.status===401||data?.authenticated===false){
        if(productId>0)setWishlistProductState(productId,wasSaved);
        window.location.href=data?.login_url||`${location.pathname.replace(/[^/]*$/,'')}login.php`;return;
      }
      if(!response.ok||!data?.ok){
        if(productId>0)setWishlistProductState(productId,wasSaved);
        wishlistToast(data?.message|| (response.status===419?'Your session expired. Refresh the page and try again.':'Wishlist could not be updated.'),true);return;
      }
      if(productId>0)setWishlistProductState(productId,!!data.saved);
      wishlistToast(data.message|| (data.saved?'Saved to wishlist.':'Removed from wishlist.'));
    }catch(err){
      if(productId>0)setWishlistProductState(productId,wasSaved);
      wishlistToast(navigator.onLine===false?'You appear to be offline. Wishlist was not changed.':'Wishlist could not be updated. Please try again.',true);
    }finally{if(button)delete button.dataset.busy;}
  });
  syncWishlistState();
  document.addEventListener('auraa:catalog-updated',e=>{const root=e.detail?.root||document;bindStoreImageFallbacks(root);syncWishlistState(root);});

  const updateBagCount=(count)=>{
    count=Math.max(0,Number(count)||0);
    document.querySelectorAll('.store-cart-link,.store-mobile-bag-tab').forEach(link=>{
      const host=link.classList.contains('store-mobile-bag-tab')?(link.querySelector('.store-mobile-tab-icon')||link):link;
      let badge=host.querySelector('b');
      if(count<=0){badge?.remove();return;}
      if(!badge){badge=document.createElement('b');host.appendChild(badge);}
      badge.textContent=String(count);
    });
  };
  const cartToast=(message,error=false)=>wishlistToast(message,error);
  document.addEventListener('submit',async e=>{
    const form=e.target;
    if(!(form instanceof HTMLFormElement)||(!form.classList.contains('product-add-form')&&!form.classList.contains('product-purchase-form')))return;
    if(!window.fetch)return;
    e.preventDefault();
    const button=form.querySelector('button[type="submit"]');
    if(button instanceof HTMLButtonElement&&button.disabled)return;
    const detailAdd=form.classList.contains('product-purchase-form');
    const original=button instanceof HTMLButtonElement&&detailAdd?button.textContent:'';
    if(button instanceof HTMLButtonElement){button.disabled=true;button.setAttribute('aria-busy','true');if(detailAdd)button.textContent='ADDING…';}
    try{
      const endpoint=new URL(form.dataset.cartEndpoint||form.getAttribute('action')||window.location.href,window.location.href);endpoint.searchParams.set('_ajax','cart');
      const response=await fetch(endpoint.toString(),{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'PerfumeStore-Cart'},body:new FormData(form)});
      const raw=await response.text();let data=null;try{data=JSON.parse(raw);}catch(_){console.error('Bag endpoint returned non-JSON',response.status,raw.slice(0,500));}
      if(!response.ok||!data?.ok){cartToast(data?.message||`Could not add this item to your bag${response.status?` (HTTP ${response.status})`:''}. Refresh the page and try again.`,true);return;}
      updateBagCount(data.cart_count);cartToast(data.message||'Added to your bag.');
      if(button instanceof HTMLButtonElement&&detailAdd)button.textContent='ADDED ✓';
    }catch(_){cartToast(navigator.onLine===false?'You appear to be offline.':'Could not add this item to your bag.',true);}
    finally{if(button instanceof HTMLButtonElement){window.setTimeout(()=>{button.disabled=false;button.removeAttribute('aria-busy');if(detailAdd)button.textContent=original;},detailAdd?900:0);}}
  });
})();

/* v4.2.8 desktop carousel controls: hidden native scrollbar, arrows on desktop, swipe remains native on mobile. */
(()=>{
  'use strict';
  const initBuilderCarousels=(root=document)=>{
    root.querySelectorAll('.builder-layout-carousel .builder-grid').forEach(track=>{
      if(!(track instanceof HTMLElement)||track.dataset.carouselBound==='1')return;
      track.dataset.carouselBound='1';
      const nav=document.createElement('div');nav.className='builder-carousel-nav';nav.setAttribute('aria-label',(document.documentElement.lang==='ar'?'التحكم في عرض المنتجات':'Carousel controls'));
      const prev=document.createElement('button');prev.type='button';prev.setAttribute('aria-label',(document.documentElement.lang==='ar'?'العناصر السابقة':'Previous items'));prev.textContent='←';
      const next=document.createElement('button');next.type='button';next.setAttribute('aria-label',(document.documentElement.lang==='ar'?'العناصر التالية':'Next items'));next.textContent='→';
      nav.append(prev,next);track.parentElement?.insertBefore(nav,track);
      let cue=null,progress=null,count=null;
      const ensureMobileCue=()=>{
        if(window.innerWidth>780){cue?.remove();cue=null;progress=null;count=null;return;}
        if(cue)return;
        cue=document.createElement('div');cue.className='builder-mobile-carousel-cue';cue.innerHTML='<span></span><i><b></b></i><em>1 / 1</em>';cue.querySelector('span').textContent=document.documentElement.lang==='ar'?'اسحب للتصفح':'Swipe →';
        track.insertAdjacentElement('afterend',cue);progress=cue.querySelector('i>b');count=cue.querySelector('em');
        const touched=()=>cue?.classList.add('is-touched');track.addEventListener('pointerdown',touched,{once:true,passive:true});track.addEventListener('touchstart',touched,{once:true,passive:true});
      };
      const sync=()=>{const max=Math.max(0,track.scrollWidth-track.clientWidth);const atEnd=max<=3||track.scrollLeft>=max-4;prev.disabled=track.scrollLeft<=2;next.disabled=atEnd;nav.hidden=max<=2;ensureMobileCue();if(cue){cue.hidden=max<=2;const cards=[...track.children].filter(el=>el instanceof HTMLElement);const total=Math.max(1,cards.length);let current=1;if(atEnd)current=total;else{const viewportCenter=track.scrollLeft+(track.clientWidth/2);let best=Number.POSITIVE_INFINITY;cards.forEach((card,index)=>{const center=card.offsetLeft+(card.offsetWidth/2);const distance=Math.abs(center-viewportCenter);if(distance<best){best=distance;current=index+1;}});}if(count)count.textContent=`${current} / ${total}`;if(progress)progress.style.width=`${Math.max(12,Math.min(100,(current/total)*100))}%`;}};
      const move=(direction)=>{const amount=Math.max(260,track.clientWidth*.82);track.scrollBy({left:direction*amount,behavior:'smooth'});};
      prev.addEventListener('click',()=>move(-1));next.addEventListener('click',()=>move(1));track.addEventListener('scroll',()=>window.requestAnimationFrame(sync),{passive:true});window.addEventListener('resize',()=>window.requestAnimationFrame(sync),{passive:true});
      requestAnimationFrame(sync);
    });
  };
  initBuilderCarousels();
  document.addEventListener('auraa:catalog-updated',event=>initBuilderCarousels(event.detail?.root||document));
})();

/* v4.2.9 one-step checkout: quote totals in place, submit once. */
(()=>{
  'use strict';
  const form=document.querySelector('[data-live-checkout]');
  if(!(form instanceof HTMLFormElement))return;
  // Page translations are generated by PHP from the published storefront locale.
  let checkoutLabels={};
  try{checkoutLabels=JSON.parse(document.getElementById('storefront-ui-translations')?.textContent||'{}');}catch(_error){}
  const tx=(key)=>typeof checkoutLabels[key]==='string'?checkoutLabels[key]:key;
  const endpoint=form.dataset.quoteUrl||'';
  const summary=form.querySelector('[data-checkout-summary]')||document;
  const subtotalEl=summary.querySelector('[data-checkout-subtotal]');
  const shippingEl=summary.querySelector('[data-checkout-shipping]');
  const shippingNote=summary.querySelector('[data-checkout-shipping-note]');
  const discountRow=summary.querySelector('[data-checkout-discount-row]');
  const discountEl=summary.querySelector('[data-checkout-discount]');
  const couponEl=summary.querySelector('[data-checkout-coupon]');
  const deliveryNote=summary.querySelector('[data-checkout-delivery-note]');
  const zoneEl=summary.querySelector('[data-checkout-zone]');
  const daysEl=summary.querySelector('[data-checkout-days]');
  const paymentEl=summary.querySelector('[data-checkout-payment]');
  const totalEl=summary.querySelector('[data-checkout-total]');
  const statusEl=summary.querySelector('[data-checkout-quote-status]');
  const legalEl=summary.querySelector('[data-checkout-payment-legal]');
  const placeButton=summary.querySelector('[data-checkout-place]');
  let timer=0,controller=null,quotedFingerprint='';
  const money=(value,currency)=>{try{return new Intl.NumberFormat(undefined,{maximumFractionDigits:2}).format(Number(value)||0)+' '+(currency||'EGP');}catch(_){return `${Number(value||0).toFixed(2)} ${currency||'EGP'}`;}};
  const value=(name)=>String(form.elements.namedItem(name)?.value||'').trim();
  const ready=()=>['customer_name','phone','governorate','city','address'].every(name=>value(name)!=='');
  const formFingerprint=()=>{const data=new FormData(form);data.delete('checkout_action');return [...data.entries()].map(([k,v])=>`${k}:${typeof v==='string'?v:''}`).join('|');};
  const setPlaceReady=(enabled)=>{if(placeButton instanceof HTMLButtonElement)placeButton.disabled=!enabled;};
  const paymentCopy=()=>{
    const method=value('payment_method')||'cod';
    if(paymentEl)paymentEl.textContent=method==='manual_transfer'?tx('Bank transfer / InstaPay'):tx('Cash on Delivery');
    if(legalEl)legalEl.textContent=method==='manual_transfer'?tx('No payment is taken online. We will contact you with Bank transfer / InstaPay details.'):tx('Payment is collected on delivery.');
    form.querySelectorAll('.checkout-payment-option').forEach(label=>label.classList.toggle('is-selected',label.querySelector('input')?.checked===true));
  };
  const resetQuote=(message=tx('Complete delivery details to calculate your exact total.'))=>{
    quotedFingerprint='';
    if(shippingEl)shippingEl.textContent='—';if(totalEl)totalEl.textContent=tx('Complete delivery details');
    if(discountRow instanceof HTMLElement)discountRow.hidden=true;if(deliveryNote instanceof HTMLElement)deliveryNote.hidden=true;
    if(statusEl)statusEl.textContent=message;if(placeButton instanceof HTMLButtonElement)placeButton.textContent=tx('PLACE ORDER');setPlaceReady(false);
  };
  const applyQuote=(q)=>{
    const currency=String(q.currency||'EGP');
    if(subtotalEl)subtotalEl.textContent=money(q.subtotal,currency);
    if(shippingEl)shippingEl.textContent=Number(q.shipping_total||0)<=0?tx('FREE'):money(q.shipping_total,currency);
    if(shippingNote){const standard=Number(q.shipping?.standard_fee||0);shippingNote.textContent=q.shipping?.free_shipping_applied&&standard>0?`${tx('Standard')} ${money(standard,currency)} · ${tx('free shipping unlocked')}`:'';}
    const discount=Number(q.discount_total||0);if(discountRow instanceof HTMLElement)discountRow.hidden=discount<=0;if(discountEl)discountEl.textContent=discount>0?`−${money(discount,currency)}`:'';if(couponEl)couponEl.textContent=q.coupon_code?`· ${q.coupon_code}`:'';
    if(deliveryNote instanceof HTMLElement)deliveryNote.hidden=false;if(zoneEl)zoneEl.textContent=String(q.shipping?.zone_name||'');
    const min=q.shipping?.days_min,max=q.shipping?.days_max;if(daysEl)daysEl.textContent=min!==null&&min!==undefined?`${tx('Estimated')} ${min}${max!==null&&max!==undefined&&max!==min?`–${max}`:''} ${tx('days')}`:'';
    if(totalEl)totalEl.textContent=money(q.grand_total,currency);
    if(placeButton instanceof HTMLButtonElement)placeButton.textContent=`${tx('PLACE ORDER')} · ${money(q.grand_total,currency)}`;
    if(statusEl)statusEl.textContent=tx('Total updated. Price, stock and delivery will be verified again when you place the order.');
  };
  const requestQuote=async()=>{
    paymentCopy();
    if(!ready()){resetQuote();return;}
    const data=new FormData(form);data.delete('checkout_action');
    const fingerprint=formFingerprint();
    if(fingerprint===quotedFingerprint){setPlaceReady(true);return;}
    controller?.abort();controller=new AbortController();setPlaceReady(false);if(statusEl)statusEl.textContent=tx('Updating total…');
    try{
      const response=await fetch(endpoint,{method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store',signal:controller.signal});
      const payload=await response.json().catch(()=>null);
      if(!response.ok||!payload?.ok){quotedFingerprint='';if(statusEl)statusEl.textContent=payload?.error||tx('Check your delivery details.');return;}
      if(formFingerprint()!==fingerprint)return;
      quotedFingerprint=fingerprint;applyQuote(payload.quote||{});setPlaceReady(true);
    }catch(error){if(error?.name!=='AbortError'){quotedFingerprint='';setPlaceReady(false);if(statusEl)statusEl.textContent=tx('Could not refresh the total. Check your connection and try again before placing the order.');}}
  };
  const schedule=()=>{quotedFingerprint='';setPlaceReady(false);window.clearTimeout(timer);timer=window.setTimeout(requestQuote,320);};
  form.addEventListener('input',schedule);form.addEventListener('change',schedule);
  const addressChoice=form.querySelector('[data-saved-checkout-address]');
  const saveAddress=form.querySelector('[data-checkout-save-address]');
  const addressLabel=form.querySelector('[data-checkout-address-label]');
  const syncSaveAddress=()=>{
    if(!(saveAddress instanceof HTMLInputElement))return;
    const usingSaved=addressChoice instanceof HTMLSelectElement && addressChoice.value!=='';
    if(usingSaved){saveAddress.checked=false;saveAddress.disabled=true;}else saveAddress.disabled=false;
    const saveBox=saveAddress.closest('.checkout-address-save');
    if(saveBox instanceof HTMLElement)saveBox.hidden=usingSaved;
    if(addressLabel instanceof HTMLElement)addressLabel.hidden=!saveAddress.checked||usingSaved;
  };
  saveAddress?.addEventListener('change',syncSaveAddress);
  if(addressChoice instanceof HTMLSelectElement){
    addressChoice.addEventListener('change',()=>{
      const selected=addressChoice.selectedOptions[0];
      syncSaveAddress();
      if(!selected)return;
      if(!addressChoice.value){
        ['governorate','city','address','building'].forEach((key)=>{const field=form.elements.namedItem(key);if(field)field.value='';});
        form.dispatchEvent(new Event('change',{bubbles:true}));
        return;
      }
      for(const key of ['customer_name','phone','governorate','city','address','building']){
        const field=form.elements.namedItem(key);
        if(!field)continue;
        field.value=selected.dataset[key.replace(/_([a-z])/g,(_,letter)=>letter.toUpperCase())]||'';
      }
      form.dispatchEvent(new Event('change',{bubbles:true}));
    });
  }

  form.addEventListener('submit',(event)=>{
    if(formFingerprint()!==quotedFingerprint){event.preventDefault();requestQuote();return;}
    if(placeButton instanceof HTMLButtonElement){placeButton.disabled=true;placeButton.textContent=tx('PLACING ORDER…');}
  });
  syncSaveAddress();paymentCopy();resetQuote();schedule();
})();

/* Repair 15: continuous, seamless, CSS-driven brand and note marquees. */
(()=>{'use strict';
  const reduced=window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches||false;
  document.querySelectorAll('.builder-layout-marquee .builder-grid').forEach((viewport)=>{
    if(viewport.dataset.marqueeReady==='1')return;
    const children=Array.from(viewport.children);
    if(children.length<2)return;
    viewport.dataset.marqueeReady='1';
    viewport.setAttribute('aria-label',(document.documentElement.lang==='ar'?'مجموعة تمر تلقائياً؛ اضغط مطولاً للإيقاف':'Continuously scrolling collection; press and hold to pause.'));
    viewport.addEventListener('pointerdown',()=>viewport.classList.add('is-touch-holding'),{passive:true});
    ['pointerup','pointercancel','pointerleave'].forEach(event=>viewport.addEventListener(event,()=>viewport.classList.remove('is-touch-holding'),{passive:true}));
    const track=document.createElement('div'); track.className='builder-marquee-track';
    const first=document.createElement('div'); first.className='builder-marquee-group';
    children.forEach(item=>first.appendChild(item));
    const second=first.cloneNode(true); second.setAttribute('aria-hidden','true');
    second.querySelectorAll('[id]').forEach(node=>node.removeAttribute('id'));
    second.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(node=>node.setAttribute('tabindex','-1'));
    track.append(first,second);viewport.append(track);
    const setDuration=()=>{
      const width=Math.max(first.getBoundingClientRect().width,200);
      // Roughly 76px per second: obvious movement without rushing the logos.
      viewport.style.setProperty('--marquee-duration',`${Math.max(8,width/76).toFixed(2)}s`);
    };
    setDuration();
    if('ResizeObserver' in window){new ResizeObserver(setDuration).observe(first)}
    else window.addEventListener('resize',setDuration,{passive:true});
    viewport.classList.toggle('is-motion-reduced',reduced);
    // Reduced motion remains accessible as a manually scrollable horizontal strip.
    if(reduced)viewport.setAttribute('aria-label',(document.documentElement.lang==='ar'?'مجموعة أفقية، مرر للتصفح':'Horizontal collection. Scroll to browse.'));
  });
})();

/* Motion safety: video respects the operating-system reduced-motion preference. */
(()=>{'use strict';if(!matchMedia('(prefers-reduced-motion: reduce)').matches)return;document.querySelectorAll('.builder-video-section video[autoplay]').forEach(video=>{video.autoplay=false;video.pause();video.removeAttribute('autoplay');});})();

/* Images on external hosts may fail. Replace empty circles with the note name. */
(()=>{'use strict';
 document.querySelectorAll('.notes-section .note-photo').forEach(img=>{
  const fallback=()=>img.closest('.note-orb')?.classList.add('image-missing');
  img.addEventListener('error',fallback,{once:true});
  if(img.complete&&img.naturalWidth===0)fallback();
 });
})();

/* Alpha 6: independently selected attribute axes always resolve to an actual SKU.
   The server remains authoritative for availability and pricing at checkout. */
(()=>{'use strict';
  const source=document.getElementById('alpha6-variant-data');
  if(!source)return;
  let variants=[];
  try{variants=JSON.parse(source.textContent||'[]');}catch{return;}
  if(!Array.isArray(variants)||!variants.length)return;
  const root=document.querySelector('[data-variant-axes]');
  const hidden=document.querySelector('[data-selected-variant]');
  const buy=document.querySelector('.product-buy-button');
  const price=document.querySelector('[data-product-price]');
  const sticky=document.querySelector('[data-sticky-product-price]');
  const regular=document.querySelector('[data-product-regular]');
  const discount=document.querySelector('[data-product-discount]');
  const status=document.querySelector('[data-selection-status]');
  if(!root||!hidden||!buy)return;
  const buttons=Array.from(root.querySelectorAll('[data-axis-choice]'));
  const axisNames=Array.from(new Set(buttons.map(b=>b.dataset.axisChoice)));
  let selected={};
  const firstAvailable=variants.find(v=>v.available)||variants[0];
  if(firstAvailable&&firstAvailable.options)selected={...firstAvailable.options};
  const match=(v,chosen)=>axisNames.every(axis=>v.options&&v.options[axis]===chosen[axis]);
  const update=()=>{
    buttons.forEach(b=>{
      const axis=b.dataset.axisChoice,code=b.dataset.optionCode;
      const pressed=selected[axis]===code;
      b.classList.toggle('is-active',pressed);
      b.setAttribute('aria-pressed',pressed?'true':'false');
      // Only disable an option if no stocked combination matches all other selected axes.
      const wouldBe={...selected,[axis]:code};
      const possible=variants.some(v=>v.available&&match(v,wouldBe));
      b.classList.toggle('is-unavailable',!possible);
      b.setAttribute('aria-label',(b.textContent||'').trim()+(!possible?' – unavailable in this combination':''));
    });
    const chosen=variants.find(v=>match(v,selected));
    const available=!!(chosen&&chosen.available);
    hidden.value=available?String(chosen.id):'0';
    buy.disabled=!available;
    buy.textContent=available?'ADD TO BAG':'UNAVAILABLE';
    if(chosen){
      if(price)price.textContent=chosen.price||'';
      if(sticky)sticky.textContent=chosen.price||'';
      if(regular){regular.textContent=chosen.regular||'';regular.hidden=!chosen.regular;}
      if(discount){discount.textContent=chosen.discount?`${chosen.discount}% OFF`:'';discount.hidden=!chosen.discount;}
    }
    if(status)status.textContent=available?'Available':(chosen?'This combination is out of stock':'Select an available combination');
    document.dispatchEvent(new CustomEvent('commerce:variant-selected',{detail:{id:chosen?chosen.id:0}}));
  };
  buttons.forEach(button=>button.addEventListener('click',()=>{
    selected[button.dataset.axisChoice]=button.dataset.optionCode;
    update();
  }));
  update();
})();

/* One predictable controller for the desktop retail navigation. A panel stays
   invisible until physical viewport coordinates are calculated synchronously.
   Mobile keeps native <details> accordions for touch and keyboard access. */
(()=>{'use strict';
  const menus=[...document.querySelectorAll('[data-shop-departments],[data-shop-audience],[data-shop-more]')];
  if(!menus.length)return;
  const desktop=window.matchMedia('(min-width:901px)');
  const clamp=(v,lo,hi)=>Math.min(Math.max(lo,v),hi);
  const locate=(menu)=>{
    if(!desktop.matches||!menu.open)return;
    const trigger=menu.querySelector('summary');
    const panel=menu.querySelector('.modern-nav-panel');
    if(!trigger||!panel)return;
    const rect=trigger.getBoundingClientRect();
    // visibility:hidden still reserves geometry, so measure before showing.
    const width=Math.min(panel.getBoundingClientRect().width||440,window.innerWidth-32);
    const panelHeight=panel.getBoundingClientRect().height;
    const x=clamp(rect.left+rect.width/2-width/2,16,Math.max(16,window.innerWidth-width-16));
    const below=rect.bottom+6;
    const y=(below+Math.min(panelHeight,innerHeight*.65)>innerHeight-12&&rect.top>Math.min(panelHeight,innerHeight*.65))
      ?Math.max(12,rect.top-Math.min(panelHeight,innerHeight*.65)-6):below;
    panel.style.setProperty('--nav-popover-x',`${Math.round(x)}px`);
    panel.style.setProperty('--nav-popover-y',`${Math.round(y)}px`);
    menu.dataset.navReady='1';
  };
  const close=(menu,returnFocus=false)=>{
    clearTimeout(menu._navEnterTimer);clearTimeout(menu._navExitTimer);
    menu.dataset.navReady='0';menu.open=false;
    if(returnFocus&&desktop.matches)menu.querySelector('summary')?.focus();
  };
  const open=(menu)=>{
    if(!desktop.matches)return;
    menus.forEach(other=>{if(other!==menu)close(other)});
    menu.dataset.navReady='0';
    menu.open=true;locate(menu);
  };
  menus.forEach(menu=>{
    const summary=menu.querySelector('summary');
    summary?.addEventListener('click',e=>{
      if(!desktop.matches)return;
      e.preventDefault();
      if(menu.open)close(menu);else open(menu);
    });
    menu.addEventListener('pointerenter',e=>{
      if(!desktop.matches||e.pointerType==='touch')return;
      clearTimeout(menu._navExitTimer);
      if(!menu.open)menu._navEnterTimer=setTimeout(()=>open(menu),350);
    });
    menu.addEventListener('pointerleave',e=>{
      if(!desktop.matches||e.pointerType==='touch')return;
      clearTimeout(menu._navEnterTimer);
      menu._navExitTimer=setTimeout(()=>{if(menu.open)close(menu)},260);
    });
    menu.addEventListener('toggle',()=>{
      if(!desktop.matches)return;
      if(menu.open){menus.forEach(other=>{if(other!==menu)close(other)});locate(menu)}
      else menu.dataset.navReady='0';
    });
  });
  document.addEventListener('pointerdown',e=>{
    if(menus.some(menu=>menu.contains(e.target)))return;
    menus.forEach(menu=>close(menu));
  });
  document.addEventListener('keydown',e=>{
    if(e.key!=='Escape')return;
    const openMenu=menus.find(menu=>menu.open);
    if(!openMenu)return;
    close(openMenu,true);e.stopPropagation();
  },true);
  const reposition=()=>menus.forEach(menu=>{
    if(menu.open&&desktop.matches){menu.dataset.navReady='0';locate(menu)}
  });
  window.addEventListener('resize',reposition,{passive:true});
  window.addEventListener('scroll',reposition,{passive:true});
  const reset=()=>menus.forEach(menu=>close(menu));
  if(desktop.addEventListener)desktop.addEventListener('change',reset);
  else if(desktop.addListener)desktop.addListener(reset);
})();

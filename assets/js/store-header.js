/* Storefront header: stable hysteresis prevents scroll-anchor resize loops. */
(()=>{'use strict';
 const header=document.querySelector('[data-store-header]');if(!header)return;
 const root=document.documentElement;
 // Different entry/exit thresholds leave enough room for the header height
 // to change without repeatedly crossing the same scroll position.
 const COLLAPSE_AT=150, EXPAND_AT=55;
 const position=()=>Math.max(0,window.scrollY||root.scrollTop||0);
 let compact=position()>=COLLAPSE_AT;
 let queued=false;
 const sync=()=>{
   queued=false;
   const y=position();
   if(!compact&&y>=COLLAPSE_AT)compact=true;
   else if(compact&&y<=EXPAND_AT)compact=false;
   const wasCompact=header.classList.contains('is-scrolled');
   if(wasCompact!==compact){
     // Don't leave an off-screen, previously open desktop dropdown or language
     // popup focused after the compact header changes which links are visible.
     header.querySelectorAll('.store-nav>details[open],.store-language-menu[open]').forEach(el=>{
       el.open=false;
       if(el.dataset.navReady)el.dataset.navReady='0';
     });
   }
   header.classList.toggle('is-scrolled',compact);
   root.classList.toggle('store-has-scrolled',compact);
 };
 const schedule=()=>{if(!queued){queued=true;requestAnimationFrame(sync);}};
 window.addEventListener('scroll',schedule,{passive:true});
 window.addEventListener('pageshow',sync);
 window.addEventListener('resize',schedule,{passive:true});
 sync();
})();

(()=>{
  'use strict';
  // Google Fonts are intentionally non-render-blocking. Activate them after the HTML
  // is parsed; system fallbacks paint immediately instead of making a local reload wait.
  document.querySelectorAll('link[data-deferred-fonts]').forEach((link)=>{link.media='all';});
  const csrf=document.querySelector('meta[name="sm-csrf"]')?.getAttribute('content')||'';
  const endpoint=document.body?.dataset?.foundationPulseUrl||'';
  if(!csrf||!endpoint)return;
  let sent=false;
  const send=()=>{
    if(sent||document.hidden)return;
    sent=true;
    const body=new URLSearchParams();
    body.set('csrf_token',csrf);
    const isStore=document.body.classList.contains('store-body');
    body.set('track_pageview',isStore?'1':'0');
    if(isStore){
      body.set('path',location.pathname);
      body.set('referrer',document.referrer||'');
      const q=new URLSearchParams(location.search);
      for(const key of ['utm_source','utm_medium','utm_campaign']){const value=q.get(key);if(value)body.set(key,value);}
    }
    fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},body:body.toString(),keepalive:true,cache:'no-store'}).catch(()=>{});
  };
  const schedule=()=>{
    if('requestIdleCallback'in window)window.requestIdleCallback(send,{timeout:2500});
    else window.setTimeout(send,1200);
  };
  if(document.readyState==='complete')schedule();else window.addEventListener('load',schedule,{once:true});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!sent)schedule();},{once:true});
})();


/* perf-debug-v427: append ?perf=1 while testing to print browser timings in DevTools. */
(()=>{
  if(new URLSearchParams(location.search).get('perf')!=='1')return;
  let lcp=0;
  try{new PerformanceObserver((list)=>{for(const entry of list.getEntries())lcp=Math.max(lcp,entry.startTime||0);}).observe({type:'largest-contentful-paint',buffered:true});}catch(_err){}
  window.addEventListener('load',()=>window.setTimeout(()=>{
    const nav=performance.getEntriesByType('navigation')[0];if(!nav)return;
    const resources=performance.getEntriesByType('resource');
    const transferred=resources.reduce((sum,entry)=>sum+(entry.transferSize||0),0);
    const metrics={
      TTFB_ms:Math.round(nav.responseStart-nav.startTime),
      DOMContentLoaded_ms:Math.round(nav.domContentLoadedEventEnd-nav.startTime),
      Load_ms:Math.round(nav.loadEventEnd-nav.startTime),
      LCP_ms:lcp?Math.round(lcp):'pending',
      Resource_transfer_KB:Math.round(transferred/1024)
    };
    console.info('[Store performance]',metrics);
    const largest=resources.map(entry=>({
      resource:(entry.name||'').split('/').pop()?.split('?')[0]||entry.name,
      type:entry.initiatorType||'resource',
      transfer_KB:Math.round((entry.transferSize||entry.encodedBodySize||0)/1024),
      duration_ms:Math.round(entry.duration||0)
    })).sort((a,b)=>b.transfer_KB-a.transfer_KB).slice(0,10);
    if(largest.length)console.table(largest);
  },0),{once:true});
})();

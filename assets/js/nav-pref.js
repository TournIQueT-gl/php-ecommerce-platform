(()=>{
  'use strict';
  const root=document.documentElement;
  for(const [key,cookie,className] of [
    ['auraa-admin-sidebar-collapsed','auraa_admin_sidebar_collapsed','pref-admin-collapsed'],
    ['auraa-account-sidebar-collapsed','auraa_account_sidebar_collapsed','pref-account-collapsed']
  ]){
    const cookies=Object.fromEntries(document.cookie.split(';').map(part=>part.trim().split('=').map(v=>v.trim())).filter(pair=>pair[0]));
    // The server renders the initial sidebar from the cookie. Do not override it
    // from a stale localStorage entry during the first paint.
    const cookieState=cookies[cookie];
    let state=cookieState==='1';
    if(cookieState!=='0'&&cookieState!=='1'){
      try{const stored=localStorage.getItem(key);if(stored==='1'||stored==='0')state=stored==='1';}catch(_){}
    }
    try{localStorage.setItem(key,state?'1':'0');}catch(_){}
    root.classList.toggle(className,state);
    document.cookie=`${cookie}=${state?'1':'0'}; path=/; max-age=31536000; samesite=lax`;
  }
})();

/* Repair 16: select homepage sections without a full document navigation.
   The physical link remains as an accessible fallback if the network request fails. */
(()=>{'use strict';
  if(!document.querySelector('.homepage-builder-shell'))return;
  let pending=null;
  // History changes do not fire beforeunload. Keep the active editor URL to avoid losing drafts.
  let activeUrl=location.href;
  const isDirty=()=>Boolean(document.querySelector('.homepage-builder-main form[data-is-dirty="1"], .homepage-builder-main [data-unsaved-indicator]:not([hidden])'));
  const confirmDiscard=()=>!isDirty()||window.confirm(
    document.documentElement.lang?.startsWith('ar')?'توجد تعديلات لم تحفظ. هل تريد الانتقال بدون حفظها؟':'You have unsaved section changes. Switch sections without saving?');
  const selectSection=async(url,push=true)=>{
    const main=document.querySelector('.homepage-builder-main');
    if(!main){location.assign(url);return;}
    pending?.abort();const request=new AbortController();pending=request;
    main.classList.add('builder-section-loading');main.setAttribute('aria-busy','true');
    try{
      const reply=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'},signal:request.signal});
      if(!reply.ok)throw new Error('Section request failed: HTTP '+reply.status);
      const incoming=new DOMParser().parseFromString(await reply.text(),'text/html');
      // A second click may start another request before this response is parsed.
      // Only the most recent selection may update the builder or browser history.
      if(pending!==request)return;
      const editor=incoming.querySelector('.homepage-builder-main');
      if(!editor)throw new Error('Section editor is missing from the response.');
      main.replaceChildren(...Array.from(editor.childNodes).map(el=>document.importNode(el,true)));
      const chosen=new URL(url,location.href).searchParams.get('section');
      document.querySelectorAll('[data-homepage-section-card]').forEach(card=>{
        const active=card.dataset.id===chosen;
        card.classList.toggle('is-selected',active);
        const link=card.querySelector('.homepage-builder-section-link');
        if(link){if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');}
      });
      // Keep the existing preview dialog and event handlers, changing its content instead of
      // replacing the dialog node (which would strand existing button listeners).
      const preview=document.querySelector('[data-builder-preview-dialog]');
      const newPreview=incoming.querySelector('[data-builder-preview-dialog]');
      const frame=preview?.querySelector('[data-builder-preview-frame]');
      const newFrame=newPreview?.querySelector('[data-builder-preview-frame]');
      if(frame&&newFrame)frame.src=newFrame.getAttribute('src')||frame.src;
      const heading=preview?.querySelector('.homepage-builder-preview-workspace header h2');
      const nextHeading=newPreview?.querySelector('.homepage-builder-preview-workspace header h2');
      if(heading&&nextHeading)heading.textContent=nextHeading.textContent;
      const previewLink=preview?.querySelector('.homepage-builder-preview-toolbar a[target="_blank"]');
      const nextPreviewLink=newPreview?.querySelector('.homepage-builder-preview-toolbar a[target="_blank"]');
      if(previewLink&&nextPreviewLink)previewLink.href=nextPreviewLink.href;
      if(window.AuraaUI?.enhance)window.AuraaUI.enhance(main);
      document.dispatchEvent(new CustomEvent('auraa:content-updated',{detail:{root:main}}));
      if(incoming.title)document.title=incoming.title;
      if(push)history.pushState({builderSection:true},'',url);
      activeUrl=url;
    }catch(err){
      if(err?.name==='AbortError'||pending!==request)return;
      console.warn('[Homepage builder] Falling back to full navigation',err);
      location.assign(url);
    }finally{
      if(pending===request){pending=null;main.removeAttribute('aria-busy');main.classList.remove('builder-section-loading');}
    }
  };
  document.addEventListener('click',event=>{
    const link=event.target instanceof Element?event.target.closest('.homepage-builder-section-link'):null;
    if(!(link instanceof HTMLAnchorElement)||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
    if(!link.closest('.homepage-builder-sidebar'))return;
    const destination=new URL(link.href,location.href);
    const normalized=path=>path.replace(/\.php$/i,'').replace(/\/$/,'');
    if(destination.origin!==location.origin||normalized(destination.pathname)!==normalized(location.pathname))return;
    event.preventDefault();
    // A pending order save must finish before switching the editor.
    if(document.querySelector('.homepage-builder-shell[data-order-saving="1"]'))return;
    if(destination.href===location.href)return;
    if(!confirmDiscard())return;
    selectSection(destination.href,true);
  });
  window.addEventListener('popstate',()=>{
    if(!document.querySelector('.homepage-builder-shell'))return;
    if(document.querySelector('.homepage-builder-shell[data-order-saving="1"]')){
      history.pushState({builderSection:true},'',activeUrl);return;
    }
    if(!confirmDiscard()){
      // Browser history has already moved. Restore the displayed section's URL without
      // replacing the editor or losing user input. Never bypass the unsaved warning.
      history.pushState({builderSection:true},'',activeUrl);
      return;
    }
    selectSection(location.href,false);
  });
})();

(() => {
  'use strict';
  const form=document.querySelector('[data-variant-generator]');
  if(form){
    const count=form.querySelector('[data-variant-count]');
    const submit=form.querySelector('[type=submit]');
    const ar=document.documentElement.lang?.toLowerCase().startsWith('ar');
    const update=()=>{
      const axes=new Map();
      for(const box of form.querySelectorAll('[data-variant-choice]')){
        const id=box.name.match(/choices\[(\d+)\]/)?.[1];
        if(id)axes.set(id,(axes.get(id)||0)+(box.checked?1:0));
      }
      const values=[...axes.values()];
      const total=values.length?values.reduce((n,k)=>n*k,1):1;
      const invalid=values.some(k=>k===0)||total>60;
      if(submit)submit.disabled=invalid;
      if(count)count.textContent=values.some(k=>k===0)?(ar?'اختر خيارًا واحدًا على الأقل لكل سمة.':'Choose at least one option in each row.'):(ar?`${total} تركيبة SKU محددة${total>60?' (الحد الأقصى 60)':''}`:total+' SKU combination'+(total===1?'':'s')+' selected'+(total>60?' (maximum 60 per action)':''));
    };
    form.addEventListener('change',update);update();
  }

  // One isolated gallery editor per product or SKU form. Never submit image paths
  // from another SKU: the PHP service restricts each reorder to owned paths.
  document.querySelectorAll('[data-uc-gallery]').forEach(galleryRoot=>{
    const owner=galleryRoot.closest('form');
    const cards=galleryRoot.querySelector('[data-uc-gallery-grid]');
    const upload=galleryRoot.querySelector('[data-uc-gallery-input]');
    const order=galleryRoot.querySelector('[data-uc-gallery-order]');
    const promote=galleryRoot.querySelector('[data-uc-gallery-promote]');
    const pending=galleryRoot.querySelector('[data-uc-gallery-pending]');
    const feedbackGallery=galleryRoot.querySelector('[data-uc-gallery-feedback]');
    const countGallery=galleryRoot.querySelector('[data-uc-gallery-count]');
    const removeMain=galleryRoot.querySelector('[data-uc-remove-main]');
    const mainInput=owner?.querySelector('[data-uc-photo-input]');
    const mainPreview=owner?.querySelector('[data-uc-photo-preview]');
    const originalMain=mainPreview?.getAttribute('src')||'';
    let pendingUrls=[];
    let mainUrl=null, validationSequence=0, validationPending=false, validationError='';
    const acceptedTypes=new Set(['image/jpeg','image/png','image/webp']);
    const validateImage=async(file)=>{
      if(!acceptedTypes.has(file.type))throw new Error(`${file.name}: choose a JPG, PNG or WebP image.`);
      const src=URL.createObjectURL(file);
      try{
        const img=new Image();
        const loaded=new Promise((resolve,reject)=>{
          img.onload=()=>resolve([img.naturalWidth,img.naturalHeight]);
          img.onerror=()=>reject(new Error(`${file.name}: this image could not be read.`));
        });
        img.src=src;
        const [width,height]=await loaded;
        if(width<700||height<700)throw new Error(`${file.name}: image is ${width} × ${height}px; minimum is 700 × 700px.`);
      }finally{URL.revokeObjectURL(src);}
    };
    const validateSelection=async()=>{
      const sequence=++validationSequence;
      validationPending=true;validationError='';sync();
      const files=[...(mainInput?.files||[]),...(upload?.files||[])];
      try{for(const file of files)await validateImage(file);}
      catch(error){if(sequence===validationSequence)validationError=error instanceof Error?error.message:'Invalid image selected.';}
      finally{if(sequence===validationSequence){validationPending=false;sync();}}
    };
    const saved=()=>[...cards.querySelectorAll('[data-uc-gallery-card]:not(.is-removed)')];
    const sync=()=>{
      if(order)order.value=JSON.stringify(saved().map(c=>c.dataset.path));
      const hasMain=Boolean(mainInput?.files?.length || (originalMain && !removeMain?.checked));
      const total=saved().length+(upload?.files?.length||0)+(hasMain?1:0);
      if(countGallery)countGallery.textContent=total+' / 6';
      galleryRoot.dataset.photoLimitExceeded=total>6?'1':'0';
      galleryRoot.dataset.photoValidationBlocked=(total>6||validationPending||Boolean(validationError))?'1':'0';
      if(feedbackGallery){
        const tooMany=total>6;
        feedbackGallery.textContent=tooMany?'Too many photos: keep six or fewer before saving.':validationError||
          (validationPending?'Checking image dimensions…':'Gallery changes take effect after saving this '+(galleryRoot.hasAttribute('data-uc-sku-gallery')?'SKU.':'product.'));
        feedbackGallery.classList.toggle('is-error',tooMany||Boolean(validationError));
      }
    };
    cards?.addEventListener('click',ev=>{
      const b=ev.target.closest('button');const card=b?.closest('[data-uc-gallery-card]');if(!card)return;
      if(b.matches('[data-uc-gallery-remove]')){
        card.classList.toggle('is-removed');const check=card.querySelector('[data-uc-gallery-remove-input]');if(check)check.checked=card.classList.contains('is-removed');
        if(promote?.value===card.dataset.path && card.classList.contains('is-removed')){
          promote.value='';
          if(mainPreview && !mainInput?.files?.length){mainPreview.hidden=!originalMain;if(originalMain)mainPreview.src=originalMain;else mainPreview.removeAttribute('src');}
        }
      }else if(b.matches('[data-uc-gallery-main]')){
        if(card.classList.contains('is-removed'))return;
        if(promote)promote.value=card.dataset.path;
        // Selecting an existing gallery photo as main supersedes an unsaved main upload.
        if(mainInput?.files?.length){mainInput.value='';if(mainUrl){URL.revokeObjectURL(mainUrl);mainUrl=null;}validateSelection();}
        if(mainPreview){mainPreview.src=card.querySelector('img')?.src||originalMain;mainPreview.hidden=!mainPreview.getAttribute('src');}
        cards.querySelectorAll('[data-uc-gallery-card]').forEach(c=>c.classList.toggle('is-main',c===card));
      }else if(b.matches('[data-uc-gallery-move]')){
        const other=b.dataset.ucGalleryMove==='left'?card.previousElementSibling:card.nextElementSibling;
        if(other?.matches('[data-uc-gallery-card]'))cards.insertBefore(card,b.dataset.ucGalleryMove==='left'?other:other.nextSibling);
      }
      sync();
    });
    mainInput?.addEventListener('change',()=>{
      if(mainUrl){URL.revokeObjectURL(mainUrl);mainUrl=null;}
      const file=mainInput.files?.[0];
      if(file?.type.startsWith('image/')){
        // An uploaded replacement takes precedence over a saved gallery promotion.
        if(promote)promote.value='';
        cards?.querySelectorAll('[data-uc-gallery-card]').forEach(c=>c.classList.remove('is-main'));
        mainUrl=URL.createObjectURL(file);if(mainPreview){mainPreview.src=mainUrl;mainPreview.hidden=false;}}
      else if(mainPreview){mainPreview.hidden=!originalMain;if(originalMain)mainPreview.src=originalMain;else mainPreview.removeAttribute('src');}
      validateSelection();
    });
    removeMain?.addEventListener('change',()=>{
      if(mainPreview && !mainInput?.files?.length)mainPreview.hidden=Boolean(removeMain.checked)||!originalMain;
      sync();
    });
    upload?.addEventListener('change',()=>{
      pendingUrls.forEach(u=>URL.revokeObjectURL(u));pendingUrls=[];
      pending?.replaceChildren();
      for(const file of upload.files||[]){
        if(!file.type.startsWith('image/'))continue;
        const src=URL.createObjectURL(file);pendingUrls.push(src);
        const img=document.createElement('img');img.src=src;img.alt='New photo preview';img.loading='lazy';pending?.append(img);
      }
      validateSelection();
    });
    // Block an invalid gallery at the editor level, before an expensive upload.
    owner?.addEventListener('submit',event=>{
      sync();
      if(galleryRoot.dataset.photoValidationBlocked!=='1')return;
      event.preventDefault();event.stopImmediatePropagation();
      feedbackGallery?.focus();
      galleryRoot.scrollIntoView({block:'center',behavior:'smooth'});
    },true);
    if(feedbackGallery)feedbackGallery.tabIndex=-1;
    window.addEventListener('pagehide',()=>{validationSequence++;pendingUrls.forEach(u=>URL.revokeObjectURL(u));if(mainUrl)URL.revokeObjectURL(mainUrl);},{once:true});
    sync();
  });

  const quick=document.querySelector('[data-uc-quick-create]');
  const quickForm=document.querySelector('[data-uc-taxonomy-form]');
  const taxonomyType=document.querySelector('[data-uc-taxonomy-type]');
  const parentWrap=document.querySelector('[data-uc-parent-wrap]');
  const feedback=document.querySelector('[data-uc-taxonomy-feedback]');
  const categorySelect=document.querySelector('[data-uc-category-select]');
  const departmentSelect=document.querySelector('[data-uc-department-switch]');
  const quickDepartment=document.querySelector('[data-uc-quick-department]');
  const productDepartment=document.querySelector('[data-uc-product-department]');
  function filterDepartmentCategories(){
    const id=departmentSelect?.value||'0';
    if(quickDepartment)quickDepartment.value=id;
    if(productDepartment)productDepartment.value=id;
    if(!categorySelect)return;
    for(const opt of categorySelect.options){
      if(!opt.value)continue;
      const visible=id==='0'||opt.dataset.department===id;
      opt.hidden=!visible;opt.disabled=!visible;
    }
    if(categorySelect.selectedOptions[0]?.disabled)categorySelect.value='';
  }
  filterDepartmentCategories();
  departmentSelect?.addEventListener('change',()=>{filterDepartmentCategories();categorySelect?.dispatchEvent(new Event('change',{bubbles:true}));});
  const brandSelect=document.querySelector('[data-uc-brand-select]');
  const productForm=document.querySelector('form[data-dirty-form]:not(.uc-variant-card)');
  const base=quickForm?.getAttribute('action') || location.pathname;
  const syncKind=()=>{if(parentWrap && taxonomyType)parentWrap.hidden=taxonomyType.value!=='category';};
  taxonomyType?.addEventListener('change',syncKind);syncKind();
  const taxonomyPhoto=quickForm?.querySelector('[data-uc-taxonomy-image]');
  const taxonomyPreview=quickForm?.querySelector('[data-uc-taxonomy-preview]');
  let taxonomyPhotoUrl=null;
  taxonomyPhoto?.addEventListener('change',()=>{
    if(taxonomyPhotoUrl){URL.revokeObjectURL(taxonomyPhotoUrl);taxonomyPhotoUrl=null;}
    const file=taxonomyPhoto.files?.[0];
    if(!taxonomyPreview)return;
    if(file?.type.startsWith('image/')){
      taxonomyPhotoUrl=URL.createObjectURL(file);taxonomyPreview.src=taxonomyPhotoUrl;taxonomyPreview.hidden=false;
    }else{taxonomyPreview.hidden=true;taxonomyPreview.removeAttribute('src');}
  });
  for(const button of document.querySelectorAll('[data-uc-taxonomy-open]')){
    button.addEventListener('click',()=>{
      if(!quick||!taxonomyType)return;
      quick.open=true;taxonomyType.value=button.getAttribute('data-uc-taxonomy-open')||'category';
      syncKind();quick.scrollIntoView({block:'center',behavior:'smooth'});
      quick.querySelector('[name="taxonomy_name"]')?.focus({preventScroll:true});
    });
  }

  const specPanel=document.querySelector('[data-uc-specs]');
  let specRequest=0;
  const showSpecNote=(message,failed=false)=>{
    if(!specPanel)return;
    specPanel.setAttribute('aria-busy','false');
    let note=specPanel.querySelector('[data-uc-spec-status]');
    if(!note){note=document.createElement('p');note.dataset.ucSpecStatus='';specPanel.prepend(note);}
    note.className=failed?'uc-inline-feedback is-error':'uc-inline-feedback';note.textContent=message;
  };
  async function refreshSpecifications(){
    if(!categorySelect||!specPanel)return;
    const chosen=categorySelect.value;
    // Invalidate earlier fetches even when the new selection is empty.
    const n=++specRequest;
    if(!chosen){
      specPanel.replaceChildren();showSpecNote('Choose or create a category to load its specifications.');return;
    }
    specPanel.setAttribute('aria-busy','true');showSpecNote('Loading category specifications…');
    const editId=productForm?.querySelector('[name="product_id"]')?.value||'0';
    const params=new URLSearchParams(editId!=='0'?{edit:editId,category:chosen}:{new:'1',category:chosen});
    try{
      const response=await fetch(base+'?'+params.toString(),{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!response.ok)throw new Error('HTTP '+response.status);
      const html=await response.text();
      if(n!==specRequest)return;
      const documentCopy=new DOMParser().parseFromString(html,'text/html');
      const next=documentCopy.querySelector('[data-uc-specs]');
      if(!next)throw new Error('Editor layout unavailable');
      specPanel.innerHTML=next.innerHTML;
      specPanel.setAttribute('aria-busy','false');
    }catch(error){
      if(n===specRequest)showSpecNote('Could not load the attributes. Open Category attributes to configure the category, then retry. '+error.message,true);
    }
  }
  categorySelect?.addEventListener('change',()=>{
    const hidden=quickForm?.querySelector('[data-uc-current-category]');
    if(hidden)hidden.value=categorySelect.value||'0';
    refreshSpecifications();
  });

  quickForm?.addEventListener('submit',async event=>{
    event.preventDefault();
    const submit=quickForm.querySelector('[type="submit"]');
    if(quickForm.dataset.saving==='1')return;
    quickForm.dataset.saving='1';
    const originalLabel=submit?.textContent||'Save category / brand';
    if(feedback){feedback.textContent='Saving…';feedback.className='uc-muted';}
    if(submit){submit.disabled=true;submit.textContent='Saving…';submit.setAttribute('aria-busy','true');}
    try{
      const payload=new FormData(quickForm);
      payload.set('ajax','1');
      if(categorySelect)payload.set('selected_category',categorySelect.value||'0');
      // Read the attribute, not form.action; hidden inputs named action shadow it.
      const response=await fetch(quickForm.getAttribute('action')||base,{
        method:'POST',body:payload,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
      });
      const raw=await response.text();let data;
      try{data=JSON.parse(raw);}catch(_e){throw new Error('Server returned HTML instead of JSON (check login/session or PHP error).');}
      if(!response.ok||!data.ok)throw new Error(data.error||'Could not create this item.');
      const item=data.item;
      const target=item.type==='brand'?brandSelect:categorySelect;
      if(target){
        const option=document.createElement('option');option.value=String(item.id);option.textContent=item.name;
        if(item.type==='category')option.dataset.department=departmentSelect?.value||'0';
        target.append(option);target.value=String(item.id);
      }
      if(item.type==='category'){
        const parent=quickForm.querySelector('[name="parent_id"]');
        if(parent){const option=document.createElement('option');option.value=String(item.id);option.textContent=item.name;parent.append(option);}
        const hidden=quickForm.querySelector('[data-uc-current-category]');if(hidden)hidden.value=String(item.id);
        await refreshSpecifications();
      }
      if(feedback){
        feedback.replaceChildren(document.createTextNode(item.name+' saved. '));
        if(item.type==='category'){
          const next=document.createElement('a');next.href=base+'?setup='+encodeURIComponent(String(item.id));
          next.textContent='Next: set up Size / Color or other attributes →';feedback.append(next);
        }else feedback.append(document.createTextNode('You can now select it in your product.'));
        feedback.className='uc-inline-feedback is-success';
      }
      quickForm.querySelector('[name="taxonomy_name"]').value='';
      const file=quickForm.querySelector('[name="taxonomy_image"]');if(file)file.value='';
      if(taxonomyPhotoUrl){URL.revokeObjectURL(taxonomyPhotoUrl);taxonomyPhotoUrl=null;}
      if(taxonomyPreview){taxonomyPreview.hidden=true;taxonomyPreview.removeAttribute('src');}
    }catch(error){
      if(feedback){feedback.textContent=error.message;feedback.className='uc-inline-feedback is-error';}
    }finally{
      quickForm.dataset.saving='';
      if(submit){submit.disabled=false;submit.textContent=originalLabel;submit.removeAttribute('aria-busy');}
    }
  });

  // Custom attributes use the same category settings table. Refresh only the
  // settings panel, not the whole admin page, after a successful insert.
  function syncCustomAttributeFields(form){
    const type=form.querySelector('[data-uc-attribute-type]');
    const scope=form.querySelector('[data-uc-attribute-scope]');
    const choices=form.querySelector('[data-uc-attribute-options]');
    const axis=form.querySelector('[data-uc-attribute-axis]');
    if(!type||!scope)return;
    if(scope.value==='variant'&&type.value!=='select')type.value='select';
    if(choices)choices.hidden=type.value!=='select';
    if(axis){axis.hidden=!(scope.value==='variant'&&type.value==='select');if(axis.hidden){const check=axis.querySelector('input');if(check)check.checked=false;}}
  }
  document.addEventListener('change',event=>{
    const field=event.target;
    if(!(field instanceof Element)||!field.matches('[data-uc-attribute-type],[data-uc-attribute-scope]'))return;
    const form=field.closest('[data-uc-attribute-form]');
    if(form)syncCustomAttributeFields(form);
  });
  document.querySelectorAll('[data-uc-attribute-form]').forEach(syncCustomAttributeFields);
  document.addEventListener('submit',async event=>{
    const attrForm=event.target;
    if(!(attrForm instanceof HTMLFormElement)||!attrForm.matches('[data-uc-attribute-form]'))return;
    event.preventDefault();
    if(attrForm.dataset.saving==='1')return;
    attrForm.dataset.saving='1';
    const submit=attrForm.querySelector('[type="submit"]');
    const feedback=attrForm.querySelector('[data-uc-attribute-feedback]');
    const label=submit?.textContent||'Create attribute';
    if(submit){submit.disabled=true;submit.textContent='Saving…';submit.setAttribute('aria-busy','true');}
    if(feedback){feedback.textContent='Saving attribute…';feedback.className='uc-muted';}
    try{
      const res=await fetch(attrForm.getAttribute('action')||base,{
        method:'POST',body:new FormData(attrForm),credentials:'same-origin',
        headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}
      });
      const json=await res.json().catch(()=>null);
      if(!res.ok||!json?.ok)throw new Error(json?.error||`Unable to create attribute (HTTP ${res.status}).`);
      const category=attrForm.querySelector('[name="category_id"]')?.value||'0';
      const refreshed=await fetch(base+'?setup='+encodeURIComponent(category),{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!refreshed.ok)throw new Error(`Saved, but could not refresh the attribute list (HTTP ${refreshed.status}).`);
      const parsed=new DOMParser().parseFromString(await refreshed.text(),'text/html');
      const replacement=parsed.querySelector('[data-uc-attribute-setup]');
      const original=document.querySelector('[data-uc-attribute-setup]');
      if(!replacement||!original)throw new Error('Saved, but the updated attribute list could not be displayed. Refresh this page.');
      const confirmation=document.createElement('p');
      confirmation.className='uc-inline-feedback is-success';confirmation.setAttribute('role','status');
      confirmation.textContent=json.message||'Attribute created successfully.';
      replacement.prepend(confirmation);
      original.replaceWith(replacement);
      const nextForm=replacement.querySelector('[data-uc-attribute-form]');
      if(nextForm)syncCustomAttributeFields(nextForm);
    }catch(error){
      if(feedback){feedback.textContent=error.message||'Could not create attribute.';feedback.className='uc-inline-feedback is-error';}
    }finally{
      attrForm.dataset.saving='';
      if(submit){submit.disabled=false;submit.textContent=label;submit.removeAttribute('aria-busy');}
    }
  });
})();

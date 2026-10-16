(()=>{'use strict';
const form=document.querySelector('[data-sx-form]');if(!form)return;
const arabic=form.dataset.sxLang==='ar'||document.documentElement.lang?.toLowerCase().startsWith('ar')||document.documentElement.dir==='rtl';
const msg=(en,ar)=>arabic?ar:en;
// Compare current settings with the initial saved draft. Reverting a change clears the dirty state.
const snapshot=()=>JSON.stringify([...new FormData(form).entries()]);
const initialDraft=snapshot();
let isDirty=false;
const draftStatus=document.querySelector('[data-sx-draft-state]');
const refreshButton=document.querySelector('[data-sx-preview-reload]');
const refreshHint=document.querySelector('[data-sx-preview-hint]');
const synchronizeDirty=()=>{
 isDirty=snapshot()!==initialDraft;
 form.dataset.isDirty=isDirty?'1':'0';
 if(draftStatus){draftStatus.textContent=isDirty?
 msg('Unsaved changes · Save Draft before refreshing the real preview','تغييرات غير محفوظة · احفظ المسودة أولًا لتحديث المعاينة'):
 msg('Saved draft · Not yet published','مسودة محفوظة · لم تُنشر بعد');draftStatus.dataset.dirty=String(isDirty);}
 if(refreshButton){refreshButton.disabled=isDirty;refreshButton.title=isDirty?
 msg('Save Draft to preview these edits','احفظ المسودة لمعاينة التعديلات'):'';}
 if(refreshHint)refreshHint.hidden=!isDirty;
};
// Existing admin beforeunload handler respects data-is-dirty; clear only for valid form submission.
form.addEventListener('submit',event=>{if(!event.defaultPrevented){isDirty=false;form.dataset.isDirty='0';}});

let presets={};try{presets=JSON.parse(document.querySelector('#sx-presets-json')?.textContent||'{}');}catch{}
const preview=document.querySelector('[data-sx-preview]');if(!preview)return;
const field=(name)=>form.elements.namedItem(name);
const pick=(selector)=>form.querySelector(selector);
const safeText=(selector,value)=>{const el=preview.querySelector(selector);if(el)el.textContent=value;};
const update=()=>{
 const colors={accent:'--theme-accent',background:'--theme-bg',text:'--theme-ink',footer:'--theme-footer'};
 for(const [key,css] of Object.entries(colors)){
  const color=field(key)?.value||'#fff';preview.style.setProperty(css,color);
  const output=pick(`[data-sx-color-output="${key}"]`);if(output)output.textContent=color;
 }
 const radius=field('radius')?.value||'8';preview.style.setProperty('--theme-radius',radius+'px');
 const spaces={compact:'8px',comfortable:'16px',spacious:'26px'};preview.style.setProperty('--theme-gap',spaces[field('density')?.value]||'16px');
 preview.dataset.sxFont=field('font')?.value||'editorial';
 safeText('[data-sx-preview-name]',field('store_name')?.value||'Your Store');
 safeText('[data-sx-preview-footer-name]',field('store_name')?.value||'Your Store');
 preview.dataset.sxLayout=field('header_layout')?.value||'balanced';
 preview.dataset.sxStorefrontLayout=field('storefront_layout')?.value||'classic';
 preview.dataset.sxPhotoShape=field('product_image_shape')?.value||'square';
 preview.dataset.sxImageFit=field('product_image_fit')?.value||'contain';
 preview.dataset.sxContentWidth=field('content_width')?.value||'standard';
 preview.dataset.sxCatalogColumns=field('catalog_columns')?.value||'0';
 preview.style.setProperty('--sx-catalog-cols',field('catalog_columns')?.value||'0');
 preview.dataset.sxHeaderDensity=field('header_density')?.value||'comfortable';
 preview.dataset.sxSticky=field('sticky_header')?.checked?'yes':'no';
 safeText('[data-sx-preview-subtitle]',field('logo_subtitle')?.value||'');
 safeText('[data-sx-preview-tagline]',field('tagline')?.value||'Discover your next favourite.');
 const toLuminance=(hex)=>{const c=[1,3,5].map(i=>parseInt(hex.substring(i,i+2),16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return .2126*c[0]+.7152*c[1]+.0722*c[2];};
 const textColor=field('text')?.value||'#211d19';const bg=field('background')?.value||'#f7f5f2';
 const [light,dark]=[toLuminance(textColor),toLuminance(bg)].sort((a,b)=>b-a);
 const ratio=(light+.05)/(dark+.05);
 const indicator=document.querySelector('[data-sx-contrast]');if(indicator){indicator.textContent=arabic?`تباين النص ${ratio.toFixed(1)}:1 · ${ratio>=4.5?'مناسب':'منخفض - غيّر الألوان'}`:`Text contrast ${ratio.toFixed(1)}:1 · ${ratio>=4.5?'AA pass':'Below AA – adjust colours'}`;indicator.dataset.valid=ratio>=4.5?'true':'false';}
 const utility=preview.querySelector('[data-sx-preview-utility]');if(utility)utility.hidden=!field('show_utility_bar')?.checked;
 form.querySelectorAll('[data-sx-preset]').forEach(b=>{b.checked=b.value===field('preset')?.value;});
};
form.querySelectorAll('[data-sx-preset]').forEach(button=>button.addEventListener('change',()=>{
 const key=button.dataset.sxPreset;const chosen=presets[key];if(!chosen)return;
 for(const [name,value] of Object.entries(chosen)){const el=field(name);if(el)el.value=String(value);}
 // Palette selection NEVER changes an explicitly selected storefront layout or homepage content.
 field('preset').value=key;update();
}));
const realBox=document.querySelector('[data-sx-real-box]');
const realFrame=document.querySelector('[data-sx-real-frame]');
const renderRealPreview=()=>{
 if(!realBox||!realFrame)return;
 const viewport={desktop:1440,tablet:768,mobile:390}[realBox.dataset.mode]||1440;
 const scale=Math.min(1,(realBox.clientWidth-2)/viewport);
 realFrame.style.width=viewport+'px';realFrame.style.height='850px';
 realFrame.style.transform=`scale(${scale})`;
 realBox.style.height=Math.ceil(850*scale)+'px';
};
window.addEventListener('resize',renderRealPreview);
if(typeof ResizeObserver==='function'&&realBox)new ResizeObserver(renderRealPreview).observe(realBox);
document.querySelector('[data-sx-preview-reload]')?.addEventListener('click',()=>{if(realFrame){const url=new URL(realFrame.src);url.searchParams.set('_refresh',String(Date.now()));realFrame.src=url.toString();}});
form.addEventListener('input',()=>{update();synchronizeDirty();});form.addEventListener('change',()=>{update();synchronizeDirty();});
document.querySelectorAll('[data-sx-mode]').forEach(b=>b.addEventListener('click',()=>{
 preview.dataset.mode=b.dataset.sxMode;
 const realBox=document.querySelector('[data-sx-real-box]');if(realBox){realBox.dataset.mode=b.dataset.sxMode;renderRealPreview();}
 preview.querySelector('[data-sx-preview-drawer]')?.setAttribute('hidden','');
 document.querySelectorAll('[data-sx-mode]').forEach(x=>{x.classList.toggle('is-active',x===b);x.setAttribute('aria-pressed',String(x===b));});
}));
const menu=preview.querySelector('[data-sx-preview-drawer]');const open=preview.querySelector('[data-sx-mobile-menu]');
open?.addEventListener('click',()=>{const opening=menu?.hasAttribute('hidden');if(opening)menu?.removeAttribute('hidden');else menu?.setAttribute('hidden','');open.setAttribute('aria-expanded',String(!!opening));});
preview.querySelector('[data-sx-mobile-menu-close]')?.addEventListener('click',()=>{menu?.setAttribute('hidden','');open?.setAttribute('aria-expanded','false');});
update();synchronizeDirty();renderRealPreview();
})();

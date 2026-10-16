(()=>{
'use strict';
let activeUiListeners=null, refreshView=null;
const initInventory=()=>{
  activeUiListeners?.abort();activeUiListeners=new AbortController();
  const {signal}=activeUiListeners;
const adjustDialog=document.querySelector('[data-inventory-adjust-dialog]');
const bulkDialog=document.querySelector('[data-inventory-bulk-dialog]');
if(!(adjustDialog instanceof HTMLDialogElement))return;
const form=adjustDialog.querySelector('[data-inventory-adjust-form]');
const errorBox=adjustDialog.querySelector('[data-inventory-dialog-error]');
const idInput=adjustDialog.querySelector('[data-inventory-dialog-id]');
const stockEl=adjustDialog.querySelector('[data-inventory-dialog-stock]');
const projectedEl=adjustDialog.querySelector('[data-inventory-dialog-projected]');
const thresholdLabel=adjustDialog.querySelector('[data-inventory-dialog-threshold-label]');
const thresholdInput=adjustDialog.querySelector('[data-inventory-dialog-threshold]');
const qtyInput=adjustDialog.querySelector('[data-inventory-dialog-quantity]');
const noteInput=adjustDialog.querySelector('[data-inventory-dialog-note]');
const productEl=adjustDialog.querySelector('[data-inventory-dialog-product]');
const brandEl=adjustDialog.querySelector('[data-inventory-dialog-brand]');
const variantEl=adjustDialog.querySelector('[data-inventory-dialog-variant]');
let currentStock=0;
let stockStateRequest=null, stockStateRevision=0;
const cancelStockState=()=>{stockStateRevision++;stockStateRequest?.abort();stockStateRequest=null;};
const showError=(msg='')=>{if(errorBox instanceof HTMLElement){errorBox.textContent=msg;errorBox.hidden=!msg;}};
const operation=()=>adjustDialog.querySelector('input[name="stock_operation"]:checked')?.value||'receive';
const refreshProjection=()=>{const qty=Math.max(0,Number.parseInt(qtyInput?.value||'0',10)||0),op=operation();let next=currentStock;if(op==='set_count')next=qty;else if(op==='correction_remove'||op==='damage')next=currentStock-qty;else next=currentStock+qty;if(projectedEl){projectedEl.textContent=String(next);projectedEl.classList.toggle('is-negative',next<0);}const label=adjustDialog.querySelector('[data-inventory-quantity-label]');if(label)label.textContent=op==='set_count'?'Counted stock':'Quantity';const hint=adjustDialog.querySelector('[data-inventory-quantity-hint]');if(hint)hint.textContent=op==='set_count'?'Enter the exact bottles physically counted.':'Use 0 when only changing the alert threshold.';if(noteInput instanceof HTMLInputElement)noteInput.required=op==='set_count'||((qty>0)&&op!=='receive');};
const updateSummary=(summary)=>{const keys=['active','healthy','low','out'];document.querySelectorAll('.inventory-metrics>a').forEach((card,i)=>{const strong=card.querySelector('strong');if(strong&&summary&&keys[i] in summary)strong.textContent=Number(summary[keys[i]]||0).toLocaleString();});};
const updateVariant=(item)=>{if(!item)return;document.querySelectorAll(`[data-inventory-open-adjust][data-variant-id="${CSS.escape(String(item.id))}"]`).forEach(btn=>{btn.dataset.stock=String(item.stock_qty);btn.dataset.threshold=String(item.low_stock_threshold);});const row=document.querySelector(`[data-inventory-row][data-variant-id="${CSS.escape(String(item.id))}"]`);if(!row)return;row.querySelector('[data-inventory-row-stock]')?.replaceChildren(document.createTextNode(String(item.stock_qty)));row.querySelector('[data-inventory-row-threshold]')?.replaceChildren(document.createTextNode(String(item.low_stock_threshold)));const state=row.querySelector('[data-inventory-row-state]');if(state){state.textContent=item.state||'';state.className=`inventory-state is-${item.state_class||'neutral'}`;}row.classList.toggle('has-problem',item.state_class==='out');row.classList.toggle('needs-attention',item.state_class==='low');};
const toast=(message,bad=false)=>{let el=document.querySelector('[data-inventory-toast]');if(!el){el=document.createElement('div');el.dataset.inventoryToast='1';el.className='admin-live-toast';document.body.append(el);}el.textContent=message;el.classList.toggle('is-error',bad);el.classList.add('is-visible');clearTimeout(Number(el.dataset.timer||0));el.dataset.timer=String(setTimeout(()=>el.classList.remove('is-visible'),2800));};
const populate=(item)=>{if(!item)return;currentStock=Number(item.stock_qty)||0;if(idInput instanceof HTMLInputElement)idInput.value=String(item.id);if(productEl)productEl.textContent=item.product_name||'Product';if(brandEl)brandEl.textContent=item.brand_name||'';if(variantEl)variantEl.textContent=[item.label,item.sku].filter(Boolean).join(' · ');if(stockEl)stockEl.textContent=String(currentStock);if(projectedEl)projectedEl.textContent=String(currentStock);if(thresholdLabel)thresholdLabel.textContent=String(item.low_stock_threshold??0);if(thresholdInput instanceof HTMLInputElement)thresholdInput.value=String(item.low_stock_threshold??0);if(qtyInput instanceof HTMLInputElement)qtyInput.value='1';if(noteInput instanceof HTMLInputElement)noteInput.value='';adjustDialog.querySelector('input[name="stock_operation"][value="receive"]')?.click();refreshProjection();};
const openAdjust=async(button)=>{
  cancelStockState();
  const revision=stockStateRevision, variantId=String(button.dataset.variantId||'0');
  showError('');
  populate({id:variantId,product_name:button.dataset.product,brand_name:button.dataset.brand,label:button.dataset.label,sku:button.dataset.sku,stock_qty:button.dataset.stock,low_stock_threshold:button.dataset.threshold});
  if(!adjustDialog.open)adjustDialog.showModal();
  document.body.classList.add('ui-modal-open');
  const ctrl=new AbortController();stockStateRequest=ctrl;
  const endpoint=form?.dataset.inventoryStateEndpoint||'inventory.php?_ajax=variant-state';
  // Keep a merchant's edits if they change a field while the live snapshot loads.
  const initial={quantity:qtyInput?.value,threshold:thresholdInput?.value,note:noteInput?.value,operation:operation()};
  try{
    const url=new URL(endpoint,location.href);url.searchParams.set('variant_id',variantId);
    const res=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',signal:ctrl.signal});
    const data=await res.json().catch(()=>null);
    if(ctrl.signal.aborted||revision!==stockStateRevision||!adjustDialog.open||idInput?.value!==variantId)return;
    if(!res.ok||!data?.ok)throw new Error(data?.message||`Live stock could not be loaded (HTTP ${res.status}).`);
    // Reject a misrouted response as well as a stale response.
    if(String(data.variant?.id)!==variantId)throw new Error('The live stock response does not match the selected SKU.');
    const edits={
      quantity:qtyInput?.value!==initial.quantity?qtyInput?.value:null,
      threshold:thresholdInput?.value!==initial.threshold?thresholdInput?.value:null,
      note:noteInput?.value!==initial.note?noteInput?.value:null,
      operation:operation()!==initial.operation?operation():null
    };
    populate(data.variant);
    if(edits.quantity!==null&&qtyInput)qtyInput.value=edits.quantity;
    if(edits.threshold!==null&&thresholdInput)thresholdInput.value=edits.threshold;
    if(edits.note!==null&&noteInput)noteInput.value=edits.note;
    if(edits.operation)adjustDialog.querySelector(`input[name="stock_operation"][value="${edits.operation}"]`)?.click();
    refreshProjection();updateSummary(data.summary);
  }catch(err){
    if(!ctrl.signal.aborted&&revision===stockStateRevision&&adjustDialog.open&&idInput?.value===variantId)
      showError(`${err instanceof Error?err.message:'Live stock could not be loaded.'} The server will validate the final quantity.`);
  }finally{if(stockStateRequest===ctrl)stockStateRequest=null;}
};
document.addEventListener('click',e=>{const adjust=e.target.closest('[data-inventory-open-adjust]');if(adjust){e.preventDefault();openAdjust(adjust);return;}const bulk=e.target.closest('[data-inventory-open-bulk]');if(bulk&&bulkDialog instanceof HTMLDialogElement){e.preventDefault();bulkDialog.showModal();document.body.classList.add('ui-modal-open');bulkDialog.querySelector('input[name="bulk_value[]"]')?.focus();}},{signal});
adjustDialog.querySelectorAll('[data-inventory-dialog-close]').forEach(btn=>btn.addEventListener('click',()=>{cancelStockState();adjustDialog.close();document.body.classList.remove('ui-modal-open');showError('');}));adjustDialog.addEventListener('cancel',()=>{cancelStockState();document.body.classList.remove('ui-modal-open');});
adjustDialog.querySelectorAll('input[name="stock_operation"]').forEach(el=>el.addEventListener('change',refreshProjection));qtyInput?.addEventListener('input',refreshProjection);
form?.addEventListener('submit',async e=>{
  e.preventDefault();if(form.dataset.saving==='1')return;
  const submittedRevision=stockStateRevision, submittedId=idInput?.value;
  showError('');refreshProjection();
  const submit=form.querySelector('button[type="submit"]');
  form.dataset.saving='1';
  if(submit instanceof HTMLButtonElement){submit.disabled=true;submit.textContent='Saving…';}
  const ownsDialog=()=>adjustDialog.open && stockStateRevision===submittedRevision && idInput?.value===submittedId;
  try{
    const fd=new FormData(form);fd.set('_ajax','inventory');
    const res=await fetch(form.getAttribute('action')||'inventory.php',{
      method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:fd
    });
    const data=await res.json().catch(()=>null);
    if(!res.ok||!data?.ok)throw new Error(data?.message||`Inventory could not be updated (HTTP ${res.status}).`);
    (data.variants||[]).forEach(updateVariant);updateSummary(data.summary);
    toast(data.message||'Inventory updated.');
    // A slow response must never close a different SKU's dialog.
    if(ownsDialog()){
      cancelStockState();adjustDialog.close();document.body.classList.remove('ui-modal-open');
      refreshView?.(location.href,false);
    }
  }catch(err){
    const message=err instanceof Error?err.message:'Inventory could not be updated.';
    if(ownsDialog())showError(message);else toast(message,true);
  }finally{
    form.dataset.saving='';
    if(submit instanceof HTMLButtonElement){submit.disabled=false;submit.textContent='Save inventory change';}
  }
});
if(bulkDialog instanceof HTMLDialogElement){const bulkForm=bulkDialog.querySelector('[data-inventory-bulk-form]'),bulkError=bulkDialog.querySelector('[data-inventory-bulk-error]');const showBulk=(msg='')=>{if(bulkError instanceof HTMLElement){bulkError.textContent=msg;bulkError.hidden=!msg;}};const close=()=>{bulkDialog.close();document.body.classList.remove('ui-modal-open');showBulk('');};bulkDialog.querySelectorAll('[data-inventory-bulk-close]').forEach(btn=>btn.addEventListener('click',close));bulkDialog.addEventListener('cancel',()=>document.body.classList.remove('ui-modal-open'));bulkForm?.addEventListener('submit',async e=>{e.preventDefault();showBulk('');const submit=bulkForm.querySelector('button[type="submit"]');if(submit instanceof HTMLButtonElement){submit.disabled=true;submit.textContent='Saving…';}try{const fd=new FormData(bulkForm);fd.set('_ajax','inventory');const res=await fetch(bulkForm.getAttribute('action')||'inventory.php',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:fd});const data=await res.json().catch(()=>null);if(!res.ok||!data?.ok)throw new Error(data?.message||`Bulk inventory could not be saved (HTTP ${res.status}).`);(data.variants||[]).forEach(updateVariant);updateSummary(data.summary);toast(data.message||'Bulk inventory updated.');bulkForm.reset();close();refreshView?.(location.href,false);}catch(err){showBulk(err instanceof Error?err.message:'Bulk inventory could not be saved.');}finally{if(submit instanceof HTMLButtonElement){submit.disabled=false;submit.textContent='Save bulk inventory';}}});}
}; // initInventory
initInventory();

// One stable document-level navigation controller. Only inventory-internal links
// are intercepted; links to Orders, Returns, Catalog, etc. still navigate normally.
let pending=null;
const isInventoryUrl=(url)=>url.origin===location.origin && /\/admin\/inventory(?:\.php)?\/?$/.test(url.pathname);
const loadView=async(raw,record=true)=>{
 const target=new URL(raw,location.href);
 if(!isInventoryUrl(target)){location.assign(target.href);return;}
 const workspace=document.querySelector('[data-inventory-workspace]');
 if(!workspace){location.assign(target.href);return;}
 pending?.abort(); const ctrl=new AbortController();pending=ctrl;
 workspace.setAttribute('aria-busy','true');
 const status=workspace.querySelector('[data-inventory-load-status]');if(status)status.hidden=false;
 try{
  const response=await fetch(target.href,{credentials:'same-origin',redirect:'follow',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},signal:ctrl.signal});
  if(!response.ok)throw new Error(`Inventory request returned HTTP ${response.status}`);
  const doc=new DOMParser().parseFromString(await response.text(),'text/html');
  const fresh=doc.querySelector('[data-inventory-workspace]');
  // A login redirect or malformed response must never silently blank the panel.
  if(!fresh)throw new Error('Inventory panel was not found in the response');
  if(ctrl.signal.aborted)return;
  workspace.replaceWith(fresh);
  if(record)history.pushState({inventory:true},'',target.href);
  initInventory();
  if(record && target.hash)fresh.querySelector(target.hash)?.scrollIntoView({block:'start',behavior:'instant'});
 }catch(err){
  if(ctrl.signal.aborted)return;
  console.error('Inventory in-page navigation failed:',err);
  // Genuine route, auth or server errors get a complete navigation for diagnosis.
  location.assign(target.href);
 }finally{
  if(pending===ctrl)pending=null;
  workspace.removeAttribute('aria-busy');
  if(status)status.hidden=true;
 }
};
refreshView=loadView;
document.addEventListener('click',e=>{
 if(e.defaultPrevented||e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;
 const anchor=e.target instanceof Element?e.target.closest('[data-inventory-workspace] a[href]'):null;
 if(!anchor||anchor.hasAttribute('download')||anchor.target&&anchor.target!=='_self')return;
 if(anchor.matches('[data-inventory-open-adjust],[data-inventory-open-bulk]'))return;
 const target=new URL(anchor.getAttribute('href'),location.href);
 if(!isInventoryUrl(target))return;
 e.preventDefault();loadView(target.href);
});
document.addEventListener('submit',e=>{
 const form=e.target;if(!(form instanceof HTMLFormElement)||!form.closest('[data-inventory-workspace]')||form.method.toLowerCase()!=='get')return;
 const target=new URL(form.getAttribute('action')||location.href,location.href);
 if(!isInventoryUrl(target))return;
 e.preventDefault();for(const [key,value] of new FormData(form))if(typeof value==='string'&&value!=='')target.searchParams.set(key,value);
 target.hash='inventory-results';loadView(target.href);
});
window.addEventListener('popstate',()=>{const target=new URL(location.href);if(isInventoryUrl(target))loadView(target.href,false);});
})();

/* Repair 16: metric links are regular navigations to an explicit stock view.
   Unlike partial DOM swaps, these always initialize the right server-rendered
   stock controls, adjustment dialogs and stock-filter state. */

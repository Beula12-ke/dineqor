// Waiter service desk: table orders are priced and tenant-checked by the server.
(() => {
  const $ = id => document.getElementById(id);
  const tablesEl=$('serviceTables'), readyEl=$('readyList'), productsEl=$('serviceProducts');
  if (!tablesEl) return;
  let data=null, activeCategory=0, cart={};
  const statusLabels={available:'Available',ordering:'Ordering',occupied:'Occupied',preparing:'Preparing',ready:'Ready',payment_pending:'Payment due',cleaning:'Needs reset',closed:'Closed'};
  const tableName=t=>t.label || `Table ${t.table_number}`;
  const lineKey=(pid,vid)=>`${pid}:${vid || 0}`;

  function drawTables(){
    const selectable=(data.tables || []).filter(t=>!['closed','cleaning'].includes(t.status));
    $('serviceTable').innerHTML=selectable.map(t=>`<option value="${Number(t.id)}">${esc(tableName(t))}${t.section?` · ${esc(t.section)}`:''}</option>`).join('') || '<option value="">No open tables</option>';
    const free=selectable.filter(t=>Number(t.open_orders)===0 && t.status==='available').length;
    const active=selectable.length-free;
    $('serviceSummary').innerHTML=`<span><b>${selectable.length}</b> tables</span><span><b>${active}</b> in service</span><span><b>${free}</b> available</span>`;
    tablesEl.innerHTML=(data.tables || []).map(t=>{
      const count=Number(t.open_orders), state=count ? 'active' : (t.status==='cleaning' ? 'reset' : '');
      const transferTargets=selectable.filter(other=>Number(other.id)!==Number(t.id));
      return `<article class="service-table ${state}"><div class="service-table-top"><span class="service-table-mark">${esc(String(t.table_number))}</span><span class="service-table-status ${state}">${esc(count?`${count} open ${count===1?'order':'orders'}`:(statusLabels[t.status]||t.status))}</span></div><h3>${esc(tableName(t))}</h3><p>${esc(t.section || 'Dining room')} · ${Number(t.capacity)} seats</p>${count?`<div class="service-table-actions"><label class="sr" for="transfer-${Number(t.id)}">Move orders to table</label><select id="transfer-${Number(t.id)}" class="service-transfer-target">${transferTargets.map(other=>`<option value="${Number(other.id)}">Move to ${esc(tableName(other))}</option>`).join('')}</select><button type="button" class="btn sm ghost" data-action="transfer" data-table="${Number(t.id)}" ${transferTargets.length?'':'disabled'}>Transfer</button></div>`:t.status!=='available'&&t.status!=='closed'?`<button class="btn sm ghost service-reset" type="button" data-action="close" data-table="${Number(t.id)}">Reset table</button>`:''}</article>`;
    }).join('') || '<div class="inventory-empty"><b>No tables yet</b><p>Add tables in Tables & QR before taking waiter orders.</p></div>';
  }

  function drawReady(){
    const orders=data.ready_orders || [];
    $('readyCount').textContent=orders.length;
    readyEl.innerHTML=orders.length?orders.map(o=>`<article class="ready-ticket"><div class="ready-ticket-main"><div class="ready-ticket-title"><b>#${esc(String(o.order_number).split('-').pop())} · ${esc(o.table_label||`Table ${o.table_number}`)}</b><small>${esc(o.customer_name||'Guest')} · ${esc(new Date(String(o.created_at).replace(' ','T')).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}))}</small></div><ul>${(o.items||[]).map(i=>`<li><b>${Number(i.qty)}×</b> ${esc(i.name)}${i.variation?` <small>(${esc(i.variation)})</small>`:''}${i.addons?.length?`<small>+ ${i.addons.map(esc).join(', ')}</small>`:''}</li>`).join('')}</ul>${o.special_instructions?`<p class="ready-note">Kitchen note: ${esc(o.special_instructions)}</p>`:''}</div><button class="btn sm" type="button" data-action="serve" data-order="${Number(o.id)}">Mark served</button></article>`).join(''):'<div class="inventory-empty compact"><b>No ready table orders</b><p>Kitchen tickets for dine-in tables appear here when ready.</p></div>';
  }

  function drawCategories(){
    $('serviceCategories').innerHTML=['<button type="button" class="cat-chip'+(!activeCategory?' on':'')+'" data-category="0">All</button>',...(data.categories||[]).map(c=>`<button type="button" class="cat-chip${activeCategory===Number(c.id)?' on':''}" data-category="${Number(c.id)}">${esc(c.name)}</button>`)].join('');
  }

  function drawProducts(){
    const term=$('serviceSearch').value.trim().toLowerCase();
    const filtered=(data.products||[]).filter(p=>(!activeCategory||p.category_id===activeCategory)&&(!term||p.name.toLowerCase().includes(term)));
    productsEl.innerHTML=filtered.map(p=>`<article class="service-product"><div><b>${esc(p.name)}</b><small>${money(p.price)}</small></div>${p.variations?.length>1?`<select class="service-variation" data-product="${p.id}" aria-label="Options for ${esc(p.name)}">${p.variations.map(v=>`<option value="${v.id}">${esc(v.name)}${Number(v.delta)?` (+${money(v.delta)})`:''}</option>`).join('')}</select>`:''}<button type="button" class="btn sm ghost" data-add="${p.id}">Add</button></article>`).join('')||'<div class="inventory-empty compact"><b>No menu items found</b><p>Try a different search or category.</p></div>';
  }

  function drawCart(){
    const lines=Object.entries(cart); $('serviceCartLines').innerHTML=lines.length?lines.map(([key,line])=>`<div class="service-cart-line"><span><b>${esc(line.name)}</b><small>${money(line.unit)}</small></span><div class="qty"><button type="button" data-dec="${esc(key)}" aria-label="Remove one">−</button>${line.qty}<button type="button" data-inc="${esc(key)}" aria-label="Add one">+</button></div><b>${money(line.qty*line.unit)}</b></div>`).join(''):'<div class="inventory-empty compact"><b>No items yet</b><p>Add menu items to get started.</p></div>';
    $('serviceTotal').textContent=money(lines.reduce((sum,[,line])=>sum+line.qty*line.unit,0));
  }

  function addProduct(product){
    const variationSelect=productsEl.querySelector(`.service-variation[data-product="${product.id}"]`);
    const variationId=variationSelect?Number(variationSelect.value):(product.variations?.[0]?.id||null);
    const variation=product.variations?.find(v=>Number(v.id)===variationId);
    const key=lineKey(product.id,variationId), name=product.name+(variation?` (${variation.name})`:''), unit=Number(product.price)+(variation?Number(variation.delta):0);
    if(cart[key]) cart[key].qty++; else cart[key]={product_id:Number(product.id),variation_id:variationId,name,unit,qty:1};
    drawCart();
  }

  async function load(){
    try{const result=await api('staff/service.php');if(result.status===401||result.status===403){location.href=BASE+'/auth/login.php';return;}if(!result.ok)throw new Error(result.error||'Could not load service desk.');data=result;drawTables();drawReady();drawCategories();drawProducts();productsEl.setAttribute('aria-busy','false');}
    catch(error){$('serviceSummary').innerHTML=`<span class="service-error">${esc(error.message||'Service desk is unavailable.')}</span>`;}
  }

  async function action(body,button){
    if(button)button.disabled=true;
    try{const result=await api('staff/service.php',{method:'POST',body});if(!result.ok){toast(result.error||'Could not complete that action.');return false;}toast(result.message||'Saved.');await load();return true;}
    catch{toast('Could not reach the server. Try again.');return false;}
    finally{if(button)button.disabled=false;}
  }

  productsEl.addEventListener('click',event=>{const button=event.target.closest('[data-add]');if(!button)return;const product=data.products.find(p=>Number(p.id)===Number(button.dataset.add));if(product)addProduct(product);});
  $('serviceCategories').addEventListener('click',event=>{const button=event.target.closest('[data-category]');if(!button)return;activeCategory=Number(button.dataset.category);drawCategories();drawProducts();});
  $('serviceSearch').addEventListener('input',debounce(drawProducts,120));
  $('serviceCartLines').addEventListener('click',event=>{const button=event.target.closest('[data-inc],[data-dec]');if(!button)return;const key=button.dataset.inc||button.dataset.dec;if(!cart[key])return;cart[key].qty+=button.dataset.inc?1:-1;if(cart[key].qty<1)delete cart[key];drawCart();});
  readyEl.addEventListener('click',event=>{const button=event.target.closest('[data-action="serve"]');if(button)action({action:'serve',order_id:Number(button.dataset.order)},button);});
  tablesEl.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(!button)return;const tableId=Number(button.dataset.table);if(button.dataset.action==='close'){if(!confirm('Reset this table to available?'))return;action({action:'close_table',table_id:tableId},button);}else{const select=button.closest('.service-table').querySelector('.service-transfer-target');action({action:'transfer_table',from_table_id:tableId,to_table_id:Number(select.value)},button);}});
  $('serviceSend').addEventListener('click',async event=>{
    const items=Object.values(cart).map(line=>({product_id:line.product_id,variation_id:line.variation_id,qty:line.qty}));
    if(!items.length){$('serviceMessage').className='msg error';$('serviceMessage').textContent='Add at least one menu item.';return;}
    const button=event.currentTarget;button.disabled=true;$('serviceMessage').className='msg';$('serviceMessage').textContent='';
    const saved=await action({action:'create_order',table_id:Number($('serviceTable').value),customer_name:$('serviceGuest').value,customer_phone:$('servicePhone').value,instructions:$('serviceInstructions').value,items},button);
    if(saved){cart={};$('serviceGuest').value='';$('servicePhone').value='';$('serviceInstructions').value='';drawCart();$('serviceMessage').className='msg ok';$('serviceMessage').textContent='Order sent to the kitchen.';}
  });
  $('serviceRefresh').addEventListener('click',load);
  drawCart();load();setInterval(load,10000);
})();

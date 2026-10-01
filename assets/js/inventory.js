(() => {
  const $ = id => document.getElementById(id);
  const ingredientsEl=$('inventoryIngredients'), movementsEl=$('inventoryMovements'), suppliersEl=$('inventorySuppliers'), purchasesEl=$('inventoryPurchases');
  const dialogs=[...document.querySelectorAll('.inventory-dialog')];
  const number = value => Number(value || 0).toLocaleString('en-KE',{maximumFractionDigits:3});
  const money = value => 'KSh ' + Number(value || 0).toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});
  let state={ingredients:[],suppliers:[],movements:[],purchases:[],products:[],recipes:[],report:{rows:[]},metrics:{}};

  const openDialog = id => $(id)?.showModal();
  const closeDialog = button => button.closest('dialog')?.close();
  document.querySelectorAll('[data-open-dialog]').forEach(button=>button.addEventListener('click',()=>{
    if(button.dataset.openDialog==='purchaseDialog'&&!state.ingredients.length){toast('Add an ingredient before recording a purchase.');openDialog('ingredientDialog');return;}
    openDialog(button.dataset.openDialog);
  }));
  document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>closeDialog(button)));
  dialogs.forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();}));

  function render() {
    $('metricIngredients').textContent=number(state.metrics.ingredients);
    $('metricLow').textContent=number(state.metrics.low_stock);
    $('metricValue').textContent=money(state.metrics.stock_value);
    $('metricLow').classList.toggle('inventory-low-number',Number(state.metrics.low_stock)>0);
    ingredientsEl.innerHTML=state.ingredients.length?`<table class="inventory-table"><thead><tr><th>Ingredient</th><th>In stock</th><th>Minimum</th><th>Unit cost</th><th>Status</th><th>Actions</th></tr></thead><tbody>${state.ingredients.map(item=>{
      const low=Number(item.current_stock)<=Number(item.min_stock);
      return `<tr><td><b>${esc(item.name)}</b><small>${esc(item.unit)} · Ingredient #${Number(item.id)}</small></td><td class="inventory-quantity">${number(item.current_stock)} <small>${esc(item.unit)}</small></td><td>${number(item.min_stock)} <small>${esc(item.unit)}</small></td><td>${money(item.cost_per_unit)}</td><td><span class="inventory-status ${low?'low':'good'}">${low?'Low stock':'In stock'}</span></td><td><div class="inventory-row-actions"><button type="button" data-inventory-action="edit" data-id="${Number(item.id)}">Edit</button><button type="button" data-inventory-action="adjust" data-id="${Number(item.id)}">Adjust</button><button type="button" data-inventory-action="waste" data-id="${Number(item.id)}">Waste</button></div></td></tr>`;
    }).join('')}</tbody></table>`:'<div class="inventory-empty"><span>◩</span><b>No ingredients yet</b><p>Add the ingredients your kitchen uses to start tracking stock.</p><button class="btn sm" type="button" data-open-dialog="ingredientDialog" id="ingredientQuickAdd">+ Add first ingredient</button></div>';
    movementsEl.innerHTML=state.movements.length?state.movements.map(m=>`<article class="inventory-activity"><span class="movement-icon ${m.quantity<0?'out':'in'}">${m.quantity<0?'↘':'↗'}</span><span class="activity-copy"><b>${esc(m.ingredient_name)}</b><small>${esc(m.type.replace('_',' '))}${m.notes?' · '+esc(m.notes):''} · ${esc(m.staff_name||'Team')}</small></span><span class="movement-qty ${m.quantity<0?'negative':'positive'}">${m.quantity<0?'−':'+'}${number(Math.abs(Number(m.quantity)))} ${esc(m.unit)}</span></article>`).join(''):'<div class="inventory-empty compact"><b>No stock movements yet</b><p>Purchases and stock changes will appear here.</p></div>';
    suppliersEl.innerHTML=state.suppliers.length?state.suppliers.map(s=>`<article class="inventory-supplier"><span class="supplier-avatar">${esc((s.name||'S').slice(0,1).toUpperCase())}</span><span><b>${esc(s.name)}</b><small>${esc(s.phone||s.email||'Supplier')}</small></span></article>`).join(''):'<div class="inventory-empty compact"><b>No suppliers added</b><p>Add supplier details when you’re ready.</p></div>';
    purchasesEl.innerHTML=state.purchases.length?`<div class="inventory-purchase-list">${state.purchases.map(p=>`<article class="inventory-purchase"><span class="purchase-date">${esc(p.purchased_at||p.created_at.slice(0,10))}</span><span class="activity-copy"><b>${esc(p.supplier_name||'Unspecified supplier')}</b><small>${number(p.item_count)} line items${p.notes?' · '+esc(p.notes):''}</small></span><span class="customer-order-total">${money(p.total_amount)}</span></article>`).join('')}</div>`:'<div class="inventory-empty compact"><b>No purchases recorded</b><p>Received purchases will appear here.</p></div>';
    const recipeGroups=new Map();state.recipes.forEach(r=>{const key=Number(r.product_id);if(!recipeGroups.has(key))recipeGroups.set(key,{name:r.product_name,items:[]});recipeGroups.get(key).items.push(r);});
    $('inventoryRecipes').innerHTML=recipeGroups.size?[...recipeGroups.entries()].map(([id,group])=>`<article class="inventory-recipe"><span><b>${esc(group.name)}</b><small>${group.items.map(i=>`${number(i.quantity)} ${esc(i.unit)} ${esc(i.ingredient_name)}`).join(' · ')}</small></span><div><button type="button" data-recipe-edit="${id}">Edit</button><button type="button" data-recipe-clear="${id}">Remove</button></div></article>`).join(''):'<div class="inventory-empty compact"><b>No recipes yet</b><p>Add recipes to deduct ingredient stock automatically when menu items are ordered.</p></div>';
    $('recipeProduct').innerHTML='<option value="">Choose a menu item</option>'+state.products.map(p=>`<option value="${Number(p.id)}">${esc(p.name)}</option>`).join('');
    const reportRows=state.report?.rows||[];
    $('inventoryReport').innerHTML=reportRows.length?`<div class="inventory-report-scroll"><table class="inventory-table"><thead><tr><th>Ingredient</th><th>Purchased</th><th>Used</th><th>Waste</th><th>Adjustments</th><th>On hand</th><th>Est. use cost</th></tr></thead><tbody>${reportRows.map(r=>{const useCost=(Number(r.used)+Number(r.wasted))*Number(r.cost_per_unit);return `<tr><td><b>${esc(r.name)}</b><small>${esc(r.unit)}</small></td><td>${number(r.purchased)} ${esc(r.unit)}</td><td class="inventory-quantity">${number(r.used)} ${esc(r.unit)}</td><td>${number(r.wasted)} ${esc(r.unit)}</td><td>${number(r.adjusted)} ${esc(r.unit)}<small>Returns ${number(r.returned)}</small></td><td>${number(r.current_stock)} ${esc(r.unit)}${Number(r.current_stock)<=Number(r.min_stock)?'<small class="inventory-low-number">Low stock</small>':''}</td><td>${money(useCost)}</td></tr>`;}).join('')}</tbody></table></div>`:'<div class="inventory-empty compact"><b>No ingredients to report</b><p>Add ingredients to see purchases, usage and waste for this period.</p></div>';
    $('reportEstimateNote').textContent=`${state.report?.from||''} to ${state.report?.to||''} · Usage cost is estimated using current ingredient costs.`;
    const supplierSelect=$('purchaseSupplier');
    supplierSelect.innerHTML='<option value="0">No supplier selected</option>'+state.suppliers.map(s=>`<option value="${Number(s.id)}">${esc(s.name)}</option>`).join('');
  }

  async function load(from=$('reportFrom').value,to=$('reportTo').value) {
    ingredientsEl.setAttribute('aria-busy','true');
    try {
      const qs=new URLSearchParams({from,to});const result=await api('restaurant/inventory.php?'+qs.toString());
      if(!result.ok) throw new Error(result.error||'Could not load inventory.');
      state=result; render(); ingredientsEl.setAttribute('aria-busy','false');
    } catch(error) {
      ingredientsEl.setAttribute('aria-busy','false');
      ingredientsEl.innerHTML=`<div class="inventory-empty"><b>Inventory couldn’t load</b><p>${esc(error.message)}</p><button class="link" id="retryInventory" type="button">Try again</button></div>`;
      $('retryInventory').onclick=load;
    }
  }
  $('inventoryRefresh').addEventListener('click',()=>load());
  const localToday=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,10);
  $('reportTo').value=localToday;$('reportFrom').value=localToday.slice(0,8)+'01';
  $('inventoryReportFilter').addEventListener('submit',event=>{event.preventDefault();load($('reportFrom').value,$('reportTo').value);});

  function recipeRow(item={}) {
    const row=document.createElement('div');row.className='recipe-ingredient-row';
    row.innerHTML=`<div class="setting-field"><label>Ingredient</label><select name="ingredient_id" required><option value="">Choose ingredient</option>${state.ingredients.map(i=>`<option value="${Number(i.id)}">${esc(i.name)} (${esc(i.unit)})</option>`).join('')}</select></div><div class="setting-field"><label>Quantity per item</label><input name="quantity" type="number" min="0.001" max="999999999.999" step="0.001" required></div><button type="button" class="purchase-remove" aria-label="Remove ingredient">×</button>`;
    if(item.ingredient_id){row.querySelector('select').value=item.ingredient_id;row.querySelector('[name=quantity]').value=Number(item.quantity).toFixed(3);}
    row.querySelector('.purchase-remove').onclick=()=>{row.remove();if(!$('recipeIngredientRows').children.length)$('recipeIngredientRows').append(recipeRow());};return row;
  }
  function resetRecipe(productId=0){const f=$('recipeForm');f.reset();f.querySelector('[data-form-message]').textContent='';$('recipeIngredientRows').replaceChildren(recipeRow());$('recipeTitle').textContent='Add menu recipe';$('recipeProduct').disabled=false;if(productId){$('recipeProduct').value=String(productId);$('recipeTitle').textContent='Edit menu recipe';$('recipeProduct').disabled=true;const rows=state.recipes.filter(r=>Number(r.product_id)===Number(productId));$('recipeIngredientRows').replaceChildren(...(rows.length?rows.map(recipeRow):[recipeRow()]));}}
  $('addRecipeIngredient').addEventListener('click',()=> $('recipeIngredientRows').append(recipeRow()));
  document.querySelector('[data-open-dialog="recipeDialog"]').addEventListener('click',()=>{resetRecipe();if(!state.products.length){toast('Add a menu item before creating a recipe.');return;}openDialog('recipeDialog');});
  $('inventoryRecipes').addEventListener('click',async event=>{
    const edit=event.target.closest('[data-recipe-edit]');if(edit){resetRecipe(Number(edit.dataset.recipeEdit));openDialog('recipeDialog');return;}
    const clear=event.target.closest('[data-recipe-clear]');if(clear&&confirm('Remove this recipe? Orders will stop using ingredient stock for this menu item.')){try{const result=await api('restaurant/inventory.php',{method:'POST',body:{action:'clear_recipe',product_id:Number(clear.dataset.recipeClear)}});if(!result.ok)throw new Error(result.error||'Could not remove recipe.');toast(result.message);await load();}catch(error){toast(error.message);}}
  });

  function purchaseRow() {
    const row=document.createElement('div'); row.className='purchase-item-row';
    row.innerHTML=`<div class="setting-field"><label>Ingredient</label><select name="ingredient_id" required><option value="">Choose ingredient</option>${state.ingredients.map(i=>`<option value="${Number(i.id)}">${esc(i.name)} (${esc(i.unit)})</option>`).join('')}</select></div><div class="setting-field"><label>Quantity</label><input name="quantity" type="number" min="0.001" step="0.001" required></div><div class="setting-field"><label>Cost per unit (KSh)</label><input name="unit_cost" type="number" min="0" step="0.01" value="0" required></div><button type="button" class="purchase-remove" aria-label="Remove item">×</button>`;
    row.querySelector('.purchase-remove').onclick=()=>{row.remove();if(!$('purchaseItemRows').children.length) $('purchaseItemRows').append(purchaseRow());};
    return row;
  }
  function resetPurchase() {
    $('purchaseForm').reset(); $('purchaseDate').value=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,10);
    $('purchaseItemRows').replaceChildren(purchaseRow()); $('purchaseForm').querySelector('[data-form-message]').textContent='';
  }
  $('addPurchaseItem').addEventListener('click',()=> $('purchaseItemRows').append(purchaseRow()));
  $('purchaseItemRows').addEventListener('change',event=>{
    if(!event.target.matches('select[name="ingredient_id"]'))return;
    const item=state.ingredients.find(i=>Number(i.id)===Number(event.target.value));
    if(item)event.target.closest('.purchase-item-row').querySelector('[name="unit_cost"]').value=Number(item.cost_per_unit||0).toFixed(2);
  });
  document.querySelector('[data-open-dialog="purchaseDialog"]').addEventListener('click',()=>setTimeout(resetPurchase));

  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-open-dialog="ingredientDialog"]'); if(trigger){resetIngredient();if(trigger.id==='ingredientQuickAdd')openDialog('ingredientDialog');}
    const action=event.target.closest('[data-inventory-action]'); if(!action)return;
    const item=state.ingredients.find(i=>Number(i.id)===Number(action.dataset.id));if(!item)return;
    if(action.dataset.inventoryAction==='edit'){
      resetIngredient();const form=$('ingredientForm');form.elements.id.value=item.id;form.elements.name.value=item.name;form.elements.unit.value=item.unit;form.elements.min_stock.value=item.min_stock;form.elements.cost_per_unit.value=item.cost_per_unit;
      form.querySelector('.ingredient-opening').hidden=true;$('ingredientTitle').textContent='Edit ingredient';openDialog('ingredientDialog');return;
    }
    const form=$('movementForm');form.reset();form.elements.ingredient_id.value=item.id;form.elements.action.value=action.dataset.inventoryAction;
    const waste=action.dataset.inventoryAction==='waste';$('movementTitle').textContent=waste?'Record waste':'Adjust stock';$('movementHelp').textContent=`${item.name} · current stock ${number(item.current_stock)} ${item.unit}${waste?' · enter quantity to remove':' · use a negative number to reduce stock'}`;
    $('movementQuantity').min=waste?'0.001':'';$('movementQuantity').value='';$('movementSubmit').textContent=waste?'Record waste':'Save adjustment';form.querySelector('[data-form-message]').textContent='';openDialog('movementDialog');
  });
  function resetIngredient(){const f=$('ingredientForm');f.reset();f.elements.id.value='';f.elements.initial_stock.value='0';f.querySelector('.ingredient-opening').hidden=false;f.querySelector('[data-form-message]').textContent='';$('ingredientTitle').textContent='Add ingredient';}
  document.querySelector('[data-open-dialog="supplierDialog"]').addEventListener('click',()=>{$('supplierForm').reset();$('supplierForm').querySelector('[data-form-message]').textContent='';});

  async function submitForm(form,action,body,success) {
    showFieldErrors(form);const msg=form.querySelector('[data-form-message]');const button=form.querySelector('[type="submit"]');button.disabled=true;msg.textContent='';
    try {const result=await api('restaurant/inventory.php',{method:'POST',body});button.disabled=false;
      if(!result.ok){msg.className='msg error';msg.textContent=result.error||'Could not save changes.';showFieldErrors(form,result.fields||{});return;}
      form.closest('dialog').close();toast(result.message||success);await load();
    } catch(error){button.disabled=false;msg.className='msg error';msg.textContent='Could not reach the server. Please try again.';}
  }
  $('ingredientForm').addEventListener('submit',event=>{event.preventDefault();const v=formData(event.currentTarget);submitForm(event.currentTarget,'save_ingredient',{action:'save_ingredient',id:Number(v.id)||0,name:v.name,unit:v.unit,initial_stock:v.initial_stock,min_stock:v.min_stock,cost_per_unit:v.cost_per_unit});});
  $('movementForm').addEventListener('submit',event=>{event.preventDefault();const v=formData(event.currentTarget);submitForm(event.currentTarget,v.action,{action:v.action,ingredient_id:Number(v.ingredient_id),quantity:v.quantity,notes:v.notes});});
  $('supplierForm').addEventListener('submit',event=>{event.preventDefault();const v=formData(event.currentTarget);submitForm(event.currentTarget,'add_supplier',{action:'add_supplier',name:v.name,phone:v.phone,email:v.email});});
  $('purchaseForm').addEventListener('submit',event=>{event.preventDefault();const form=event.currentTarget;const v=formData(form);const items=[...$('purchaseItemRows').querySelectorAll('.purchase-item-row')].map(row=>({ingredient_id:Number(row.querySelector('[name=ingredient_id]').value),quantity:row.querySelector('[name=quantity]').value,unit_cost:row.querySelector('[name=unit_cost]').value}));submitForm(form,'receive_purchase',{action:'receive_purchase',supplier_id:Number(v.supplier_id),purchased_at:v.purchased_at,notes:v.notes,items});});
  $('recipeForm').addEventListener('submit',event=>{event.preventDefault();const form=event.currentTarget;const ingredients=[...$('recipeIngredientRows').querySelectorAll('.recipe-ingredient-row')].map(row=>({ingredient_id:Number(row.querySelector('[name=ingredient_id]').value),quantity:row.querySelector('[name=quantity]').value}));submitForm(form,'save_recipe',{action:'save_recipe',product_id:Number($('recipeProduct').value),ingredients});});
  load();
})();

// Date-filtered restaurant reporting and CSV export.
(() => {
  const form=document.getElementById('reportFilters'); if(!form)return;
  const fmtDate=value=>new Date(`${value}T00:00:00`).toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});
  let report=null;
  const escapeCsv=value=>`"${String(value??'').replaceAll('"','""')}"`;

  function breakdown(rows,labelKey,extra=''){
    if(!rows?.length)return '<div class="report-empty">No data for this period.</div>';
    return `<div class="report-list">${rows.map(row=>`<div class="report-list-row"><span><b>${esc(String(row[labelKey]||'').replaceAll('_',' '))}</b>${extra?`<small>${esc(extra(row))}</small>`:''}</span><strong>${row.sales!==undefined?money(row.sales):Number(row.orders||0).toLocaleString()}</strong></div>`).join('')}</div>`;
  }

  function render(data){
    report=data;
    const s=data.summary;
    document.getElementById('reportMetrics').innerHTML=[
      ['Paid sales',money(s.paid_sales),'Paid orders only'],['Paid orders',Number(s.paid_orders).toLocaleString(),`${Number(s.total_orders).toLocaleString()} total orders`],
      ['Average paid order',money(s.average_paid),'Per paid order'],['Outstanding',money(s.outstanding),`${Number(s.cancelled_orders).toLocaleString()} cancelled`]
    ].map(([label,value,foot])=>`<article class="report-metric"><small>${label}</small><b>${value}</b><span>${foot}</span></article>`).join('');
    document.getElementById('reportRangeLabel').textContent=`${fmtDate(data.range.from)} – ${fmtDate(data.range.to)}`;
    const peak=Math.max(1,...data.daily.map(day=>Number(day.sales)));
    document.getElementById('dailySales').innerHTML=data.daily.map(day=>`<div class="daily-sale"><div class="daily-sale-value">${day.sales?esc(money(day.sales)):''}</div><div class="daily-sale-track"><i style="height:${Math.max(4,Math.round(Number(day.sales)/peak*100))}%"></i></div><b>${esc(new Date(`${day.date}T00:00:00`).toLocaleDateString(undefined,{weekday:'short'}))}</b><small>${Number(day.orders)} orders</small></div>`).join('');
    document.getElementById('orderTypeBreakdown').innerHTML=breakdown(data.order_types,'order_type',row=>`${Number(row.orders)} orders`);
    document.getElementById('paymentBreakdown').innerHTML=breakdown(data.payments,'method',row=>`${Number(row.orders)} payments`);
    document.getElementById('statusBreakdown').innerHTML=breakdown(data.statuses,'status',row=>`${Number(row.orders)} orders`);
    document.getElementById('topProducts').innerHTML=data.top_products.length?`<div class="top-products-table"><div><span>ITEM</span><span>QUANTITY</span><span>PAID SALES</span></div>${data.top_products.map((row,index)=>`<div><span><i>${index+1}</i><b>${esc(row.name)}</b></span><span>${Number(row.quantity).toLocaleString()}</span><b>${money(row.sales)}</b></div>`).join('')}</div>`:'<div class="report-empty">No paid items for this period.</div>';
  }

  async function load(){
    const values=new FormData(form); const from=values.get('from'),to=values.get('to');
    if(!from||!to)return;
    const message=document.getElementById('reportMessage');message.className='msg';message.textContent='Loading…';
    try{const result=await api(`restaurant/reports.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);if(!result.ok){message.className='msg error';message.textContent=result.error||'Could not load report.';return;}render(result);message.className='msg';message.textContent='';}
    catch{message.className='msg error';message.textContent='Could not reach the server. Try again.';}
  }

  form.addEventListener('submit',event=>{event.preventDefault();load();});
  document.getElementById('downloadReport').addEventListener('click',()=>{
    if(!report)return;
    const rows=[['Daily sales','Orders','Paid sales'],...report.daily.map(day=>[day.date,day.orders,day.sales]),[],['Top-selling item','Quantity','Paid sales'],...report.top_products.map(item=>[item.name,item.quantity,item.sales])];
    const csv=rows.map(row=>row.map(escapeCsv).join(',')).join('\r\n');const blob=new Blob([csv],{type:'text/csv;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=`dineqor-report-${report.range.from}-to-${report.range.to}.csv`;a.click();URL.revokeObjectURL(url);
  });
  const localDate=date=>`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
  const today=new Date(),from=new Date(today);from.setDate(today.getDate()-6);
  form.elements.from.value=localDate(from);form.elements.to.value=localDate(today);
  load();
})();

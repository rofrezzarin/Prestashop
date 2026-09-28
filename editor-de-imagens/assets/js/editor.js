(function(){
'use strict';
const VERSION='1.0.211';
const startupVersion=document.getElementById('dtfStartupVersion'),startupBox=document.getElementById('dtfStartup');if(startupVersion)startupVersion.textContent='Versão '+(startupBox&&startupBox.dataset.version?startupBox.dataset.version:VERSION);
const ROOT_SELECTOR='[data-dtf-uv-editor]';
if(window.PrintWayImageEditor&&window.PrintWayImageEditor.version===VERSION){
 window.PrintWayImageEditor.init();return;
}
let activeEditorRoot=null;

function initEditor(editorRoot){
 if(editorRoot.dataset.dtfInitialized)return;
 editorRoot.dataset.dtfInitialized='starting';
 const versionLabel=editorRoot.querySelector('.dtf-version'),serverVersion=editorRoot.dataset.dtfVersion,versionCheckUrl=editorRoot.dataset.dtfVersionUrl;
 const compareVersions=(a,b)=>{const pa=String(a).split('.').map(v=>parseInt(v,10)||0),pb=String(b).split('.').map(v=>parseInt(v,10)||0),length=Math.max(pa.length,pb.length);for(let i=0;i<length;i++){if((pa[i]||0)!==(pb[i]||0))return(pa[i]||0)>(pb[i]||0)?1:-1}return 0};
 const refreshUrl=()=>{const u=new URL(window.location.href);u.searchParams.set('dtf_refresh',Date.now());return u.toString()};
 const showVersionUpdate=availableVersion=>{if(!versionLabel||!availableVersion||compareVersions(availableVersion,VERSION)<=0)return;let link=versionLabel.querySelector('.dtf-version-update');if(!link){link=document.createElement('a');link.className='dtf-version-update';link.textContent='↓';link.addEventListener('click',e=>{e.preventDefault();window.location.replace(link.href)});versionLabel.append(' ',link)}link.href=refreshUrl();link.textContent='↓';link.title='Atualização disponível: versão '+availableVersion+' — clique para carregar';link.setAttribute('aria-label','Atualização disponível. Clique para carregar a versão '+availableVersion+'.');link.dataset.version=availableVersion};
 showVersionUpdate(serverVersion);
 const checkForUpdates=async()=>{if(!versionCheckUrl)return;try{const u=new URL(versionCheckUrl,window.location.href);u.searchParams.set('_dtf_version_check',Date.now());const response=await fetch(u.toString(),{cache:'no-store',credentials:'same-origin'});if(!response.ok)return;const payload=await response.json(),availableVersion=payload&&payload.success&&payload.data&&payload.data.version;if(availableVersion)showVersionUpdate(availableVersion)}catch(_){}};
 checkForUpdates();
 window.setInterval(checkForUpdates,10000);
 // Controls must belong to this instance, never to a nested shortcode in Help.
 const QA=s=>Array.from(editorRoot.querySelectorAll(s)).filter(el=>el.closest(ROOT_SELECTOR)===editorRoot);
 const Q=s=>QA(s)[0]||null;
 const $id=id=>Q('[id="'+id+'"]');

const fileInput=$id('fileInput')||$id('dtfFileInput');
const canvas=$id('dtfCanvas'), ctx=canvas.getContext('2d',{alpha:true,willReadFrequently:true});
const compareCanvas=$id('dtfCompareCanvas'), compareCtx=compareCanvas.getContext('2d',{alpha:true});
const viewport=$id('dtfViewport'), inner=$id('dtfInner'), innerScroll=$id('dtfInnerScroll');
const selection=$id('dtfSelection'), selectionLabel=$id('dtfSelectionLabel'), eraserCursor=$id('dtfEraserCursor'), badge=$id('dtfObjectBadge');
const marquee=$id('dtfMarquee');
const statusEl=$id('dtfStatus'), progressEl=$id('dtfProgress'), progressBar=$id('dtfProgressBar'), progressText=$id('dtfProgressText'), errorBox=$id('dtfError'), errorTitle=$id('dtfErrorTitle'), errorLog=$id('dtfErrorLog');
const compareOverlay=$id('dtfCompareOverlay');
const rulerTop=$id('dtfRulerTop'),rulerLeft=$id('dtfRulerLeft'),rulerCorner=$id('dtfRulerCorner');
let rulerMarksSignature='';
function updateRulers(){
 const vr=viewport.getBoundingClientRect(),ir=inner.getBoundingClientRect();
 const sheetLeft=ir.left-vr.left+viewport.scrollLeft,sheetTop=ir.top-vr.top+viewport.scrollTop;
 const extraMm=10,baseX=canvas.width/state.workWmm,baseY=canvas.height/state.workHmm,scaleX=baseX*state.zoom,scaleY=baseY*state.zoom;
 /* A régua superior acompanha somente a rolagem horizontal. A lateral
  * acompanha somente a vertical. scrollTop/scrollLeft compensam o eixo que
  * precisa permanecer visualmente fixo dentro do viewport rolável. */
 if(rulerTop){rulerTop.style.left=sheetLeft+'px';rulerTop.style.top=viewport.scrollTop+'px';rulerTop.style.width=Math.max(1,ir.width+extraMm*scaleX)+'px'}
 if(rulerLeft){rulerLeft.style.left=viewport.scrollLeft+'px';rulerLeft.style.top=sheetTop+'px';rulerLeft.style.height=Math.max(1,ir.height+extraMm*scaleY)+'px'}
 if(rulerCorner){rulerCorner.style.left=viewport.scrollLeft+'px';rulerCorner.style.top=viewport.scrollTop+'px'}
 const add=(el,max,vertical,scale)=>{if(!el)return;el.innerHTML='';for(let v=0;v<=max+0.001;v+=1){const s=document.createElement('span'),whole=Math.round(v),pos=v*scale,label=(max<=20||whole%10===0)?String(whole):'';if(label){const text=document.createElement('b');text.textContent=label;s.appendChild(text)}s.style[vertical?'top':'left']=pos+'px';s.className=whole%10===0?'major':'minor';s.setAttribute('aria-label',whole+' mm');el.appendChild(s)}};
 const signature=[state.workWmm,state.workHmm,state.dpi,state.zoom,scaleX,scaleY].join('|');
 if(signature!==rulerMarksSignature){rulerMarksSignature=signature;add(rulerTop,state.workWmm+extraMm,false,scaleX);add(rulerLeft,state.workHmm+extraMm,true,scaleY)}
 if(!guideDrag)renderCustomGuides();
}
viewport.addEventListener('scroll',()=>{updateRulers();updateEraserCursor()},{passive:true});
const ui={
 clickTol:$id('dtfClickTol'), clickTolValue:$id('dtfClickTolValue'),
 newProject:$id('dtfNewProject'), load:$id('dtfLoad'), openProject:$id('dtfOpenProject'), saveProject:$id('dtfSaveProject'), reset:$id('dtfReset'), original:$id('dtfOriginal'), workW:$id('dtfWorkW'), workH:$id('dtfWorkH'), applyWork:$id('dtfApplyWork'), bgColor:$id('dtfBgColor'), transparent:$id('dtfTransparent'), dpi:$id('dtfWorkDpi'),
 removeMode:$id('dtfRemoveMode'), tol:$id('dtfTol'), tolValue:$id('dtfTolValue'), tolMinus:$id('dtfTolMinus'), tolPlus:$id('dtfTolPlus'), pickColor:$id('dtfPickColor'), pickPreview:$id('dtfPickColorPreview'), edge:$id('dtfEdge'), edgeValue:$id('dtfEdgeValue'), dehalo:$id('dtfDehalo'), alignLeft:$id('dtfAlignLeft'),alignCenter:$id('dtfAlignCenter'),alignRight:$id('dtfAlignRight'),alignTop:$id('dtfAlignTop'),alignMiddle:$id('dtfAlignMiddle'),alignBottom:$id('dtfAlignBottom'),distributeH:$id('dtfDistributeH'),distributeV:$id('dtfDistributeV'),organize:$id('dtfOrganize'),
 undo:$id('dtfUndo'), redo:$id('dtfRedo'), undoMenuBtn:$id('dtfUndoMenuBtn'), redoMenuBtn:$id('dtfRedoMenuBtn'), undoMenu:$id('dtfUndoMenu'), redoMenu:$id('dtfRedoMenu'), duplicate:$id('dtfDuplicate')||{disabled:true,addEventListener:()=>{}}, del:$id('dtfDelete')||{disabled:true,addEventListener:()=>{}}, eraser:$id('dtfEraser'), restoreBrush:$id('dtfRestoreBrush'), brush:$id('dtfBrush')||{value:'32',addEventListener:()=>{}}, brushValue:$id('dtfBrushValue')||{textContent:'32'},
 selectedName:$id('dtfSelectedName'), objectTotal:$id('dtfObjectTotal'),objectSelected:$id('dtfObjectSelected'), center:$id('dtfCenterObject')||{disabled:true,addEventListener:()=>{}}, objX:$id('dtfObjX')||{value:'',disabled:true,addEventListener:()=>{}}, objY:$id('dtfObjY')||{value:'',disabled:true,addEventListener:()=>{}}, objW:$id('dtfObjW')||{value:'',disabled:true,addEventListener:()=>{}}, objH:$id('dtfObjH')||{value:'',disabled:true,addEventListener:()=>{}}, lockRatio:$id('dtfObjLock')||{checked:true}, zoomOut:$id('dtfZoomOut'), zoom100:$id('dtfZoom100'), zoomIn:$id('dtfZoomIn'), zoomFit:$id('dtfZoomFit'), compare:$id('dtfCompare'), compareRange:$id('dtfCompareRange'), compareValue:$id('dtfCompareValue'),
 pdfPage:$id('dtfPdfPage'), pdfDpi:$id('dtfPdfDpi'), pdfConvert:$id('dtfPdfConvert'),
 exportOpen:$id('dtfOpenExport'), exportModal:$id('dtfExportModal'), exportConfirm:$id('dtfExportConfirm'), exportCancel:$id('dtfExportCancel'), exportImageOptions:$id('dtfExportImageOptions'), exportFormatHint:$id('dtfExportFormatHint'), exportPreviewCanvas:$id('dtfExportPreviewCanvas'), exportPreviewEmpty:$id('dtfExportPreviewEmpty'), exportPreviewMeta:$id('dtfExportPreviewMeta'), exportPreviewProgress:$id('dtfExportPreviewProgress'), exportPreviewProgressBar:$id('dtfExportPreviewProgressBar'), exportPreviewProgressText:$id('dtfExportPreviewProgressText'), quality:$id('dtfQuality'), qualityValue:$id('dtfQualityValue'), exportAiEnhance:$id('dtfExportAiEnhance'), outputInfo:$id('dtfOutputInfo'), magicFill:$id('dtfMagicFill'), magicModal:$id('dtfMagicModal'), magicMessage:$id('dtfMagicMessage'), magicConfirm:$id('dtfMagicConfirm'), magicCancel:$id('dtfMagicCancel'), magicCutoutOnly:$id('dtfMagicCutoutOnly'), magicReview:$id('dtfMagicReview'), magicVariants:$id('dtfMagicVariants'), magicZoom:$id('dtfMagicZoom'), magicZoomImage:$id('dtfMagicZoomImage'), magicZoomLabel:$id('dtfMagicZoomLabel'), magicUpload:$id('dtfMagicUpload'), magicUploadButton:$id('dtfMagicUploadButton'), magicUploadWrap:$id('dtfMagicUploadWrap'), magicSources:$id('dtfMagicSources'), magicCapacity:$id('dtfMagicCapacity'), magicAutoHeight:$id('dtfMagicAutoHeight'), magicAutoHeightWrap:$id('dtfMagicAutoHeightWrap'), magicProgress:$id('dtfMagicProgress'), magicProgressBar:$id('dtfMagicProgressBar'), magicProgressText:$id('dtfMagicProgressText'), magicSteps:$id('dtfMagicSteps'), magicLayoutPreview:$id('dtfMagicLayoutPreview'), magicLayoutCanvas:$id('dtfMagicLayoutCanvas'), magicLayoutMeta:$id('dtfMagicLayoutMeta'), copyError:$id('dtfCopyError')
};
const unitSelect=$id('dtfUnit');
const fitHeightButton=document.createElement('button');fitHeightButton.type='button';fitHeightButton.className='dtf-tool dtf-align-btn';fitHeightButton.title='Ajusta a largura e a altura da área de trabalho ao limite final das imagens';fitHeightButton.setAttribute('aria-label','Ajustar a imagem: ajustar a área de trabalho às imagens');fitHeightButton.innerHTML='<span class="dtf-icon">⤢</span><span>Ajustar a imagem</span>';if(ui.organize&&ui.organize.parentElement)ui.organize.parentElement.appendChild(fitHeightButton);
fitHeightButton.addEventListener('click',()=>{const items=state.objects.filter(o=>o.visible);if(!items.length){setStatus('Nenhuma imagem para ajustar');return}const right=Math.max(...items.map(o=>o.x+o.w)),bottom=Math.max(...items.map(o=>o.y+o.h));if(right<=0||bottom<=0)return;pushHistory('Ajustar área de trabalho às imagens');state.workWmm=Math.max(10,right/state.dpi*25.4);state.workHmm=Math.max(10,bottom/state.dpi*25.4);try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:state.workWmm,h:state.workHmm,dpi:state.dpi}))}catch(_){}syncWorkspaceUI();render();setStatus('Área de trabalho ajustada à largura e à altura final das imagens')});
const clickRemove=$id('dtfClickRemove'),areaRemoveButton=$id('dtfAreaRemove'),globalColorRemoveButton=$id('dtfColorRemove');
const fillTool=$id('dtfFillTool'),fillColor=$id('dtfFillColor');
const lockMenu=document.createElement('button');lockMenu.type='button';lockMenu.id='dtfLockObject';lockMenu.textContent='Bloquear objeto';const canvasMenu=$id('dtfCanvasMenu');if(canvasMenu)canvasMenu.appendChild(lockMenu);
const inheritMenu=document.createElement('div');inheritMenu.className='dtf-properties-menu';const inheritBtn=document.createElement('button');inheritBtn.type='button';inheritBtn.textContent='Herança ▸';const inheritSub=document.createElement('div');inheritSub.className='dtf-properties-submenu';const inheritCopy=document.createElement('button');inheritCopy.type='button';inheritCopy.textContent='Copiar';const inheritPaste=document.createElement('button');inheritPaste.type='button';inheritPaste.textContent='Colar';inheritPaste.disabled=true;inheritSub.append(inheritCopy,inheritPaste);inheritMenu.append(inheritBtn,inheritSub);if(canvasMenu)canvasMenu.appendChild(inheritMenu);inheritCopy.onclick=()=>{const o=selected();if(o){propertyClipboard={w:o.w,h:o.h,opacity:o.opacity,locked:o.locked};inheritPaste.disabled=false;canvasMenu.classList.remove('show')}};inheritPaste.onclick=()=>{const o=selected();if(o&&propertyClipboard){pushHistory('Colar herança');o.w=Math.min(canvas.width,propertyClipboard.w);o.h=Math.min(canvas.height,propertyClipboard.h);o.opacity=propertyClipboard.opacity;o.locked=propertyClipboard.locked;render();canvasMenu.classList.remove('show')}};
const flipHOld=$id('dtfFlipH'),flipVOld=$id('dtfFlipV');if(flipHOld&&flipVOld&&canvasMenu){flipHOld.style.display='none';flipVOld.style.display='none';const inv=document.createElement('div');inv.className='dtf-invert-menu';const invBtn=document.createElement('button');invBtn.type='button';invBtn.textContent='Inverter ▸';const sub=document.createElement('div');sub.className='dtf-invert-submenu';const bh=document.createElement('button');bh.type='button';bh.textContent='Horizontal';const bv=document.createElement('button');bv.type='button';bv.textContent='Vertical';sub.append(bh,bv);inv.append(invBtn,sub);canvasMenu.appendChild(inv);bh.addEventListener('click',()=>{flipSelected(true);canvasMenu.classList.remove('show')});bv.addEventListener('click',()=>{flipSelected(false);canvasMenu.classList.remove('show')})}
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.dtf-user-guide')||state.tool!=='select')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(hit&&hit.locked){e.preventDefault();e.stopImmediatePropagation();setStatus('Objeto bloqueado — use o botão direito para desbloquear')}},{capture:true});
document.addEventListener('contextmenu',()=>{const o=selected();if(lockMenu){lockMenu.style.display=o?'block':'none';lockMenu.textContent=o&&o.locked?'Desbloquear objeto':'Bloquear objeto'}},{capture:true});
document.addEventListener('contextmenu',e=>{setTimeout(()=>{const m=$id('dtfCanvasMenu');if(!m||!m.classList.contains('show'))return;const r=m.getBoundingClientRect(),x=Math.min(e.clientX,window.innerWidth-r.width-8),y=Math.min(e.clientY,window.innerHeight-r.height-8);m.style.left=Math.max(8,x)+'px';m.style.top=Math.max(8,y)+'px'},0)},{capture:false});
viewport.addEventListener('contextmenu',e=>{const p=clientToWorkspace(e),o=p&&hitTest(p.x,p.y);if(o){if(!state.selectedIds.includes(o.id))selectObject(o,false);lockMenu.style.display='block';lockMenu.textContent=o.locked?'Desbloquear objeto':'Bloquear objeto'}else lockMenu.style.display='none'},{capture:false});
lockMenu.addEventListener('click',()=>{const o=selected();if(!o)return;pushHistory(o.locked?'Desbloquear objeto':'Bloquear objeto');o.locked=!o.locked;lockMenu.textContent=o.locked?'Desbloquear objeto':'Bloquear objeto';render();canvasMenu.classList.remove('show')});
const copyPropMenu=document.createElement('button'),pastePropMenu=document.createElement('button');copyPropMenu.textContent='Copiar propriedades';pastePropMenu.textContent='Colar propriedades';if(canvasMenu){canvasMenu.append(copyPropMenu,pastePropMenu);pastePropMenu.style.display='none'}copyPropMenu.onclick=()=>{const o=selected();if(o){propertyClipboard={w:o.w,h:o.h,opacity:o.opacity,locked:o.locked};pastePropMenu.style.display='block';canvasMenu.classList.remove('show')}};pastePropMenu.onclick=()=>{const o=selected();if(o&&propertyClipboard){pushHistory('Colar propriedades');o.w=Math.min(canvas.width,propertyClipboard.w);o.h=Math.min(canvas.height,propertyClipboard.h);o.opacity=propertyClipboard.opacity;o.locked=propertyClipboard.locked;render();canvasMenu.classList.remove('show')}};
let gridEnabled=false,objectSnapEnabled=false;const gridStep=()=>Math.max(1,physicalMmToPx(1));const snapGrid=v=>Math.round(v/gridStep())*gridStep();const snapGroup=document.createElement('div');snapGroup.className='dtf-group dtf-snap-group';snapGroup.innerHTML='<div class="dtf-caption">ALINHAR À</div><div class="dtf-tools"></div>';const snapTools=snapGroup.querySelector('.dtf-tools');const gridButton=document.createElement('button');gridButton.type='button';gridButton.className='dtf-tool dtf-align-btn';gridButton.title='Alinhar à grade de 1 mm';gridButton.setAttribute('aria-label','Alinhar à grade de 1 mm');gridButton.innerHTML='<span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM9 3v18M15 3v18M3 9h18M3 15h18"/></svg></span><span>Grade</span>';const objectButton=document.createElement('button');objectButton.type='button';objectButton.className='dtf-tool dtf-align-btn';objectButton.title='Alinhar ao objeto mais próximo';objectButton.setAttribute('aria-label','Alinhar ao objeto mais próximo');objectButton.innerHTML='<span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h7v14H3zM14 5h7v14h-7zM10 12h4M12 10l2 2-2 2"/></svg></span><span>Objeto</span>';const guidesButton=document.createElement('button');guidesButton.type='button';guidesButton.className='dtf-tool dtf-align-btn';guidesButton.title='Mostrar margens de segurança, centro e linhas de corte';guidesButton.setAttribute('aria-label','Mostrar guias e margens de segurança');guidesButton.innerHTML='<span class="dtf-icon">⌗</span><span>Guias</span>';snapTools.append(gridButton,objectButton,guidesButton);const alignPanel=Q('[data-panel="alinhamentos"]');if(alignPanel)alignPanel.appendChild(snapGroup);gridButton.addEventListener('click',()=>{gridEnabled=!gridEnabled;inner.classList.toggle('dtf-grid',gridEnabled);gridButton.classList.toggle('active',gridEnabled);gridButton.setAttribute('aria-pressed',String(gridEnabled));if(gridEnabled)inner.style.setProperty('--dtf-grid-size',gridStep()+'px');else inner.style.removeProperty('--dtf-grid-size');setStatus(gridEnabled?'Grade ativa — encaixe a cada 1 mm':'Grade desativada')});objectButton.addEventListener('click',()=>{objectSnapEnabled=!objectSnapEnabled;objectButton.classList.toggle('active',objectSnapEnabled);objectButton.setAttribute('aria-pressed',String(objectSnapEnabled));setStatus(objectSnapEnabled?'Alinhamento por objeto ativo':'Alinhamento por objeto desativado')});let guidesEnabled=false;const guidesOverlay=$id('dtfGuidesOverlay');function updateGuidesOverlay(){if(!guidesOverlay)return;guidesOverlay.classList.toggle('show',guidesEnabled);if(!guidesEnabled)return;const inset=Math.min(canvas.width,canvas.height,physicalMmToPx(5));const safe=guidesOverlay.querySelector('.dtf-guide-safe');if(safe)safe.style.inset=inset+'px'}guidesButton.addEventListener('click',()=>{guidesEnabled=!guidesEnabled;guidesButton.classList.toggle('active',guidesEnabled);guidesButton.setAttribute('aria-pressed',String(guidesEnabled));updateGuidesOverlay();setStatus(guidesEnabled?'Guias ativas — margem de 5 mm, centro e linhas de corte visíveis':'Guias desativadas')});updateGuidesOverlay()

const state={
 workWmm:280,workHmm:100,dpi:300,bgColor:'#ffffff',transparent:true,
 objects:[],selectedId:null,selectedIds:[],zoom:1,panX:0,panY:0,space:false,drag:null,marquee:null,
 customGuides:[],selectedGuideId:null,
 tool:'select',brushSize:32,compare:false,comparePct:50,
 undo:[],redo:[],historyLock:false,historyLimit:22,
 pdf:null,pdfFileName:'documento',pdfPage:1,pdfDpi:300,
 logs:[],clipboard:[],intelligentVariantSets:{}
};
const ERASER_MIN=4,ERASER_MAX=180,MAX_PX=18000000;
try{const saved=JSON.parse(localStorage.getItem('printway_dtf_workspace')||'null');if(saved){state.workWmm=Number(saved.w)||state.workWmm;state.workHmm=Number(saved.h)||state.workHmm;state.dpi=Number(saved.dpi)||state.dpi}}catch(_){ }

function uid(){return 'obj_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,8)}
function clamp(v,a,b){return Math.max(a,Math.min(b,v))}
function mmToPx(mm){const u=unitSelect?unitSelect.value:'mm';const mmValue=u==='cm'?mm*10:u==='m'?mm*1000:u==='px'?mm*25.4/state.dpi:mm;return Math.max(1,Math.round((mmValue/25.4)*state.dpi))}
function physicalMmToPx(mm){return Math.max(1,Math.round((mm/25.4)*state.dpi))}
function pxToMm(px){return px*25.4/state.dpi}
function mmToUnit(mm){const u=unitSelect?unitSelect.value:'mm';return u==='cm'?mm/10:u==='m'?mm/1000:u==='px'?mm*state.dpi/25.4:mm}
function unitToMm(v){const u=unitSelect?unitSelect.value:'mm';return u==='cm'?v*10:u==='m'?v*1000:u==='px'?v*25.4/state.dpi:v}
function setStatus(t){statusEl.textContent=t}

/* Guias criadas pelas réguas. A posição é guardada em milímetros para não
 * mudar quando o DPI, o zoom ou o tamanho visual da página forem alterados. */
const customGuidesLayer=document.createElement('div');
customGuidesLayer.className='dtf-custom-guides';
customGuidesLayer.setAttribute('aria-label','Guias da página');
inner.appendChild(customGuidesLayer);
const guideDragPreview=document.createElement('div');
guideDragPreview.className='dtf-guide-drag-preview';
guideDragPreview.hidden=true;
document.body.appendChild(guideDragPreview);
let guideDrag=null;

function sanitizeCustomGuides(guides){
 const used=new Set();
 return (Array.isArray(guides)?guides:[]).map((guide,index)=>{
  const orientation=guide&&guide.orientation==='h'?'h':guide&&guide.orientation==='v'?'v':null;
  const raw=Number(guide&&guide.valueMm);
  if(!orientation||!Number.isFinite(raw))return null;
  const limit=orientation==='v'?state.workWmm:state.workHmm;
  let id=String(guide.id||('guide_'+Date.now().toString(36)+'_'+index));
  if(used.has(id))id+='_'+index;
  used.add(id);
  return {id,orientation,valueMm:clamp(raw,0,limit)};
 }).filter(Boolean);
}
function guideCanvasPosition(guide){return guide.valueMm/25.4*state.dpi}
function renderCustomGuides(){
 if(!customGuidesLayer)return;
 state.customGuides=sanitizeCustomGuides(state.customGuides);
 customGuidesLayer.innerHTML='';
 const zoom=Math.max(.01,Number(state.zoom)||1),hit=10/zoom,stroke=1/zoom;
 customGuidesLayer.style.setProperty('--dtf-guide-hit',hit+'px');
 customGuidesLayer.style.setProperty('--dtf-guide-stroke',stroke+'px');
 state.customGuides.forEach(guide=>{
  const line=document.createElement('button'),axis=guide.orientation==='v'?'vertical':'horizontal',value=Number(guide.valueMm.toFixed(2));
  line.type='button';line.className='dtf-user-guide dtf-user-guide-'+guide.orientation;
  line.classList.toggle('selected',guide.id===state.selectedGuideId);
  line.dataset.guideId=guide.id;
  line.setAttribute('aria-label','Guia '+axis+' em '+value+' milímetros');
  line.title='Guia '+axis+': '+value+' mm — arraste para mover; arraste para fora ou dê duplo clique para excluir';
  if(guide.orientation==='v')line.style.left=guideCanvasPosition(guide)+'px';else line.style.top=guideCanvasPosition(guide)+'px';
  line.addEventListener('pointerdown',event=>beginGuideDrag(guide.orientation,event,guide.id));
  line.addEventListener('dblclick',event=>{event.preventDefault();event.stopPropagation();removeCustomGuide(guide.id)});
  customGuidesLayer.appendChild(line);
 });
}
function updateSelectedGuideStyle(){customGuidesLayer.querySelectorAll('.dtf-user-guide').forEach(line=>line.classList.toggle('selected',line.dataset.guideId===state.selectedGuideId))}
function pointerGuideValue(event,orientation){
 const rect=canvas.getBoundingClientRect();
 if(!rect.width||!rect.height)return null;
 const inside=event.clientX>=rect.left&&event.clientX<=rect.right&&event.clientY>=rect.top&&event.clientY<=rect.bottom;
 const px=orientation==='v'?(event.clientX-rect.left)*canvas.width/rect.width:(event.clientY-rect.top)*canvas.height/rect.height;
 const limit=orientation==='v'?state.workWmm:state.workHmm;
 return {inside,valueMm:clamp(pxToMm(px),0,limit),rect};
}
function updateGuideDragPreview(event){
 if(!guideDrag)return;
 const position=pointerGuideValue(event,guideDrag.orientation);
 if(!position)return;
 guideDrag.last=position;
 guideDragPreview.hidden=false;
 guideDragPreview.className='dtf-guide-drag-preview dtf-guide-drag-preview-'+guideDrag.orientation+(position.inside?' valid':'');
 if(guideDrag.orientation==='v')guideDragPreview.style.left=event.clientX+'px';else guideDragPreview.style.top=event.clientY+'px';
 const axis=guideDrag.orientation==='v'?'X':'Y';
 setStatus('Posição da guia '+axis+': '+position.valueMm.toFixed(2)+' mm'+(position.inside?'':' — solte dentro da página'));
}
function beginGuideDrag(orientation,event,guideId=null){
 if(event.button!==0)return;
 event.preventDefault();event.stopPropagation();
 activeEditorRoot=editorRoot;
 if(state.tool!=='select'){deactivateSpecialTool(true);deactivateBrush()}
 state.selectedGuideId=guideId;
 guideDrag={orientation,guideId,pointerId:event.pointerId,last:null};
 document.documentElement.classList.add('dtf-guide-dragging','dtf-guide-dragging-'+orientation);
 updateSelectedGuideStyle();updateGuideDragPreview(event);
 try{event.currentTarget.setPointerCapture(event.pointerId)}catch(_){}
}
function finishGuideDrag(event,cancelled=false){
 if(!guideDrag||event.pointerId!==guideDrag.pointerId)return;
 const drag=guideDrag,position=pointerGuideValue(event,drag.orientation)||drag.last;
 guideDrag=null;guideDragPreview.hidden=true;guideDragPreview.className='dtf-guide-drag-preview';
 guideDragPreview.style.removeProperty('left');guideDragPreview.style.removeProperty('top');
 document.documentElement.classList.remove('dtf-guide-dragging','dtf-guide-dragging-h','dtf-guide-dragging-v');
 if(cancelled){renderCustomGuides();setStatus('Movimentação da guia cancelada');return}
 const existingIndex=drag.guideId?state.customGuides.findIndex(guide=>guide.id===drag.guideId):-1;
 if(!position||!position.inside){
  if(existingIndex>=0){pushHistory('Excluir guia');state.customGuides.splice(existingIndex,1);state.selectedGuideId=null;setStatus('Guia excluída')}
  else setStatus('Guia não adicionada — solte dentro da página');
  renderCustomGuides();return;
 }
 const valueMm=Number(position.valueMm.toFixed(3));
 let changed=false;
 if(existingIndex>=0){
  const guide=state.customGuides[existingIndex];
  if(Math.abs(guide.valueMm-valueMm)>.0005){pushHistory('Mover guia');guide.valueMm=valueMm;changed=true;setStatus('Guia movida para '+valueMm+' mm')}
  else setStatus('Guia mantida em '+valueMm+' mm');
  state.selectedGuideId=guide.id;
 }else{
  pushHistory('Adicionar guia');
  const guide={id:'guide_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,7),orientation:drag.orientation,valueMm};
  state.customGuides.push(guide);state.selectedGuideId=guide.id;
  changed=true;
  setStatus('Guia '+(drag.orientation==='v'?'vertical':'horizontal')+' adicionada em '+valueMm+' mm');
 }
 if(changed){setProjectDirty(true);renderCustomGuides()}else updateSelectedGuideStyle();
}
function removeCustomGuide(id){
 const index=state.customGuides.findIndex(guide=>guide.id===id);if(index<0)return false;
 pushHistory('Excluir guia');state.customGuides.splice(index,1);if(state.selectedGuideId===id)state.selectedGuideId=null;
 setProjectDirty(true);renderCustomGuides();setStatus('Guia excluída');return true;
}
function clearCustomGuides(){
 if(!state.customGuides.length){setStatus('Não há guias criadas para excluir');return}
 pushHistory('Excluir todas as guias');state.customGuides=[];state.selectedGuideId=null;setProjectDirty(true);renderCustomGuides();setStatus('Todas as guias foram excluídas');
}
if(rulerTop){rulerTop.setAttribute('aria-hidden','false');rulerTop.setAttribute('aria-label','Régua horizontal — arraste para criar uma guia horizontal');rulerTop.addEventListener('pointerdown',event=>beginGuideDrag('h',event))}
if(rulerLeft){rulerLeft.setAttribute('aria-hidden','false');rulerLeft.setAttribute('aria-label','Régua vertical — arraste para criar uma guia vertical');rulerLeft.addEventListener('pointerdown',event=>beginGuideDrag('v',event))}
document.addEventListener('pointermove',event=>{if(guideDrag&&event.pointerId===guideDrag.pointerId){event.preventDefault();updateGuideDragPreview(event)}},{capture:true});
document.addEventListener('pointerup',event=>finishGuideDrag(event,false),{capture:true});
document.addEventListener('pointercancel',event=>finishGuideDrag(event,true),{capture:true});
viewport.addEventListener('pointerdown',event=>{if(!event.target.closest('.dtf-user-guide')&&state.selectedGuideId){state.selectedGuideId=null;renderCustomGuides()}},{capture:true});

function progress(v,t){const p=clamp(Number(v)||0,0,100);progressEl.classList.add('show');progressBar.style.width=p+'%';progressText.textContent=(t||'')+' • v'+VERSION}
function finish(t){progressBar.style.width='100%';progressText.textContent=t||'';setTimeout(()=>progressEl.classList.remove('show'),700)}
function nextPaint(){return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))}
function log(msg,data){const entry='['+new Date().toLocaleTimeString()+'] '+msg+(data!==undefined?'\n'+JSON.stringify(data,null,2):'');state.logs.push(entry);if(state.logs.length>80)state.logs.shift();console.debug('[DTF]',msg,data)}
function showError(title,err){const detail=err&&err.stack?err.stack:(err&&err.message?err.message:String(err));errorTitle.textContent=title;errorLog.textContent=state.logs.concat(['ERROR: '+detail]).join('\n\n');errorBox.classList.add('show');log(title,detail);setStatus(title);errorBox.scrollIntoView({block:'nearest'})}
function clearError(){errorBox.classList.remove('show');errorLog.textContent=''}
ui.copyError.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(errorLog.textContent);setStatus('Log copiado')}catch(e){setStatus('Selecione e copie o log manualmente')}})

function canvasClone(src){const c=document.createElement('canvas');c.width=src.width;c.height=src.height;c.getContext('2d').putImageData(src.getContext('2d').getImageData(0,0,src.width,src.height),0,0);return c}
function dataClone(src){return src?new Uint8ClampedArray(src):null}
function cloneCanvasData(c){return {w:c.width,h:c.height,data:dataClone(c.getContext('2d').getImageData(0,0,c.width,c.height).data)}}
function canvasFromData(s){const c=document.createElement('canvas');c.width=s.w;c.height=s.h;c.getContext('2d').putImageData(new ImageData(dataClone(s.data),s.w,s.h),0,0);return c}
function objectSnapshot(o){return {id:o.id,name:o.name,x:o.x,y:o.y,w:o.w,h:o.h,visible:o.visible,opacity:o.opacity,locked:o.locked===true,sourceType:o.sourceType,groupId:o.groupId,canvas:cloneCanvasData(o.canvas),base:cloneCanvasData(o.baseCanvas),aiOriginal:o.aiOriginalCanvas?cloneCanvasData(o.aiOriginalCanvas):null,aiOriginalW:o.aiOriginalW||null,aiOriginalH:o.aiOriginalH||null,originalW:o.originalW,originalH:o.originalH,intelligentVariantSetId:o.intelligentVariantSetId||null,intelligentVariantId:o.intelligentVariantId||null,intelligentVariantRotated:o.intelligentVariantRotated===true}}
function snapshotIntelligentVariantSets(){return Object.fromEntries(Object.entries(state.intelligentVariantSets||{}).map(([id,set])=>[id,{id,options:(set.options||[]).map(option=>({...option,canvas:cloneCanvasData(option.canvas)}))}]))}
function restoreIntelligentVariantSets(sets){return Object.fromEntries(Object.entries(sets||{}).map(([id,set])=>[id,{id,options:(set.options||[]).map(option=>({...option,canvas:canvasFromData(option.canvas)}))}]))}
function projectSnapshot(){return {workWmm:state.workWmm,workHmm:state.workHmm,dpi:state.dpi,bgColor:state.bgColor,transparent:state.transparent,customGuides:state.customGuides.map(guide=>({...guide})),selectedGuideId:state.selectedGuideId,selectedId:state.selectedId,selectedIds:[...(state.selectedIds||[])],objects:state.objects.map(objectSnapshot),intelligentVariantSets:snapshotIntelligentVariantSets()}}
function restoreProject(s){
 const view={zoom:state.zoom,panX:state.panX,panY:state.panY};
 state.historyLock=true;state.workWmm=s.workWmm;state.workHmm=s.workHmm;state.dpi=s.dpi;state.bgColor=s.bgColor;state.transparent=s.transparent;state.customGuides=sanitizeCustomGuides(s.customGuides);state.selectedGuideId=state.customGuides.some(guide=>guide.id===s.selectedGuideId)?s.selectedGuideId:null;state.selectedId=s.selectedId;state.selectedIds=s.selectedIds||[s.selectedId].filter(Boolean);state.intelligentVariantSets=restoreIntelligentVariantSets(s.intelligentVariantSets||{});
 state.objects=s.objects.map(o=>({...o,canvas:canvasFromData(o.canvas),baseCanvas:canvasFromData(o.base),aiOriginalCanvas:o.aiOriginal?canvasFromData(o.aiOriginal):null}));
 state.historyLock=false;syncWorkspaceUI();state.zoom=view.zoom;state.panX=view.panX;state.panY=view.panY;applyZoom();render();updateSelection();updateHistoryUI();
}
const autoToleranceSuggestedIds=new Set();
let projectDirty=false,currentProjectName='',currentProjectHandle=null;
const AUTOSAVE_KEY='printway_dtf_autosave_v1';
let autosaveTimer=0,autosaveBusy=false,autosaveRecoveryShown=false;
function clearAutosave(){if(autosaveTimer){clearTimeout(autosaveTimer);autosaveTimer=0}try{localStorage.removeItem(AUTOSAVE_KEY)}catch(_){} }
function writeAutosave(){if(autosaveBusy||!projectDirty||!state.objects.length)return;autosaveBusy=true;try{const project=projectData();localStorage.setItem(AUTOSAVE_KEY,JSON.stringify({version:1,savedAt:Date.now(),project}))}catch(error){log('Salvamento automático indisponível',error&&error.message?error.message:error)}finally{autosaveBusy=false}}
function scheduleAutosave(){if(!projectDirty||autosaveBusy)return;if(autosaveTimer)clearTimeout(autosaveTimer);autosaveTimer=window.setTimeout(()=>{autosaveTimer=0;writeAutosave()},1200)}
function readAutosave(){try{const saved=JSON.parse(localStorage.getItem(AUTOSAVE_KEY)||'null');if(!saved||saved.version!==1||!saved.project||saved.project.format!=='PrintWay Editor Project'||!Array.isArray(saved.project.objects)||!saved.project.objects.length)return null;const age=Date.now()-Number(saved.savedAt||0);return Number.isFinite(age)&&age>=0&&age<30*24*60*60*1000?saved:null}catch(_){return null}}
function setProjectDirty(dirty){projectDirty=Boolean(dirty);if(ui.saveProject){const action=currentProjectHandle?'Salvar no arquivo .pwedit aberto':'Salvar projeto .pwedit';ui.saveProject.classList.toggle('dtf-project-dirty',projectDirty);ui.saveProject.title=projectDirty?action+' — há alterações não salvas':action}if(projectDirty)scheduleAutosave()}
function pushHistory(label){if(state.historyLock)return;const snap=projectSnapshot();snap.label=label||'Edição';state.undo.push(snap);if(state.undo.length>state.historyLimit)state.undo.shift();state.redo=[];setProjectDirty(true);updateHistoryUI()}
function undo(){if(!state.undo.length)return;const target=state.undo[state.undo.length-1];if(target&&target.label==='Antes do arraste de remoção por área'&&pendingSmartAreaFeedback&&pendingSmartAreaFeedback.historyDepth===state.undo.length)settleSmartAreaFeedback(false);const cur=projectSnapshot();cur.label='Estado atual';state.redo.push(cur);restoreProject(state.undo.pop());setProjectDirty(true);setStatus('Desfeito')}
function redo(){if(!state.redo.length)return;const cur=projectSnapshot();cur.label='Estado atual';state.undo.push(cur);restoreProject(state.redo.pop());setProjectDirty(true);setStatus('Refeito')}
function updateHistoryUI(){if(ui.undo)ui.undo.disabled=!state.undo.length;if(ui.redo)ui.redo.disabled=!state.redo.length;renderHistoryMenus()}
function historyLabel(s,index,total){return (index+1)+'. '+(s.label||'Etapa '+(index+1))}
function renderHistoryMenus(){
 const fill=(menu,items,kind)=>{if(!menu)return;menu.innerHTML='';items.forEach((s,i)=>{const b=document.createElement('button');b.type='button';b.textContent=historyLabel(s,i,items.length);b.dataset.index=String(i);b.addEventListener('click',()=>{const count=kind==='undo'?items.length-i:i+1;for(let n=0;n<count;n++)kind==='undo'?undo():redo();closeHistoryMenus()});menu.appendChild(b)});menu.classList.toggle('open',false)};
 fill(ui.undoMenu,[...state.undo].reverse(),'undo');fill(ui.redoMenu,[...state.redo].reverse(),'redo');
 if(ui.undoMenuBtn)ui.undoMenuBtn.disabled=!state.undo.length;if(ui.redoMenuBtn)ui.redoMenuBtn.disabled=!state.redo.length;
}
function closeHistoryMenus(){if(ui.undoMenu)ui.undoMenu.classList.remove('open');if(ui.redoMenu)ui.redoMenu.classList.remove('open');if(ui.undoMenuBtn)ui.undoMenuBtn.setAttribute('aria-expanded','false');if(ui.redoMenuBtn)ui.redoMenuBtn.setAttribute('aria-expanded','false')}
function toggleHistoryMenu(kind){const menu=kind==='undo'?ui.undoMenu:ui.redoMenu,other=kind==='undo'?ui.redoMenu:ui.undoMenu,btn=kind==='undo'?ui.undoMenuBtn:ui.redoMenuBtn;if(!menu||!other||!btn)return;other.classList.remove('open');menu.classList.toggle('open');btn.setAttribute('aria-expanded',String(menu.classList.contains('open')))}

function selected(){return state.objects.find(o=>o.id===state.selectedId)||null}
function selectedObjects(){const ids=state.selectedIds&&state.selectedIds.length?state.selectedIds:[state.selectedId];return state.objects.filter(o=>ids.includes(o.id))}
function setContextAvailability(control,enabled,reason=''){
 if(!control||typeof control.disabled==='undefined')return;
 if(!control.dataset.contextTitle)control.dataset.contextTitle=control.getAttribute('title')||control.getAttribute('aria-label')||'';
 control.disabled=!enabled;control.setAttribute('aria-disabled',String(!enabled));
 const title=enabled?control.dataset.contextTitle:reason;if(title)control.setAttribute('title',title);
}
function updateContextTools(){
 const items=selectedObjects(),count=items.length,hasSelection=count>0,hasMultiple=count>1,hasObjects=state.objects.length>0,needsSelection='Selecione uma imagem para usar esta ferramenta.',needsMultiple='Selecione pelo menos duas imagens para usar esta função.';
 [ui.original,ui.reset,ui.pickColor,ui.dehalo,ui.duplicate,ui.del,ui.center,ui.clickTol,ui.tol,ui.tolMinus,ui.tolPlus,ui.fillTool].forEach(control=>setContextAvailability(control,hasSelection,needsSelection));
 [ui.eraser,ui.restoreBrush,ui.brush].forEach(control=>setContextAvailability(control,hasObjects,'Insira uma imagem na área de trabalho primeiro.'));
 [clickRemove,areaRemoveButton,globalColorRemoveButton].forEach(control=>setContextAvailability(control,hasSelection,needsSelection));if(fillColor)setContextAvailability(fillColor,hasSelection,needsSelection);
 [ui.alignLeft,ui.alignCenter,ui.alignRight,ui.alignTop,ui.alignMiddle,ui.alignBottom].forEach(control=>setContextAvailability(control,hasSelection,needsSelection));
 [ui.distributeH,ui.distributeV,ui.organize].forEach(control=>setContextAvailability(control,hasMultiple,needsMultiple));
 setContextAvailability(fitHeightButton,hasObjects,'Insira uma imagem para ajustar a área de trabalho.');
 setContextAvailability(ui.magicFill,!hasObjects||hasSelection,hasObjects?'Selecione uma imagem para a montagem inteligente.':'Envie uma imagem para iniciar a montagem inteligente.');
 setContextAvailability(ui.exportOpen,hasObjects,'Insira uma imagem antes de exportar.');
 const organizeApply=$id('dtfOrganizeApply');if(organizeApply)setContextAvailability(organizeApply,hasMultiple,needsMultiple);
 const anyGrouped=items.some(item=>item.groupId),menuNeedsSelection='Selecione uma imagem primeiro.';
 [['dtfCopyObjects',hasSelection,menuNeedsSelection],['dtfDuplicateObjects',hasSelection,menuNeedsSelection],['dtfDeleteObjects',hasSelection,menuNeedsSelection],['dtfFlipH',hasSelection,menuNeedsSelection],['dtfFlipV',hasSelection,menuNeedsSelection],['dtfGroupObjects',hasMultiple&&!anyGrouped,anyGrouped?'Desagrupe os objetos antes de criar um novo grupo.':needsMultiple],['dtfUngroupObjects',anyGrouped,'Selecione um grupo para desagrupar.'],['dtfPasteObjects',state.clipboard&&state.clipboard.length,'Copie uma imagem antes de colar.']].forEach(([id,enabled,reason])=>setContextAvailability($id(id),enabled,reason));
}
function updateObjectUI(){
 const o=selected();const disabled=!o;
 ui.duplicate.disabled=disabled;ui.del.disabled=disabled;ui.center.disabled=disabled;ui.compare.disabled=disabled;ui.objX.disabled=disabled;ui.objY.disabled=disabled;ui.objW.disabled=disabled;ui.objH.disabled=disabled;ui.compareRange.disabled=disabled||!state.compare;
 ui.selectedName.textContent=o?o.name:'Nenhum';
 if(ui.objectTotal)ui.objectTotal.textContent=String(state.objects.length);if(ui.objectSelected)ui.objectSelected.textContent=String(state.selectedIds.length);
 if(o){ui.objX.value=mmToUnit(pxToMm(o.x)).toFixed(1);ui.objY.value=mmToUnit(pxToMm(o.y)).toFixed(1);ui.objW.value=mmToUnit(pxToMm(o.w)).toFixed(1);ui.objH.value=mmToUnit(pxToMm(o.h)).toFixed(1)} else {ui.objX.value=ui.objY.value=ui.objW.value=ui.objH.value=''};updateContextTools()
}

function syncWorkspaceUI(){
 ui.workW.value=Number(mmToUnit(state.workWmm).toFixed(2));ui.workH.value=Number(mmToUnit(state.workHmm).toFixed(2));ui.dpi.value=state.dpi;ui.bgColor.value=state.bgColor;ui.transparent.checked=state.transparent;document.querySelectorAll('#dtfEditor .dtf-mm').forEach(el=>el.textContent=unitSelect?unitSelect.value:'mm');
 const w=physicalMmToPx(state.workWmm),h=physicalMmToPx(state.workHmm);canvas.width=w;canvas.height=h;compareCanvas.width=w;compareCanvas.height=h;updateRulers();
 inner.style.width=w+'px';inner.style.height=h+'px';inner.classList.toggle('dtf-checker',state.transparent);inner.classList.toggle('dtf-solid-bg',!state.transparent);inner.style.background=state.transparent?'':' '+state.bgColor;updateGuidesOverlay();
 ui.outputInfo.textContent='Área: '+state.workWmm+' × '+state.workHmm+' mm • '+state.dpi+' DPI • '+w+' × '+h+' px';fitZoom(false);requestAnimationFrame(updateRulers)
}

function minimumZoom(){const vw=Math.max(1,viewport.clientWidth-44),vh=Math.max(1,viewport.clientHeight-40);return clamp(Math.min(vw/canvas.width,vh/canvas.height),.08,1)}
function fitZoom(showStatus=true){const z=minimumZoom();state.zoom=z;state.panX=0;state.panY=0;applyZoom();if(showStatus)setStatus('Área ajustada à tela')}
function fitWidthZoom(showStatus=true){const vw=Math.max(1,viewport.clientWidth-44),z=clamp(vw/canvas.width,.08,4);state.zoom=z;state.panX=0;state.panY=0;applyZoom();if(showStatus)setStatus('Página ajustada à largura — zoom '+Math.round(z*100)+'%')}
function updateScrollSpace(){if(!innerScroll)return;const z=Math.max(.01,state.zoom||1),x=Math.max(0,state.panX||0),y=Math.max(0,state.panY||0),availableW=Math.max(1,viewport.clientWidth-44),availableH=Math.max(1,viewport.clientHeight-40);innerScroll.style.width=Math.max(availableW,canvas.width*z+x+8)+'px';innerScroll.style.height=Math.max(availableH,canvas.height*z+y+8)+'px'}
function applyZoom(){state.panX=Math.max(0,state.panX);state.panY=Math.max(0,state.panY);inner.style.transform='translate('+state.panX+'px,'+state.panY+'px) scale('+state.zoom+')';updateScrollSpace();if(selectionLabel)selectionLabel.style.transform='scale('+(1/state.zoom)+')';updateRulers();updateSelection();updateEraserCursor()}
function setZoom(z){state.zoom=clamp(z,minimumZoom(),4);applyZoom();setStatus('Zoom '+Math.round(state.zoom*100)+'%')}

function clearCanvas(){ctx.clearRect(0,0,canvas.width,canvas.height);ctx.fillStyle=state.transparent?'#eeeeee':state.bgColor;ctx.fillRect(0,0,canvas.width,canvas.height)}
function drawObjectChecker(o){if(!state.transparent||!o.visible)return;ctx.save();ctx.beginPath();ctx.rect(o.x,o.y,o.w,o.h);ctx.clip();ctx.fillStyle='#fff';ctx.fillRect(o.x,o.y,o.w,o.h);ctx.fillStyle='#dedede';const size=16;for(let y=Math.floor(o.y/size)*size;y<o.y+o.h;y+=size)for(let x=Math.floor(o.x/size)*size;x<o.x+o.w;x+=size){if((Math.floor(x/size)+Math.floor(y/size))%2===0)ctx.fillRect(x,y,size,size)}ctx.restore()}
function drawObject(o,targetCtx){if(!o.visible)return;targetCtx.save();targetCtx.globalAlpha=o.opacity||1;targetCtx.imageSmoothingEnabled=true;targetCtx.imageSmoothingQuality='high';targetCtx.drawImage(o.canvas,0,0,o.canvas.width,o.canvas.height,o.x,o.y,o.w,o.h);targetCtx.restore()}
function render(){clearCanvas();for(const o of state.objects)drawObjectChecker(o);for(const o of state.objects)drawObject(o,ctx);updateCompareCanvas();updateSelection();updateEraserCursor()}
function updateCompareCanvas(){if(!state.compare||!selected()){compareOverlay.classList.remove('show');return}const o=selected();compareCtx.clearRect(0,0,compareCanvas.width,compareCanvas.height);compareCtx.save();compareCtx.globalAlpha=o.opacity||1;compareCtx.drawImage(o.baseCanvas,0,0,o.baseCanvas.width,o.baseCanvas.height,o.x,o.y,o.w,o.h);compareCtx.restore();compareOverlay.style.clipPath='inset(0 '+(100-state.comparePct)+'% 0 0)';compareOverlay.classList.add('show')}

function updateSelection(){inner.querySelectorAll('.dtf-multi-selection,.dtf-lock-badge').forEach(el=>el.remove());const o=selected();if(!o){selection.classList.remove('show');badge.classList.remove('show');updateObjectUI();return}if(o.locked){const lb=document.createElement('div');lb.className='dtf-lock-badge';lb.textContent='🔒';lb.style.left=o.x+'px';lb.style.top=o.y+'px';inner.appendChild(lb)}
 state.selectedIds.filter(id=>id!==o.id).forEach(id=>{const item=state.objects.find(x=>x.id===id);if(!item)return;const box=document.createElement('div');box.className='dtf-multi-selection';box.style.left=item.x+'px';box.style.top=item.y+'px';box.style.width=item.w+'px';box.style.height=item.h+'px';inner.appendChild(box)});
 selection.classList.add('show');selection.style.left=o.x+'px';selection.style.top=o.y+'px';selection.style.width=o.w+'px';selection.style.height=o.h+'px';selectionLabel.style.top=o.y<32?(o.h+6)+'px':'-34px';selectionLabel.textContent=o.name+' • '+pxToMm(o.w).toFixed(1)+' × '+pxToMm(o.h).toFixed(1)+' mm';badge.textContent=state.objects.length+' objeto'+(state.objects.length===1?'':'s');badge.classList.add('show');updateObjectUI()}
function hitTest(wx,wy){for(let i=state.objects.length-1;i>=0;i--){const o=state.objects[i];if(!o.visible||wx<o.x||wy<o.y||wx>o.x+o.w||wy>o.y+o.h)continue;return o}return null}
function clientToWorkspace(e){const r=canvas.getBoundingClientRect();if(!r.width||!r.height)return null;return {x:(e.clientX-r.left)*(canvas.width/r.width),y:(e.clientY-r.top)*(canvas.height/r.height)}}

function selectObject(o,multi=false){if(!o){state.selectedId=null;state.selectedIds=[]}else if(multi){if(state.selectedIds.includes(o.id)){state.selectedIds=state.selectedIds.filter(id=>id!==o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null}else{state.selectedIds.push(o.id);state.selectedId=o.id}}else if(o.groupId){state.selectedId=o.id;state.selectedIds=state.objects.filter(item=>item.groupId===o.groupId).map(item=>item.id)}else{state.selectedId=o.id;state.selectedIds=[o.id]}updateObjectUI();updateSelection();updateCompareCanvas();if(o)scheduleSmartAreaGuidePreparation(o);const suggested=o&&state.selectedIds.length===1&&setRecommendedBackgroundTolerance(o);const selectedStatus=o&&state.selectedIds.length?(state.selectedIds.length>1?state.selectedIds.length+' objetos selecionados':'Selecionado: '+o.name):'Nenhum objeto selecionado';setStatus(suggested?selectedStatus+' · tolerância sugerida: '+ui.tol.value:selectedStatus)}

function imgToCanvas(img){const c=document.createElement('canvas');c.width=img.naturalWidth||img.width;c.height=img.naturalHeight||img.height;if(!c.width||!c.height)throw new Error('A imagem não possui tamanho válido.');c.getContext('2d').drawImage(img,0,0);return c}
function readU32(data,offset,little=false){return little?(data[offset]|data[offset+1]<<8|data[offset+2]<<16|data[offset+3]<<24)>>>0:((data[offset]<<24|data[offset+1]<<16|data[offset+2]<<8|data[offset+3])>>>0)}
function embeddedImageResolution(bytes){
 const has=(offset,...values)=>values.every((value,index)=>bytes[offset+index]===value);
 if(bytes.length>29&&has(0,137,80,78,71,13,10,26,10)){let offset=8;while(offset+12<=bytes.length){const length=readU32(bytes,offset),type=String.fromCharCode(bytes[offset+4],bytes[offset+5],bytes[offset+6],bytes[offset+7]);if(type==='pHYs'&&length>=9){const x=readU32(bytes,offset+8),y=readU32(bytes,offset+12),unit=bytes[offset+16];if(unit===1&&x&&y)return {x:x*.0254,y:y*.0254,embedded:true}}if(type==='IEND'||offset+12+length>bytes.length)break;offset+=12+length}}
 if(bytes.length>12&&bytes[0]===255&&bytes[1]===216){let offset=2;while(offset+4<bytes.length){if(bytes[offset]!==255){offset++;continue}const marker=bytes[offset+1];if(marker===217||marker===218)break;const length=(bytes[offset+2]<<8)|bytes[offset+3],start=offset+4;if(length<2||start+length-2>bytes.length)break;if(marker===224&&length>=14&&has(start,74,70,73,70,0)){const unit=bytes[start+7],x=(bytes[start+8]<<8)|bytes[start+9],y=(bytes[start+10]<<8)|bytes[start+11];if(x&&y&&unit)return {x:unit===2?x*2.54:x,y:unit===2?y*2.54:y,embedded:true}}offset=start+length-2}}
 return null;
}
async function getImageResolution(file){try{const bytes=new Uint8Array(await file.slice(0,262144).arrayBuffer());return embeddedImageResolution(bytes)||{x:state.dpi,y:state.dpi,embedded:false}}catch(_){return {x:state.dpi,y:state.dpi,embedded:false}}}
function imageToObject(img,name,sourceType,resolution,options={}){const c=document.createElement('canvas');c.width=img.naturalWidth||img.width;c.height=img.naturalHeight||img.height;c.getContext('2d').drawImage(img,0,0);const dpiX=Math.max(1,Number(resolution&&resolution.x)||state.dpi),dpiY=Math.max(1,Number(resolution&&resolution.y)||state.dpi),naturalW=Math.max(1,c.width*state.dpi/dpiX),naturalH=Math.max(1,c.height*state.dpi/dpiY),maxW=options.allowWorkspaceExpand?Infinity:canvas.width,maxH=options.allowWorkspaceExpand?Infinity:canvas.height,w=Math.min(maxW,naturalW),h=Math.min(maxH,naturalH);return {id:uid(),name:name||'Objeto '+(state.objects.length+1),x:0,y:0,w,h,visible:true,opacity:1,canvas:c,baseCanvas:canvasFromData(cloneCanvasData(c)),originalW:c.width,originalH:c.height,sourceDpiX:dpiX,sourceDpiY:dpiY,sourceType:sourceType||'image'}}
function ensureWorkspaceFitsImported(objects){if(!objects.length)return false;const right=Math.max(...objects.map(o=>o.x+o.w)),bottom=Math.max(...objects.map(o=>o.y+o.h)),neededW=Math.max(10,Number(pxToMm(right).toFixed(2))),neededH=Math.max(10,Number(pxToMm(bottom).toFixed(2)));let changed=false;if(neededW>state.workWmm+.001){state.workWmm=Math.min(1000,neededW);changed=true}if(neededH>state.workHmm+.001){state.workHmm=Math.min(10000,neededH);changed=true}if(changed)try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:state.workWmm,h:state.workHmm,dpi:state.dpi}))}catch(_){}return changed}
function addObject(o){pushHistory('Antes de adicionar objeto');if(ensureWorkspaceFitsImported([o]))syncWorkspaceUI();state.objects.push(o);pendingRemovalGuideIds.add(o.id);selectObject(o);render();setStatus('Objeto adicionado: '+o.name)}

let removalGuide=null,removalGuideTimer=0,removalGuideRequest=0;const pendingRemovalGuideIds=new Set();
function imageComplexityScore(o){
 const source=o&&(o.aiOriginalCanvas||o.baseCanvas||o.canvas);if(!source||!source.width||!source.height)return 0;
 const sample=document.createElement('canvas'),size=36;sample.width=size;sample.height=size;const sc=sample.getContext('2d',{willReadFrequently:true});sc.drawImage(source,0,0,size,size);const data=sc.getImageData(0,0,size,size).data,bins=new Set();let detail=0,edgeDiff=0,edges=0,pairs=0,edgeR=0,edgeG=0,edgeB=0;
 for(let y=0;y<size;y++)for(let x=0;x<size;x++){const i=(y*size+x)*4,r=data[i],g=data[i+1],b=data[i+2];bins.add((r>>5)+'-'+(g>>5)+'-'+(b>>5));if(x<size-1){const j=i+4;detail+=Math.abs(r-data[j])+Math.abs(g-data[j+1])+Math.abs(b-data[j+2]);pairs++}if(y<size-1){const j=i+size*4;detail+=Math.abs(r-data[j])+Math.abs(g-data[j+1])+Math.abs(b-data[j+2]);pairs++}if(x===0||y===0||x===size-1||y===size-1){edgeR+=r;edgeG+=g;edgeB+=b;edges++}}
 edgeR/=edges;edgeG/=edges;edgeB/=edges;for(let y=0;y<size;y++)for(let x=0;x<size;x++)if(x===0||y===0||x===size-1||y===size-1){const i=(y*size+x)*4;edgeDiff+=Math.abs(data[i]-edgeR)+Math.abs(data[i+1]-edgeG)+Math.abs(data[i+2]-edgeB)}
 return clamp(Math.round((detail/Math.max(1,pairs)/765)*115+(edgeDiff/Math.max(1,edges)/765)*50+Math.min(1,bins.size/180)*40),0,100);
}
function hideRemovalGuide(){clearTimeout(removalGuideTimer);if(removalGuide)removalGuide.hidden=true}
function showRemovalGuide(target,message,kind='simple',duration=5200){
 if(!target)return;if(!removalGuide){removalGuide=document.createElement('div');removalGuide.className='dtf-removal-guide';removalGuide.setAttribute('role','status');document.body.appendChild(removalGuide)}
 removalGuide.className='dtf-removal-guide dtf-removal-guide-'+kind;removalGuide.textContent=message;removalGuide.hidden=false;const r=target.getBoundingClientRect(),g=removalGuide.getBoundingClientRect(),left=clamp(r.left+r.width/2-g.width/2,8,window.innerWidth-g.width-8),above=r.top-g.height-11;removalGuide.style.left=left+'px';removalGuide.style.top=(above>8?above:Math.min(window.innerHeight-g.height-8,r.bottom+11))+'px';clearTimeout(removalGuideTimer);removalGuideTimer=window.setTimeout(hideRemovalGuide,duration);
}
function scheduleRemovalGuidance(){
 const request=++removalGuideRequest;window.setTimeout(()=>{if(request!==removalGuideRequest||typeof activeTab==='undefined'||activeTab!=='editar'||helpOpen)return;const items=selectedObjects(),fresh=items.filter(item=>pendingRemovalGuideIds.has(item.id));if(!fresh.length){hideRemovalGuide();return}fresh.forEach(item=>pendingRemovalGuideIds.delete(item.id));const score=Math.max(...fresh.map(imageComplexityScore)),complex=score>=43;showRemovalGuide(complex&&ui.magicFill?ui.magicFill:ui.reset,complex?'Imagem com muitos detalhes: a IA deve produzir um recorte mais preciso.':'Fundo uniforme detectado: a remoção simples é a opção mais rápida.',complex?'complex':'simple',5000)},160);
}
document.addEventListener('pointerdown',()=>{if(removalGuide&&!removalGuide.hidden)hideRemovalGuide()},true);

async function canvasToBlob(c,type='image/png',quality){return new Promise((resolve,reject)=>c.toBlob(b=>b?resolve(b):reject(new Error('Falha ao criar imagem')),type,quality))}
function loadBlobImage(blob){
 return new Promise((resolve,reject)=>{
  if(!blob||!blob.size){reject(new Error('O arquivo está vazio ou não pôde ser lido.'));return;}
  const reader=new FileReader();
  let img=null,settled=false;
  const timer=setTimeout(()=>complete(new Error('A leitura da imagem demorou demais. Tente abrir o arquivo novamente.')),30000);
  function complete(error){
   if(settled)return;
   settled=true;clearTimeout(timer);
   reader.onload=reader.onerror=reader.onabort=null;
   if(reader.readyState===1)reader.abort();
   if(img)img.onload=img.onerror=null;
   if(error)reject(error);else resolve(img);
  }
  reader.onerror=()=>complete(reader.error||new Error('Não foi possível ler o arquivo.'));
  reader.onabort=()=>complete(new Error('A leitura do arquivo foi interrompida.'));
  reader.onload=()=>{
   img=new Image();
   img.onload=()=>complete(img.naturalWidth&&img.naturalHeight?null:new Error('A imagem não possui dimensões válidas.'));
   img.onerror=()=>complete(new Error('O navegador não conseguiu abrir este formato de imagem.'));
   img.src=reader.result;
  };
 try{reader.readAsDataURL(blob);}catch(error){complete(error);}
 });
}

function isTiffFile(file){
 const name=String(file&&file.name||''),type=String(file&&file.type||'').toLowerCase();
 return /\.tiff?$/i.test(name)||type==='image/tiff'||type==='image/tif'||type==='image/x-tiff';
}
function tiffResolutionFromIfd(ifd,orientation){
 const first=tag=>{const value=ifd&&ifd[tag];return Math.max(0,Number(Array.isArray(value)||ArrayBuffer.isView(value)?value[0]:value)||0)};
 const unit=Math.round(first('t296')),factor=unit===3?2.54:1;
 let x=first('t282')*factor,y=first('t283')*factor;
 if(!x)x=state.dpi;if(!y)y=x;
 if(orientation>=5&&orientation<=8)[x,y]=[y,x];
 return {x,y,embedded:!!(first('t282')||first('t283'))};
}
function tiffCanvasFromRgba(rgba,width,height,orientation){
 const source=document.createElement('canvas');source.width=width;source.height=height;
 const sourceContext=source.getContext('2d',{alpha:true,willReadFrequently:true}),pixels=sourceContext.createImageData(width,height);
 if(!rgba||rgba.length!==pixels.data.length)throw new Error('Os dados de pixels do TIFF estão incompletos.');
 pixels.data.set(rgba);sourceContext.putImageData(pixels,0,0);
 if(orientation<2||orientation>8)return source;
 const swapped=orientation>=5,output=document.createElement('canvas');output.width=swapped?height:width;output.height=swapped?width:height;
 const context=output.getContext('2d',{alpha:true});
 const transforms={2:[-1,0,0,1,width,0],3:[-1,0,0,-1,width,height],4:[1,0,0,-1,0,height],5:[0,1,1,0,0,0],6:[0,1,-1,0,height,0],7:[0,-1,-1,0,height,width],8:[0,-1,1,0,0,width]};
 context.setTransform(...transforms[orientation]);context.drawImage(source,0,0);context.setTransform(1,0,0,1,0,0);return output;
}
async function decodeTiffFile(file){
 progress(12,'Abrindo arquivo TIFF…');const bytes=await file.arrayBuffer(),header=new Uint8Array(bytes,0,Math.min(4,bytes.byteLength));
 const classicTiff=header.length===4&&((header[0]===73&&header[1]===73&&header[2]===42&&header[3]===0)||(header[0]===77&&header[1]===77&&header[2]===0&&header[3]===42));
 if(!classicTiff)throw new Error('O arquivo não possui uma estrutura TIFF válida.');
 const UTIF=await ensureUTIF();let ifds;
 try{ifds=UTIF.decode(bytes)}catch(error){throw new Error('O TIFF está corrompido ou possui uma estrutura não suportada'+(error&&error.message?': '+error.message:'.'))}
 const pages=[];
 for(let index=0;index<ifds.length;index++){
  const ifd=ifds[index];if(!ifd||!ifd.t256||!ifd.t257)continue;
  const width=Math.max(0,Number(ifd.t256[0])||0),height=Math.max(0,Number(ifd.t257[0])||0),compression=Math.round(Number(ifd.t259&&ifd.t259[0])||1);
  if(!width||!height)continue;if(width*height>MAX_PX)throw new Error('A página '+(index+1)+' do TIFF é grande demais para a memória segura do navegador.');
  if(![1,3,4,5,6,7,8,32767,32773].includes(compression))throw new Error('A compressão TIFF '+compression+' não é compatível. Salve como TIFF LZW, ZIP, PackBits ou sem compressão.');
  progress(20+Math.round(index/Math.max(1,ifds.length)*55),'Decodificando página '+(index+1)+' do TIFF…');
  try{UTIF.decodeImage(bytes,ifd,ifds);const rgba=UTIF.toRGBA8(ifd),orientation=Math.round(Number(ifd.t274&&ifd.t274[0])||1),canvas=tiffCanvasFromRgba(rgba,width,height,orientation);pages.push({canvas,resolution:tiffResolutionFromIfd(ifd,orientation)})}catch(error){throw new Error('Não foi possível decodificar a página '+(index+1)+' do TIFF'+(error&&error.message?': '+error.message:'.'))}
  await nextPaint();
 }
 if(!pages.length)throw new Error('O TIFF não contém nenhuma página de imagem válida.');
 return pages;
}

// Remoção de fundo local: o modelo é baixado uma vez e fica em cache pelo navegador.
// A imagem não é enviada para um endpoint do servidor.
const BROWSER_BG_REMOVAL_URL='https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.7.0/+esm';
let browserBgRemovalPromise=null,browserBgModelPromise=null,browserBgModelReady=false;
function loadBrowserBgRemoval(){
 if(!browserBgRemovalPromise){
  browserBgRemovalPromise=import(BROWSER_BG_REMOVAL_URL).then(mod=>{
   const removeBackground=mod.default||mod.removeBackground||mod.imglyRemoveBackground;
   if(typeof removeBackground!=='function')throw new Error('A biblioteca de remoção de fundo não expôs uma função válida.');
   return {removeBackground,preload:typeof mod.preload==='function'?mod.preload:null};
  }).catch(error=>{browserBgRemovalPromise=null;throw error});
 }
 return browserBgRemovalPromise;
}
function preloadBrowserBgModel(){
 if(browserBgModelReady)return loadBrowserBgRemoval();
 if(!browserBgModelPromise)browserBgModelPromise=loadBrowserBgRemoval().then(async api=>{
  if(api.preload)await api.preload({device:navigator.gpu?'gpu':'cpu',model:'isnet_fp16'});
  browserBgModelReady=true;return api;
 }).catch(error=>{browserBgModelPromise=null;throw error});
 return browserBgModelPromise;
}
function warmBrowserBgModel(){
 const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
 if(connection&&connection.saveData)return;
 const warm=()=>preloadBrowserBgModel().catch(()=>{});
 window.setTimeout(warm,350);
}
const MAGIC_GAP_MM=4,MAGIC_OBJECT_LIMIT=600;
let magicSession={token:0,sources:[],prepared:[],variants:[],rawCuts:[],packing:null,stage:'idle',preparing:false,applying:false,complete:false,improved:false,variantsReady:false,autoHeight:true};
const MAGIC_STEP_RANGES={source:[0,5],model:[5,28],remove:[28,72],layout:[72,85],apply:[84,100]};
function setMagicStepProgress(name,value){
 if(!ui.magicSteps)return;
 const item=ui.magicSteps.querySelector('[data-magic-step="'+name+'"]');
 if(item)item.style.setProperty('--dtf-magic-step-progress',clamp(Number(value)||0,0,100)+'%');
}
function magicStep(name,status){
 if(!ui.magicSteps)return;
 const item=ui.magicSteps.querySelector('[data-magic-step="'+name+'"]');if(!item)return;
 item.classList.remove('active','done','error');if(status)item.classList.add(status);
 setMagicStepProgress(name,status==='done'?100:status==='active'?7:0);
 const mark=item.querySelector('.dtf-magic-step-mark'),number=Array.from(ui.magicSteps.children).indexOf(item)+1;
 if(mark)mark.textContent=status==='done'?'✓':status==='active'?'…':status==='error'?'!':String(number);
}
function resetMagicSteps(){['source','model','remove','layout','apply'].forEach(name=>magicStep(name,''))}
function setMagicBusy(busy){
 if(!ui.magicModal||ui.magicModal.hidden)return;const locked=!!busy;
 ui.magicModal.classList.toggle('dtf-magic-busy',locked);if(ui.magicProgress){ui.magicProgress.style.left='';ui.magicProgress.style.top='';}
 ui.magicModal.querySelectorAll('.dtf-magic-upload button,.dtf-magic-sources input,.dtf-magic-sources button,.dtf-magic-variants button,.dtf-magic-auto-height input').forEach(control=>control.disabled=locked||control.dataset.magicBaseDisabled==='true');
 if(ui.magicUploadButton)ui.magicUploadButton.disabled=locked;
 if(locked){if(ui.magicConfirm)ui.magicConfirm.disabled=true;if(ui.magicCutoutOnly)ui.magicCutoutOnly.disabled=true;}
 if(ui.magicCancel){ui.magicCancel.disabled=false;ui.magicCancel.textContent=locked?'Cancelar':'Cancelar';ui.magicCancel.title=locked?'Cancela o processamento atual sem alterar a área de trabalho.':'Fecha esta janela sem alterar a área de trabalho';}
}
function magicProgress(value,text){
 const amount=clamp(Number(value)||0,0,100);
 if(ui.magicProgressBar)ui.magicProgressBar.style.width=amount+'%';
 if(ui.magicProgressText)ui.magicProgressText.textContent=text||'';
 const active=ui.magicSteps&&ui.magicSteps.querySelector('li.active');
 if(active){const name=active.dataset.magicStep,range=MAGIC_STEP_RANGES[name]||[0,100],local=(amount-range[0])/Math.max(1,range[1]-range[0])*100;setMagicStepProgress(name,clamp(local,7,96));}
 setMagicBusy(magicSession.preparing||magicSession.applying);
}
function setMagicStage(stage){
 magicSession.stage=stage;
 if(!ui.magicModal)return;
 ui.magicModal.classList.toggle('dtf-magic-review-stage',stage==='review');
 ui.magicModal.classList.toggle('dtf-magic-layout-stage',stage==='layout');
 ui.magicModal.classList.toggle('dtf-magic-complete-stage',stage==='complete');
 if(ui.magicLayoutPreview&&stage!=='layout')ui.magicLayoutPreview.hidden=true;
 if(ui.magicReview)ui.magicReview.hidden=stage!=='review';
 if(ui.magicCutoutOnly){ui.magicCutoutOnly.hidden=stage!=='review';ui.magicCutoutOnly.disabled=!(stage==='review'&&magicSession.variantsReady&&!magicSession.preparing&&!magicSession.applying);ui.magicCutoutOnly.title='Aplica somente a miniatura de recorte escolhida e fecha';ui.magicCutoutOnly.setAttribute('aria-label','Aplicar somente o recorte selecionado')}
 if(ui.magicConfirm){const mounting=stage==='layout',reviewReady=stage==='review'&&magicSession.variantsReady&&!magicSession.preparing;ui.magicConfirm.hidden=stage==='complete';ui.magicConfirm.textContent=mounting?'Montar':'Avançar';ui.magicConfirm.title=mounting?'Cria a montagem com as quantidades e o espaçamento definidos':'Continua para definir e criar a montagem das imagens';ui.magicConfirm.setAttribute('aria-label',ui.magicConfirm.title);ui.magicConfirm.disabled=!(reviewReady||mounting)||magicSession.applying}
 if(ui.magicCancel){ui.magicCancel.textContent=stage==='complete'?'Fechar':'Cancelar';ui.magicCancel.title=stage==='complete'?'Fecha esta janela':'Fecha esta janela sem alterar a área de trabalho'}
 if(stage==='review')ui.magicMessage.textContent='Recorte pronto. Escolha uma versão: finalize somente o recorte ou avance para a montagem.';
 if(stage==='layout')ui.magicMessage.textContent=magicSession.sources.length===1?'Confira a quantidade automática e monte.':'Defina as quantidades e monte.';
 if(stage==='complete')ui.magicMessage.textContent='Concluído. O resultado já está na área de trabalho.';
 setMagicBusy(magicSession.preparing||magicSession.applying);
}
function closeMagicDialog(){
 if(!ui.magicModal)return;
 const wasProcessing=magicSession.preparing||magicSession.applying;magicSession.token++;magicSession.preparing=false;magicSession.applying=false;ui.magicModal.hidden=true;if(ui.magicZoom)ui.magicZoom.hidden=true;if(ui.magicLayoutPreview)ui.magicLayoutPreview.hidden=true;if(wasProcessing){finish('Processamento cancelado');setStatus('Montagem inteligente cancelada. Nenhuma alteração foi aplicada.');}
 if(ui.magicFill)ui.magicFill.focus();
}
function magicPreviewData(source,limit=96,frameW=source.width,frameH=source.height){
 const safeFrameW=Math.max(1,Number(frameW)||source.width||1),safeFrameH=Math.max(1,Number(frameH)||source.height||1),scale=Math.min(1,limit/Math.max(safeFrameW,safeFrameH)),preview=document.createElement('canvas');
 preview.width=Math.max(1,Math.round(safeFrameW*scale));preview.height=Math.max(1,Math.round(safeFrameH*scale));
 preview.getContext('2d').drawImage(source,0,0,preview.width,preview.height);return preview.toDataURL('image/png');
}
function magicThumbDimensions(width,height,limit=70){
 const w=Math.max(.01,Number(width)||1),h=Math.max(.01,Number(height)||1),scale=limit/Math.max(w,h);return {w:Math.max(8,Math.round(w*scale)),h:Math.max(8,Math.round(h*scale))};
}
function magicSourceFromObject(object,temporary=false){const original=object.aiOriginalCanvas||object.baseCanvas||object.canvas;return {id:object.id||uid(),name:object.name||'Imagem',object,canvas:original,w:object.w,h:object.h,quantity:0,magicScale:1,temporary}}
function applyMagicSize(sourceIndex,axis,value){const prepared=magicSession.prepared[sourceIndex];if(!prepared)return;const mm=Number(String(value||'').replace(',','.'));if(!Number.isFinite(mm)||mm<=0)return;const pixels=physicalMmToPx(mm),base=axis==='w'?prepared.baseW:prepared.baseH;if(!base)return;prepared.source.magicScale=clamp(pixels/base,.02,20);prepared.w=prepared.baseW*prepared.source.magicScale;prepared.h=prepared.baseH*prepared.source.magicScale;updateMagicCapacity();renderMagicLayoutPreview()}
function resetMagicSize(sourceIndex){const prepared=magicSession.prepared[sourceIndex];if(!prepared)return;prepared.source.magicScale=1;prepared.w=prepared.baseW;prepared.h=prepared.baseH;renderMagicSources();updateMagicCapacity();renderMagicLayoutPreview()}
function renderMagicSources(){
 if(!ui.magicSources)return;ui.magicSources.innerHTML='';
 const multiple=magicSession.sources.length>1,preparedById=new Map(magicSession.prepared.map(item=>[item.source.id,item]));
 magicSession.sources.forEach((source,index)=>{
  const prepared=preparedById.get(source.id),card=document.createElement('div');card.className='dtf-magic-source-card';
  const itemW=prepared?prepared.w:source.w,itemH=prepared?prepared.h:source.h,thumbSize=magicThumbDimensions(itemW,itemH,44);card.style.setProperty('--dtf-magic-source-thumb-w',thumbSize.w+'px');
  const thumb=document.createElement('div');thumb.className='dtf-magic-thumb';thumb.style.width=thumbSize.w+'px';thumb.style.height=thumbSize.h+'px';const image=document.createElement('img');image.alt='Miniatura da imagem';image.src=magicPreviewData(prepared?prepared.canvas:source.canvas,96,itemW,itemH);thumb.appendChild(image);
  const info=document.createElement('div');info.className='dtf-magic-source-info';
  if(prepared&&magicSession.stage==='layout'){
   const sizes=document.createElement('div'),sizeInputs={};sizes.className='dtf-magic-size-fields';
   const syncSizeInputs=()=>{if(sizeInputs.w)sizeInputs.w.value=pxToMm(prepared.w).toFixed(1);if(sizeInputs.h)sizeInputs.h.value=pxToMm(prepared.h).toFixed(1)};
   [['w','Largura'],['h','Altura']].forEach(([axis,label])=>{const field=document.createElement('label'),input=document.createElement('input');field.textContent=label+' (mm)';input.type='number';input.min='0.1';input.step='0.1';input.value=pxToMm(axis==='w'?prepared.w:prepared.h).toFixed(1);input.title='A medida é proporcional à outra dimensão';sizeInputs[axis]=input;const applyLive=()=>{applyMagicSize(index,axis,input.value);const other=axis==='w'?'h':'w';if(sizeInputs[other])sizeInputs[other].value=pxToMm(other==='w'?prepared.w:prepared.h).toFixed(1)};input.addEventListener('input',applyLive);input.addEventListener('change',()=>{applyLive();syncSizeInputs()});input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();applyLive();syncSizeInputs()}});field.appendChild(input);sizes.appendChild(field)});
   const reset=document.createElement('button');reset.type='button';reset.className='dtf-magic-size-reset';reset.title='Restaurar as medidas originais desta imagem';reset.setAttribute('aria-label','Restaurar medidas originais');reset.textContent='↺';reset.addEventListener('click',()=>resetMagicSize(index));sizes.appendChild(reset);info.appendChild(sizes)
  }else{const size=document.createElement('small');size.textContent=pxToMm(itemW).toFixed(1)+' × '+pxToMm(itemH).toFixed(1)+' mm';info.append(size)}
  if(multiple||(prepared&&magicSession.stage==='layout')){
   const field=document.createElement('label');field.className='dtf-magic-quantity';field.append(document.createTextNode('Quantidade'));
   const input=document.createElement('input');input.type='number';input.min=multiple?'1':'0';input.max=String(MAGIC_OBJECT_LIMIT);input.step='1';input.placeholder=multiple?'1':'Máxima';input.value=source.quantity>0?String(source.quantity):'';input.disabled=!prepared;
   input.addEventListener('input',()=>{source.quantity=input.value===''?0:clamp(parseInt(input.value,10)||0,0,MAGIC_OBJECT_LIMIT);updateMagicCapacity();renderMagicLayoutPreview()});
   input.addEventListener('blur',()=>{if(multiple&&source.quantity<1){source.quantity=1;input.value='1';updateMagicCapacity();renderMagicLayoutPreview()}});field.appendChild(input);info.appendChild(field);
  }else{const automatic=document.createElement('small');automatic.className='dtf-magic-auto';automatic.textContent=prepared?'Quantidade calculada automaticamente':'Analisando capacidade…';info.appendChild(automatic)}
  if(prepared){const capacity=buildMagicPacking(canvas.width,canvas.height,prepared.w,prepared.h,MAGIC_GAP_MM/25.4*state.dpi).total,limit=document.createElement('small');limit.className='dtf-magic-source-limit';limit.textContent='Capacidade individual: até '+Math.min(capacity,MAGIC_OBJECT_LIMIT);info.appendChild(limit)}
  card.append(thumb,info);ui.magicSources.appendChild(card);
 });
}
function hideMagicZoom(){if(ui.magicZoom)ui.magicZoom.hidden=true}
function showMagicZoom(card,variant,frameW=variant.w,frameH=variant.h){
 if(!ui.magicZoom||!ui.magicZoomImage||!ui.magicZoomLabel)return;
 const availableW=Math.max(150,Math.min(440,window.innerWidth-40)),availableH=Math.max(120,Math.min(360,window.innerHeight-70)),ratio=Math.max(.02,Number(frameW)||1)/Math.max(.02,Number(frameH)||1);let imageW=availableW,imageH=imageW/ratio;if(imageH>availableH){imageH=availableH;imageW=imageH*ratio}imageW=Math.round(imageW);imageH=Math.round(imageH);
 ui.magicZoom.style.width=(imageW+16)+'px';ui.magicZoom.style.height=(imageH+16)+'px';ui.magicZoomImage.src=magicPreviewData(variant.canvas,Math.max(imageW,imageH),frameW,frameH);ui.magicZoomImage.alt='Ampliação do recorte '+variant.label;ui.magicZoomLabel.textContent=variant.label;ui.magicZoom.hidden=false;
 const rect=card.getBoundingClientRect(),width=imageW+16,height=imageH+16,left=rect.right+10+width<=window.innerWidth?rect.right+10:Math.max(12,rect.left-width-10),top=Math.max(12,Math.min(rect.top,window.innerHeight-height-12));ui.magicZoom.style.left=left+'px';ui.magicZoom.style.top=top+'px';
}
function selectMagicVariant(sourceIndex,variantId){
 const group=magicSession.variants[sourceIndex],variant=group&&group.options.find(item=>item.id===variantId);if(!variant)return;
 group.selectedId=variantId;magicSession.prepared[sourceIndex]=magicPreparedFromVariant(group.source,variant);renderMagicVariants();renderMagicSources();updateMagicCapacity();renderMagicLayoutPreview();
}
function createMoreMagicVariant(sourceIndex){
 const group=magicSession.variants[sourceIndex];if(!group||group.options.length>=5||!group.availableOptions||!group.availableOptions.length)return;
 const next=group.availableOptions.shift();group.options.push(next);renderMagicVariants();
}
function renderMagicVariants(){
 if(!ui.magicVariants)return;ui.magicVariants.innerHTML='';
 magicSession.variants.forEach((group,sourceIndex)=>{
  const section=document.createElement('section');section.className='dtf-magic-variant-group';section.setAttribute('aria-label','Opções de recorte da imagem '+(sourceIndex+1));const list=document.createElement('div');list.className='dtf-magic-variant-list';
  group.options.forEach(variant=>{const card=document.createElement('button'),thumbSize=magicThumbDimensions(group.source.w,group.source.h,70);card.type='button';card.className='dtf-magic-variant';card.style.setProperty('--dtf-magic-variant-w',thumbSize.w+'px');card.style.setProperty('--dtf-magic-variant-h',thumbSize.h+'px');const selected=group.selectedId===variant.id;card.classList.toggle('selected',selected);card.setAttribute('aria-pressed',selected?'true':'false');card.setAttribute('aria-label','Selecionar '+variant.label+(variant.recommended?' (sugestão da IA)':''));card.title=variant.label+(variant.recommended?' — sugestão da IA':'')+' — passe o mouse para ampliar';const image=document.createElement('img');image.src=magicPreviewData(variant.canvas,128,group.source.w,group.source.h);image.alt='';const check=document.createElement('i');check.textContent=selected?'✓':'';card.append(image,check);card.addEventListener('click',()=>selectMagicVariant(sourceIndex,variant.id));card.addEventListener('pointerenter',()=>showMagicZoom(card,variant,group.source.w,group.source.h));card.addEventListener('pointerleave',hideMagicZoom);card.addEventListener('focus',()=>showMagicZoom(card,variant,group.source.w,group.source.h));card.addEventListener('blur',hideMagicZoom);list.appendChild(card)});
  if(group.options.length<5&&group.availableOptions&&group.availableOptions.length){const add=document.createElement('button'),thumbSize=magicThumbDimensions(group.source.w,group.source.h,70);add.type='button';add.className='dtf-magic-variant dtf-magic-variant-add';add.style.setProperty('--dtf-magic-variant-w',thumbSize.w+'px');add.style.setProperty('--dtf-magic-variant-h',thumbSize.h+'px');add.title='Criar mais uma variação inteligente de recorte';add.setAttribute('aria-label','Criar mais uma opção de recorte inteligente');add.innerHTML='<span>＋</span><small>Criar mais<br>opção</small>';add.addEventListener('click',()=>createMoreMagicVariant(sourceIndex));list.appendChild(add)}
  section.append(list);ui.magicVariants.appendChild(section);
 });
}
function tryMagicShelfPacking(items,areaW,areaH,gap,allowRotate,compact){
 const ordered=[...items].sort((a,b)=>compact?(Math.max(b.w,b.h)-Math.max(a.w,a.h)||b.w*b.h-a.w*a.h):(b.h-a.h||b.w-a.w)),rows=[];
 for(const item of ordered){
  const orientations=[{w:item.w,h:item.h,rotated:false}];
  if(allowRotate&&Math.abs(item.w-item.h)>.5)orientations.push({w:item.h,h:item.w,rotated:true});
  if(compact&&orientations.length>1)orientations.sort((a,b)=>a.h-b.h||Number(a.rotated)-Number(b.rotated));
  let choice=null;
  for(let rowIndex=0;rowIndex<rows.length;rowIndex++)for(const orientation of orientations){const row=rows[rowIndex],nextX=row.items.length?row.usedW+gap:0;if(orientation.h<=row.height+.01&&nextX+orientation.w<=areaW+.01){const score=areaW-(nextX+orientation.w)+(orientation.rotated?.1:0);if(!choice||score<choice.score)choice={row,rowIndex,orientation,nextX,score}}}
  if(choice){choice.row.items.push({...item,...choice.orientation,x:choice.nextX});choice.row.usedW=choice.nextX+choice.orientation.w;continue}
  const usedHeight=rows.reduce((sum,row)=>sum+row.height,0)+Math.max(0,rows.length-1)*gap,newY=rows.length?usedHeight+gap:0;
  let orientation=orientations.find(option=>option.w<=areaW+.01&&newY+option.h<=areaH+.01);
  if(!orientation)return null;
  rows.push({height:orientation.h,usedW:orientation.w,items:[{...item,...orientation,x:0}]});
 }
 const usedH=rows.reduce((sum,row)=>sum+row.height,0)+Math.max(0,rows.length-1)*gap;if(usedH>areaH+.01)return null;
 const placements=[];let y=(areaH-usedH)/2;
 rows.forEach(row=>{const startX=(areaW-row.usedW)/2;row.items.forEach(item=>placements.push({sourceIndex:item.sourceIndex,x:startX+item.x,y:y+(row.height-item.h)/2,w:item.w,h:item.h,rotated:item.rotated}));y+=row.height+gap});
 return {fits:true,total:items.length,rotatedTotal:placements.filter(item=>item.rotated).length,placements,usedHeight:usedH};
}
function buildMultipleMagicPacking(prepared,areaW,areaH,gap){
 const items=[];
 prepared.forEach((item,sourceIndex)=>{const quantity=clamp(parseInt(item.source.quantity,10)||0,0,MAGIC_OBJECT_LIMIT);for(let count=0;count<quantity&&items.length<=MAGIC_OBJECT_LIMIT;count++)items.push({sourceIndex,w:item.w,h:item.h})});
 if(!items.length)return {fits:false,total:0,rotatedTotal:0,placements:[]};
 if(items.length>MAGIC_OBJECT_LIMIT)return {fits:false,total:items.length,rotatedTotal:0,placements:[]};
 for(const allowRotate of [false,true])for(const compact of [false,true]){const result=tryMagicShelfPacking(items,areaW,areaH,gap,allowRotate,compact);if(result)return result}
 return {fits:false,total:items.length,rotatedTotal:0,placements:[]};
}
function fixedMagicPacking(){
 if(!magicSession.prepared.length)return null;const gap=MAGIC_GAP_MM/25.4*state.dpi;
 if(magicSession.prepared.length===1){const item=magicSession.prepared[0],maximum=buildMagicPacking(canvas.width,canvas.height,item.w,item.h,gap),wanted=clamp(parseInt(item.source.quantity,10)||0,0,MAGIC_OBJECT_LIMIT),total=wanted||maximum.total,placements=maximum.placements.slice(0,total).map(place=>({...place,sourceIndex:0}));return {...maximum,total,fits:total>0&&total<=maximum.total&&total<=MAGIC_OBJECT_LIMIT,placements,maximum:maximum.total}}
 return buildMultipleMagicPacking(magicSession.prepared,canvas.width,canvas.height,gap);
}
function desiredMagicItems(){const items=[];magicSession.prepared.forEach((item,sourceIndex)=>{let quantity=clamp(parseInt(item.source.quantity,10)||0,0,MAGIC_OBJECT_LIMIT);if(magicSession.prepared.length===1&&!quantity)quantity=1;for(let count=0;count<quantity&&items.length<=MAGIC_OBJECT_LIMIT;count++)items.push({sourceIndex,w:item.w,h:item.h})});return items}
function autoHeightMagicPacking(){
 const items=desiredMagicItems(),gap=MAGIC_GAP_MM/25.4*state.dpi;if(!items.length||items.length>MAGIC_OBJECT_LIMIT)return null;
 const generousHeight=items.reduce((sum,item)=>sum+Math.max(item.w,item.h)+gap,0),candidates=[];
 for(const allowRotate of [false,true])for(const compact of [false,true]){const result=tryMagicShelfPacking(items,canvas.width,generousHeight,gap,allowRotate,compact);if(!result)continue;const minY=Math.min(...result.placements.map(item=>item.y)),placements=result.placements.map(item=>({...item,y:item.y-minY})),requiredHeight=Math.ceil(Math.max(...placements.map(item=>item.y+item.h)));candidates.push({...result,placements,requiredHeight,usedHeight:requiredHeight,autoHeight:true})}
 candidates.sort((a,b)=>a.requiredHeight-b.requiredHeight||a.rotatedTotal-b.rotatedTotal);return candidates[0]||null;
}
function currentMagicPacking(){
 const fixed=fixedMagicPacking(),expanded=fixed&&!fixed.fits?autoHeightMagicPacking():null,maxAutoHeight=physicalMmToPx(10000),canExpand=!!(expanded&&expanded.fits&&expanded.requiredHeight>canvas.height+.5&&expanded.requiredHeight<=maxAutoHeight);
 magicSession.autoHeightTooTall=!!(expanded&&expanded.requiredHeight>maxAutoHeight);magicSession.autoHeightNeeded=canExpand;if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=!canExpand;
 if(canExpand&&magicSession.autoHeight)return {...expanded,maximum:fixed.maximum||expanded.total};return fixed;
}
function updateMagicCapacity(){
 if(!ui.magicCapacity)return false;
 if(!magicSession.sources.length){if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;ui.magicCapacity.className='dtf-magic-capacity';ui.magicCapacity.textContent=state.objects.length?'Selecione uma ou mais imagens.':'Envie uma imagem para iniciar.';ui.magicConfirm.disabled=true;return false}
 if(magicSession.prepared.length!==magicSession.sources.length){if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;ui.magicCapacity.className='dtf-magic-capacity analyzing';ui.magicCapacity.textContent='Analisando imagens e espaço disponível…';ui.magicConfirm.disabled=true;return false}
 if(magicSession.stage==='review'){if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;if(!magicSession.variantsReady||magicSession.preparing){ui.magicCapacity.className='dtf-magic-capacity analyzing';ui.magicCapacity.textContent='Finalizando as miniaturas de recorte…';ui.magicConfirm.disabled=true;if(ui.magicCutoutOnly)ui.magicCutoutOnly.disabled=true;return false}ui.magicCapacity.className='dtf-magic-capacity fits';ui.magicCapacity.textContent='Recorte pronto para revisão.';ui.magicConfirm.disabled=false;if(ui.magicCutoutOnly)ui.magicCutoutOnly.disabled=false;return true}
 if(magicSession.stage!=='layout'&&magicSession.stage!=='applying'){if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;ui.magicConfirm.disabled=true;return false}
 const packing=currentMagicPacking();magicSession.packing=packing;
 if(!packing||!packing.fits){ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent=packing&&packing.total>MAGIC_OBJECT_LIMIT?'A quantidade ultrapassa o limite de segurança de '+MAGIC_OBJECT_LIMIT+' objetos.':magicSession.autoHeightNeeded?'Ative o ajuste automático de altura para incluir todas as imagens.':magicSession.autoHeightTooTall?'A altura necessária ultrapassa o limite de 10.000 mm. Reduza as medidas ou quantidades.':'Essa montagem não cabe na largura da área de trabalho. Reduza as medidas ou quantidades.';ui.magicConfirm.disabled=true;magicStep('layout','error');return false}
 const areaHeight=packing.autoHeight?packing.requiredHeight:canvas.height,used=packing.placements.reduce((sum,item)=>sum+item.w*item.h,0),utilization=Math.max(0,Math.min(100,used/(canvas.width*areaHeight)*100));ui.magicCapacity.className='dtf-magic-capacity fits';ui.magicCapacity.textContent=packing.autoHeight?('A altura será ajustada para '+pxToMm(packing.requiredHeight).toFixed(1)+' mm ao aplicar · '+packing.total+' imagens com 4 mm.'):(magicSession.prepared.length===1?(packing.maximum+' cópias cabem com 4 mm'+(packing.total!==packing.maximum?' · '+packing.total+' selecionadas':'')+'.'):(packing.total+' imagens cabem com 4 mm'+(packing.rotatedTotal?' · '+packing.rotatedTotal+' giradas em 90°.':'.'))+' Otimização: '+utilization.toFixed(0)+'% da folha.');ui.magicConfirm.disabled=false;magicStep('layout','done');return true;
}
function positionMagicLayoutPreview(){
 if(!ui.magicLayoutPreview||ui.magicLayoutPreview.hidden||!ui.magicModal)return;const box=ui.magicModal.querySelector('.dtf-magic-box');if(!box)return;const rect=box.getBoundingClientRect(),panelWidth=250,panelHeight=Math.min(240,Math.max(190,rect.height));let left=rect.right+10,top=Math.max(12,Math.min(window.innerHeight-panelHeight-12,rect.top));if(left+panelWidth>window.innerWidth-10){left=rect.left-panelWidth-10;if(left<10){left=Math.max(10,Math.min(window.innerWidth-panelWidth-10,rect.left+(rect.width-panelWidth)/2));top=Math.min(window.innerHeight-panelHeight-10,rect.bottom-8)}}ui.magicLayoutPreview.style.left=left+'px';ui.magicLayoutPreview.style.top=top+'px';
}
function drawMagicArrow(context,x,y,width,height,rotated){const cx=x+width/2,cy=y+height/2,length=Math.max(5,Math.min(width,height)*.45);context.save();context.strokeStyle='#285f99';context.fillStyle='#285f99';context.lineWidth=1.25;context.lineCap='round';context.beginPath();if(rotated){context.moveTo(cx-length/2,cy);context.lineTo(cx+length/2,cy);context.lineTo(cx+length/2-3,cy-3);context.moveTo(cx+length/2,cy);context.lineTo(cx+length/2-3,cy+3)}else{context.moveTo(cx,cy+length/2);context.lineTo(cx,cy-length/2);context.lineTo(cx-3,cy-length/2+3);context.moveTo(cx,cy-length/2);context.lineTo(cx+3,cy-length/2+3)}context.stroke();context.restore()}
function renderMagicLayoutPreview(){
 if(!ui.magicLayoutPreview||!ui.magicLayoutCanvas||!ui.magicLayoutMeta)return;if(magicSession.stage!=='layout'||!magicSession.prepared.length){ui.magicLayoutPreview.hidden=true;return}ui.magicLayoutPreview.hidden=false;const preview=ui.magicLayoutCanvas,context=preview.getContext('2d'),packing=currentMagicPacking(),previewHeight=packing&&packing.autoHeight?packing.requiredHeight:canvas.height;magicSession.packing=packing;context.clearRect(0,0,preview.width,preview.height);const pad=14,scale=Math.min((preview.width-pad*2)/Math.max(1,canvas.width),(preview.height-pad*2)/Math.max(1,previewHeight)),sheetW=canvas.width*scale,sheetH=previewHeight*scale,sheetX=(preview.width-sheetW)/2,sheetY=(preview.height-sheetH)/2;context.fillStyle='#fff';context.fillRect(sheetX,sheetY,sheetW,sheetH);context.strokeStyle='#9eaab6';context.lineWidth=1;context.strokeRect(sheetX+.5,sheetY+.5,sheetW-1,sheetH-1);if(!packing||!packing.fits){context.fillStyle='#a03a3a';context.font='600 11px system-ui';context.textAlign='center';context.fillText('Não cabe com as medidas atuais',preview.width/2,preview.height/2);ui.magicLayoutMeta.textContent=magicSession.autoHeightNeeded?'Ative o ajuste automático de altura.':'Reduza as medidas para caber na largura da folha.';positionMagicLayoutPreview();return}const colors=['#d9ebff','#e6f5e9','#fff0d6','#eee5ff'];packing.placements.forEach(place=>{const x=sheetX+place.x*scale,y=sheetY+place.y*scale,w=Math.max(2,place.w*scale),h=Math.max(2,place.h*scale);context.fillStyle=colors[place.sourceIndex%colors.length];context.strokeStyle='#3977ae';context.lineWidth=1;context.fillRect(x,y,w,h);context.strokeRect(x+.5,y+.5,Math.max(1,w-1),Math.max(1,h-1));drawMagicArrow(context,x,y,w,h,place.rotated)});ui.magicLayoutMeta.textContent=packing.total+' item'+(packing.total===1?'':'s')+' · espaço de 4 mm'+(packing.autoHeight?' · altura final '+pxToMm(packing.requiredHeight).toFixed(1)+' mm':'')+'.';positionMagicLayoutPreview();
}
function trimTransparentArtwork(source){
 const context=source.getContext('2d',{willReadFrequently:true}),data=context.getImageData(0,0,source.width,source.height).data;
 let minX=source.width,minY=source.height,maxX=-1,maxY=-1;
 for(let y=0;y<source.height;y++)for(let x=0;x<source.width;x++)if(data[(y*source.width+x)*4+3]>2){if(x<minX)minX=x;if(y<minY)minY=y;if(x>maxX)maxX=x;if(y>maxY)maxY=y}
 if(maxX<0)throw new Error('A IA não encontrou uma parte visível para repetir.');
 // Não conserve uma margem transparente no resultado: depois do recorte a
 // arte passa a começar no próprio canto superior esquerdo do seu quadro.
 const x=minX,y=minY,w=maxX-minX+1,h=maxY-minY+1,out=document.createElement('canvas');
 out.width=w;out.height=h;out.getContext('2d').drawImage(source,x,y,w,h,0,0,w,h);
 return {canvas:out,x,y,w,h};
}
function magicVariantTrim(variant){
 const source=variant&&variant.canvas,saved=variant&&variant.trim;
 if(!source)throw new Error('Miniatura inteligente sem imagem.');
 if(saved&&Number(saved.w)>0&&Number(saved.h)>0){const x=Math.max(0,Math.min(source.width-1,Math.round(Number(saved.x)||0))),y=Math.max(0,Math.min(source.height-1,Math.round(Number(saved.y)||0))),w=Math.max(1,Math.min(source.width-x,Math.round(Number(saved.w)))),h=Math.max(1,Math.min(source.height-y,Math.round(Number(saved.h)))),canvas=document.createElement('canvas');canvas.width=w;canvas.height=h;canvas.getContext('2d').drawImage(source,x,y,w,h,0,0,w,h);return {x,y,w,h,canvas}}
 return trimTransparentArtwork(source);
}
function refineMagicAlpha(original,removed,mode){
 const width=removed.width,height=removed.height,base=document.createElement('canvas'),out=document.createElement('canvas');base.width=out.width=width;base.height=out.height=height;base.getContext('2d').drawImage(original,0,0,width,height);const source=base.getContext('2d',{willReadFrequently:true}).getImageData(0,0,width,height),mask=removed.getContext('2d',{willReadFrequently:true}).getImageData(0,0,width,height),pixels=source.data;
 for(let index=3;index<pixels.length;index+=4){const alpha=mask.data[index];pixels[index]=mode==='detail'?(alpha?Math.min(255,Math.round(alpha*1.2+8)):0):(alpha<=28?0:Math.min(255,Math.round((alpha-28)*1.12)))}
 out.getContext('2d').putImageData(source,0,0);return out;
}
function preserveMagicInterior(original,removed){
 const result=analyzeMagicRegions(original,removed);return {canvas:result.outsideCanvas,restored:result.interiorRemoved,exterior:result.exteriorRemoved,total:result.total};
}
function analyzeMagicRegions(original,removed){
 const width=removed.width,height=removed.height,total=width*height,originalCanvas=document.createElement('canvas');originalCanvas.width=width;originalCanvas.height=height;const originalContext=originalCanvas.getContext('2d',{willReadFrequently:true});originalContext.drawImage(original,0,0,width,height);const originalData=originalContext.getImageData(0,0,width,height),removedData=removed.getContext('2d',{willReadFrequently:true}).getImageData(0,0,width,height),source=originalData.data,mask=removedData.data,external=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0;
 const push=index=>{if(index<0||index>=total||external[index]||mask[index*4+3]>250)return;external[index]=1;queue[tail++]=index};
 for(let x=0;x<width;x++){push(x);push((height-1)*width+x)}for(let y=0;y<height;y++){push(y*width);push(y*width+width-1)}
 while(head<tail){const index=queue[head++],x=index%width,y=Math.floor(index/width);if(x)push(index-1);if(x<width-1)push(index+1);if(y)push(index-width);if(y<height-1)push(index+width)}
 const outsideData=new ImageData(new Uint8ClampedArray(source),width,height),insideData=new ImageData(new Uint8ClampedArray(source),width,height);let interiorRemoved=0,exteriorRemoved=0;
 for(let index=0;index<total;index++){const offset=index*4,originalAlpha=source[offset+3],cutAlpha=mask[offset+3];if(external[index]){outsideData.data[offset+3]=Math.min(originalAlpha,cutAlpha);insideData.data[offset+3]=originalAlpha;if(cutAlpha+8<originalAlpha)exteriorRemoved++}else{outsideData.data[offset+3]=originalAlpha;insideData.data[offset+3]=Math.min(originalAlpha,cutAlpha);if(cutAlpha+8<originalAlpha)interiorRemoved++}}
 const outsideCanvas=document.createElement('canvas'),insideCanvas=document.createElement('canvas');outsideCanvas.width=insideCanvas.width=width;outsideCanvas.height=insideCanvas.height=height;outsideCanvas.getContext('2d').putImageData(outsideData,0,0);insideCanvas.getContext('2d').putImageData(insideData,0,0);
 return {outsideCanvas,insideCanvas,interiorRemoved,exteriorRemoved,total};
}
// Remove somente o papel claro conectado às bordas. Diferente de uma remoção
// por cor global, os brancos dentro de letras (O, A, e, etc.) permanecem,
// assim como retângulos coloridos e seus detalhes internos.
function preserveMagicLettersAndBlocks(original){
 const width=original.width,height=original.height,total=width*height,out=canvasClone(original),context=out.getContext('2d',{willReadFrequently:true}),data=context.getImageData(0,0,width,height),pixels=data.data,seen=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0,removed=0;
 const isPaper=index=>{const offset=index*4,r=pixels[offset],g=pixels[offset+1],b=pixels[offset+2],a=pixels[offset+3];return a>0&&Math.min(r,g,b)>=210&&Math.max(r,g,b)-Math.min(r,g,b)<=38};
 const push=index=>{if(index<0||index>=total||seen[index]||!isPaper(index))return;seen[index]=1;queue[tail++]=index};
 for(let x=0;x<width;x++){push(x);push((height-1)*width+x)}
 for(let y=0;y<height;y++){push(y*width);push(y*width+width-1)}
 while(head<tail){const index=queue[head++],x=index%width,y=Math.floor(index/width);if(x)push(index-1);if(x<width-1)push(index+1);if(y)push(index-width);if(y<height-1)push(index+width)}
 for(let index=0;index<total;index++)if(seen[index]){pixels[index*4+3]=0;removed++}
 context.putImageData(data,0,0);return {canvas:out,removed};
}
// Recupera a moldura clara de fotografias pequenas (por exemplo, Polaroids)
// depois de remover o papel externo. Letras e blocos continuam protegidos pelo
// recorte anterior; aqui a detecção é limitada a regiões compactas e texturadas.
function preserveMagicPhotoFrames(original,paperCutout){
 const width=original.width,height=original.height,total=width*height,source=original.getContext('2d',{willReadFrequently:true}).getImageData(0,0,width,height),out=canvasClone(paperCutout),target=out.getContext('2d',{willReadFrequently:true}).getImageData(0,0,width,height),pixels=source.data,seen=new Uint8Array(total),queue=new Int32Array(total);let restored=0;
 const isContent=index=>{const i=index*4,r=pixels[i],g=pixels[i+1],b=pixels[i+2],a=pixels[i+3],min=Math.min(r,g,b),max=Math.max(r,g,b);return a>0&&(min<210||max-min>38)};
 const isPaper=index=>{const i=index*4,r=pixels[i],g=pixels[i+1],b=pixels[i+2],a=pixels[i+3];return a>0&&Math.min(r,g,b)>=188&&Math.max(r,g,b)-Math.min(r,g,b)<=58};
 const components=[];
 for(let start=0;start<total;start++){
  if(seen[start]||!isContent(start))continue;
  let head=0,tail=0,minX=width,minY=height,maxX=0,maxY=0,count=0,changes=0;queue[tail++]=start;seen[start]=1;
  while(head<tail){const index=queue[head++],x=index%width,y=Math.floor(index/width),i=index*4,r=pixels[i],g=pixels[i+1],b=pixels[i+2];count++;minX=Math.min(minX,x);minY=Math.min(minY,y);maxX=Math.max(maxX,x);maxY=Math.max(maxY,y);
   for(const next of [x?index-1:-1,x<width-1?index+1:-1,y?index-width:-1,y<height-1?index+width:-1]){if(next<0||seen[next]||!isContent(next))continue;const ni=next*4;if(Math.abs(r-pixels[ni])+Math.abs(g-pixels[ni+1])+Math.abs(b-pixels[ni+2])>90)changes++;seen[next]=1;queue[tail++]=next;}
  }
  const bw=maxX-minX+1,bh=maxY-minY+1,box=bw*bh,aspect=bw/Math.max(1,bh),density=count/Math.max(1,box),texture=changes/Math.max(1,count);
  if(count>40&&box<total*.16&&aspect>.42&&aspect<2.4&&density>.22&&texture>.11)components.push({minX,minY,maxX,maxY,bw,bh});
 }
 components.forEach(component=>{const pad=Math.max(2,Math.round(Math.min(component.bw,component.bh)*.075));for(let y=Math.max(0,component.minY-pad);y<=Math.min(height-1,component.maxY+pad);y++)for(let x=Math.max(0,component.minX-pad);x<=Math.min(width-1,component.maxX+pad);x++){const index=y*width+x,offset=index*4;if(target.data[offset+3]||!isPaper(index))continue;target.data[offset]=pixels[offset];target.data[offset+1]=pixels[offset+1];target.data[offset+2]=pixels[offset+2];target.data[offset+3]=pixels[offset+3];restored++;}});
 out.getContext('2d').putImageData(target,0,0);return {canvas:out,restored,components:components.length};
}
function analyzeMagicMask(removed){const data=removed.getContext('2d',{willReadFrequently:true}).getImageData(0,0,removed.width,removed.height).data;let transparent=0,soft=0;for(let index=3;index<data.length;index+=4){const alpha=data[index];if(alpha<=8)transparent++;else if(alpha<247)soft++}const total=Math.max(1,removed.width*removed.height);return {transparentRatio:transparent/total,softRatio:soft/total}}
function buildMagicVariant(source,cutout,label,mode){
 const trimmed=trimTransparentArtwork(cutout),frame=document.createElement('canvas');
 // O recorte é armazenado novamente na caixa original, sem redimensionar o
 // conteúdo. Assim a miniatura e o resultado usam a mesma proporção do objeto
 // selecionado e não esticam uma silhueta que tenha sido apenas aparada.
 frame.width=source.canvas.width;frame.height=source.canvas.height;frame.getContext('2d').drawImage(trimmed.canvas,trimmed.x,trimmed.y);
 return {id:source.id+'-'+mode,label,mode,canvas:frame,trim:{x:trimmed.x,y:trimmed.y,w:trimmed.w,h:trimmed.h},w:source.w,h:source.h,visualSample:magicVisualSample(frame)};
}
function magicPreparedFromVariant(source,variant){
 const trim=magicVariantTrim(variant),scale=source.magicScale||1,frameW=Math.max(1,source.canvas.width),frameH=Math.max(1,source.canvas.height);
 return {...variant,canvas:canvasClone(trim.canvas),trim,w:source.w*trim.w/frameW*scale,h:source.h*trim.h/frameH*scale,baseW:source.w*trim.w/frameW,baseH:source.h*trim.h/frameH,source};
}
function magicVisualSample(source){const sample=document.createElement('canvas'),size=48;sample.width=sample.height=size;const context=sample.getContext('2d',{alpha:true,willReadFrequently:true});context.clearRect(0,0,size,size);context.drawImage(source,0,0,size,size);return context.getImageData(0,0,size,size).data}
function magicVariantDifference(first,second){const a=first.visualSample||magicVisualSample(first.canvas),b=second.visualSample||magicVisualSample(second.canvas);let sum=0;for(let index=0;index<a.length;index+=4){const alpha=Math.abs(a[index+3]-b[index+3]),color=(Math.abs(a[index]-b[index])+Math.abs(a[index+1]-b[index+1])+Math.abs(a[index+2]-b[index+2]))/3;sum+=alpha*.72+color*.28}return sum/(a.length/4)}
function magicVariantIsAlmostOriginal(source,candidate){
 const original=magicVisualSample(source.canvas),trial=candidate.visualSample||magicVisualSample(candidate.canvas);let visible=0,unchanged=0;
 for(let index=0;index<original.length;index+=4){if(original[index+3]<9)continue;visible++;const alpha=Math.abs(original[index+3]-trial[index+3]),color=(Math.abs(original[index]-trial[index])+Math.abs(original[index+1]-trial[index+1])+Math.abs(original[index+2]-trial[index+2]))/3;if(alpha<16&&color<16)unchanged++}
 return visible>0&&unchanged/visible>=.95;
}
function automaticMagicVariants(source,removed){
 const options=[],regions=analyzeMagicRegions(source.canvas,removed),mask=analyzeMagicMask(removed),paperSafe=preserveMagicLettersAndBlocks(source.canvas),photoSafe=preserveMagicPhotoFrames(source.canvas,paperSafe.canvas),meaningful=Math.max(24,regions.total*.00025),hasOutside=regions.exteriorRemoved>meaningful,hasInside=regions.interiorRemoved>meaningful;
 const add=(cutout,label,mode,threshold=8,recommended=false)=>{if(options.length>=5)return null;let candidate;try{candidate=buildMagicVariant(source,cutout,label,mode)}catch(_){return null}if(magicVariantIsAlmostOriginal(source,candidate))return null;if(options.some(option=>magicVariantDifference(option,candidate)<threshold))return null;candidate.recommended=!!recommended;options.push(candidate);return candidate};
 add(removed,'Recorte total','total');
 // Prioridade para artes com texto, etiquetas e logotipos: este modo mantém
 // as ilhas internas claras e deixa transparentes apenas as áreas de papel
 // que chegam até a borda da imagem.
 if(photoSafe.restored>Math.max(8,meaningful*.1))add(photoSafe.canvas,'Preservar fotos, letras e blocos','photo-safe',3,true);
 if(paperSafe.removed>meaningful)add(paperSafe.canvas,'Preservar letras e blocos','outside-paper',4,!options.some(option=>option.recommended));
 if(hasOutside&&hasInside)add(regions.outsideCanvas,'Somente fundo externo','outside');
 if(hasInside)add(regions.insideCanvas,'Somente fundo interno','inside');
 const complex=mask.softRatio>.0015||mask.transparentRatio>.08||hasInside;
 if(complex)add(refineMagicAlpha(source.canvas,removed,'detail'),'Mais detalhes','detail',11);
 if(mask.softRatio>.003)add(refineMagicAlpha(source.canvas,removed,'clean'),'Borda limpa','clean',12);
 if(!options.length)throw new Error('A IA não encontrou um recorte diferente do original. Tente outra imagem ou use a remoção por clique.');
 if(!options.some(option=>option.recommended))options[0].recommended=true;
 return options;
}
function rotateCanvas90(source){
 const out=document.createElement('canvas'),context=out.getContext('2d');
 out.width=source.height;out.height=source.width;
 context.translate(out.width,0);context.rotate(Math.PI/2);context.drawImage(source,0,0);
 return out;
}
function buildMagicPacking(areaW,areaH,artW,artH,gap){
 const normalFits=artW<=areaW+.01&&artH<=areaH+.01,rotatedFits=artH<=areaW+.01&&artW<=areaH+.01;
 if(!normalFits){if(!rotatedFits)return {normalCols:0,normalRows:0,rotatedCols:0,rotatedRows:0,total:0,rotatedTotal:0,placements:[]};const cols=Math.floor((areaW+gap)/(artH+gap)),rows=Math.floor((areaH+gap)/(artW+gap)),blockW=cols*artH+(cols-1)*gap,blockH=rows*artW+(rows-1)*gap,startX=(areaW-blockW)/2,startY=(areaH-blockH)/2,placements=[];for(let row=0;row<rows;row++)for(let column=0;column<cols;column++)placements.push({x:startX+column*(artH+gap),y:startY+row*(artW+gap),w:artH,h:artW,rotated:true});return {normalCols:0,normalRows:0,rotatedCols:cols,rotatedRows:rows,total:placements.length,rotatedTotal:placements.length,placements}}
 const normalCols=Math.max(1,Math.floor((areaW+gap)/(artW+gap))),normalRows=Math.max(1,Math.floor((areaH+gap)/(artH+gap)));
 let best={normalCols,normalRows,rotatedCols:0,rotatedRows:0,total:normalCols*normalRows,rotatedTotal:0};
 // Testa uma faixa lateral com cópias giradas. Ela só é usada quando realmente
 // aumenta a quantidade de artes que cabem, mantendo o intervalo físico.
 const possibleRotatedRows=Math.floor((areaH+gap)/(artW+gap));
 if(possibleRotatedRows>0&&Math.abs(artW-artH)>.5){
  for(let cols=1;cols<=normalCols;cols++){
   const normalBlockW=cols*artW+(cols-1)*gap,remainingW=areaW-normalBlockW-gap;
   if(remainingW<artH)continue;
   const rotatedCols=Math.floor((remainingW+gap)/(artH+gap));
   if(rotatedCols<1)continue;
   const rotatedTotal=rotatedCols*possibleRotatedRows,total=cols*normalRows+rotatedTotal;
   if(total>best.total||(total===best.total&&rotatedTotal<best.rotatedTotal))best={normalCols:cols,normalRows,rotatedCols,rotatedRows:possibleRotatedRows,total,rotatedTotal};
  }
 }
 const placements=[],normalBlockW=best.normalCols*artW+(best.normalCols-1)*gap,normalBlockH=best.normalRows*artH+(best.normalRows-1)*gap;
 if(!best.rotatedTotal){
  const startX=(areaW-normalBlockW)/2,startY=(areaH-normalBlockH)/2;
  for(let row=0;row<best.normalRows;row++)for(let column=0;column<best.normalCols;column++)placements.push({x:startX+column*(artW+gap),y:startY+row*(artH+gap),w:artW,h:artH,rotated:false});
  return {...best,placements};
 }
 const rotatedBlockW=best.rotatedCols*artH+(best.rotatedCols-1)*gap,rotatedBlockH=best.rotatedRows*artW+(best.rotatedRows-1)*gap,usedW=rotatedBlockW+gap+normalBlockW,usedH=Math.max(rotatedBlockH,normalBlockH),startX=(areaW-usedW)/2,startY=(areaH-usedH)/2,normalX=startX+rotatedBlockW+gap,normalY=startY+(usedH-normalBlockH)/2,rotatedY=startY+(usedH-rotatedBlockH)/2;
 // As cópias normais são criadas primeiro; as últimas, giradas, ocupam a faixa esquerda.
 for(let row=0;row<best.normalRows;row++)for(let column=0;column<best.normalCols;column++)placements.push({x:normalX+column*(artW+gap),y:normalY+row*(artH+gap),w:artW,h:artH,rotated:false});
 for(let row=0;row<best.rotatedRows;row++)for(let column=0;column<best.rotatedCols;column++)placements.push({x:startX+column*(artH+gap),y:rotatedY+row*(artW+gap),w:artH,h:artW,rotated:true});
 return {...best,placements};
}
async function prepareMagicSources(){
 if(!magicSession.sources.length)return false;
 const token=++magicSession.token,sources=[...magicSession.sources],device=navigator.gpu?'gpu':'cpu';let modelReady=false;magicSession.prepared=[];magicSession.variants=[];magicSession.rawCuts=[];magicSession.improved=false;magicSession.variantsReady=false;magicSession.preparing=true;setMagicStage('analyzing');ui.magicConfirm.disabled=true;
 magicStep('source','done');magicStep('model','active');magicStep('remove','');magicStep('layout','');magicStep('apply','');renderMagicSources();updateMagicCapacity();
 try{
  magicProgress(5,browserBgModelReady?'IA local pronta.':'Preparando a IA local…');const api=await preloadBrowserBgModel();if(token!==magicSession.token)return false;
  if(token!==magicSession.token)return false;modelReady=true;magicStep('model','done');magicStep('remove','active');
  const prepared=[],variants=[],rawCuts=[];
  for(let index=0;index<sources.length;index++){
   const source=sources[index],input=await canvasToBlob(source.canvas,'image/png',1),start=28+(index/sources.length)*42,span=42/sources.length;let highest=0;
   magicProgress(start,'Removendo fundo da imagem '+(index+1)+' de '+sources.length+'…');
   const output=await api.removeBackground(input,{device,model:'isnet_fp16',output:{format:'image/png',type:'foreground'},progress:(key,current,total)=>{if(!total||token!==magicSession.token)return;highest=Math.max(highest,current/total);magicProgress(start+highest*span,'Processando imagem '+(index+1)+' de '+sources.length+' no navegador…')}});
   if(token!==magicSession.token)return false;if(!/^image\//i.test(output.type||''))throw new Error('A IA não devolveu uma imagem válida.');
   const image=await loadBlobImage(output),removed=imgToCanvas(image);magicProgress(start+span*.9,'Criando opções inteligentes…');const allOptions=automaticMagicVariants(source,removed),variant=allOptions.find(option=>option.recommended)||allOptions[0],options=allOptions.slice(0,Math.min(3,allOptions.length));source.magicScale=1;prepared.push(magicPreparedFromVariant(source,variant));variants.push({source,selectedId:variant.id,options,availableOptions:allOptions.slice(options.length),allOptions});rawCuts.push({source,canvas:removed});
  }
  if(token!==magicSession.token)return false;magicSession.prepared=prepared;magicSession.variants=variants;magicSession.rawCuts=rawCuts;magicSession.preparing=false;magicStep('remove','done');setMagicStage('review');renderMagicSources();renderMagicVariants();updateMagicCapacity();magicProgress(70,'Finalizando as miniaturas de recorte…');await nextPaint();if(token!==magicSession.token)return false;magicSession.variantsReady=true;updateMagicCapacity();magicProgress(72,'Opções de recorte prontas.');finish('Recortes prontos');return true;
 }catch(error){
  if(token!==magicSession.token)return false;magicSession.preparing=false;magicSession.prepared=[];magicStep('model',modelReady?'done':'error');magicStep('remove',modelReady?'error':'');ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='Não foi possível analisar as imagens: '+(error&&error.message?error.message:String(error));ui.magicConfirm.disabled=true;magicProgress(0,'Falha na análise. Tente novamente.');finish('Erro');log('Falha no preenchimento mágico',error&&error.message?error.message:error);return false;
 }
}
async function openMagicDialog(){
 if(!ui.magicModal)return;
 let chosen=selectedObjects();const hasObjects=state.objects.length>0;if(!chosen.length&&state.objects.length===1){selectObject(state.objects[0],false);chosen=selectedObjects()}const missing=hasObjects&&!chosen.length,token=magicSession.token+1;
 magicSession={token,sources:chosen.map(object=>magicSourceFromObject(object)),prepared:[],variants:[],rawCuts:[],packing:null,stage:'idle',preparing:false,applying:false,complete:false,improved:false,cleanEdges:true,enhanceColors:false,variantsReady:false,autoHeight:true,autoHeightNeeded:false};if(chosen.length>1)magicSession.sources.forEach(source=>source.quantity=1);resetMagicSteps();ui.magicModal.classList.remove('dtf-magic-busy');ui.magicModal.classList.toggle('dtf-magic-no-selection',missing);ui.magicModal.hidden=false;if(ui.magicAutoHeight)ui.magicAutoHeight.checked=true;if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;setMagicStage('idle');ui.magicConfirm.hidden=false;ui.magicConfirm.textContent='Avançar';ui.magicConfirm.disabled=true;ui.magicCancel.disabled=false;if(ui.magicProgressBar)ui.magicProgressBar.style.width='0%';if(ui.magicVariants)ui.magicVariants.innerHTML='';
 ui.magicUploadWrap.hidden=hasObjects;renderMagicSources();
 if(!hasObjects){ui.magicMessage.textContent='Envie uma imagem para iniciar a montagem.';ui.magicProgressText.textContent='Aguardando imagem.';ui.magicCapacity.textContent='Envie uma imagem para calcular as cópias.';ui.magicUploadButton.focus();return}
 if(missing){ui.magicMessage.textContent='Selecione uma ou mais imagens e abra esta função novamente.';ui.magicProgressText.textContent='Aguardando seleção.';updateMagicCapacity();ui.magicCancel.textContent='Entendi';ui.magicCancel.focus();return}
 ui.magicMessage.textContent=chosen.length===1?'A imagem original será recuperada antes do recorte.':chosen.length+' imagens originais serão recuperadas antes do recorte.';
 prepareMagicSources();
}
async function loadMagicUpload(file){
 if(!file)return;if(!/^image\//i.test(file.type||'')&&!/\.(avif|bmp|gif|heic|heif|ico|jpe?g|png|svg|tiff?|webp)$/i.test(file.name||'')){ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='Escolha um arquivo de imagem compatível.';return}
 magicSession.preparing=true;magicStep('source','active');setMagicBusy(true);
 try{magicProgress(2,'Lendo a imagem…');let image,resolution;if(isTiffFile(file)){const pages=await decodeTiffFile(file);image=pages[0].canvas;resolution=pages[0].resolution}else [image,resolution]=await Promise.all([loadBlobImage(file),getImageResolution(file)]);if(ui.magicModal.hidden)return;const object=imageToObject(image,(file.name||'imagem').replace(/\.[^.]+$/,''),'magic-upload',resolution);object.id=uid();magicSession.sources=[magicSourceFromObject(object,true)];magicSession.prepared=[];magicSession.variants=[];magicSession.rawCuts=[];magicSession.variantsReady=false;magicSession.packing=null;ui.magicMessage.textContent='Imagem pronta para análise.';magicStep('source','done');magicSession.preparing=false;setMagicBusy(false);renderMagicSources();await prepareMagicSources()}catch(error){magicSession.preparing=false;setMagicBusy(false);ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='Não foi possível abrir a imagem: '+(error&&error.message?error.message:String(error));magicProgress(0,'Falha ao abrir a imagem.');finish('Erro')}
}
function advanceMagicLayout(){
 if(magicSession.stage!=='review'||magicSession.prepared.length!==magicSession.sources.length)return;hideMagicZoom();setMagicStage('layout');renderMagicSources();magicStep('layout','active');magicProgress(82,'Calculando a montagem…');updateMagicCapacity();renderMagicLayoutPreview();
}
function finishMagicResult(message,status){
 magicSession.complete=true;magicSession.stage='complete';magicProgress(100,message);finish(message);setStatus(status);hideMagicZoom();if(ui.magicLayoutPreview)ui.magicLayoutPreview.hidden=true;ui.magicModal.hidden=true;if(ui.magicFill)ui.magicFill.focus();
}
function magicVariantRegistry(){
 const registry={},ids=[];magicSession.variants.forEach((group,index)=>{const id='smart_'+uid();ids[index]=id;registry[id]={id,options:(group.allOptions||group.options||[]).map(option=>({id:option.id,label:option.label,mode:option.mode,w:option.w,h:option.h,trim:option.trim?{...option.trim}:null,canvas:canvasClone(option.canvas)}))}});return {registry,ids};
}
function applyMagicFinishing(source){
 if(!magicSession.cleanEdges&&!magicSession.enhanceColors)return canvasClone(source);const out=canvasClone(source),context=out.getContext('2d',{willReadFrequently:true}),data=context.getImageData(0,0,out.width,out.height),pixels=data.data;
 for(let index=0;index<pixels.length;index+=4){let alpha=pixels[index+3];if(!alpha)continue;const r=pixels[index],g=pixels[index+1],b=pixels[index+2],max=Math.max(r,g,b),min=Math.min(r,g,b),saturation=max-min;if(magicSession.cleanEdges&&alpha<245&&max>170&&saturation<34)alpha=Math.round(alpha*.62);if(magicSession.enhanceColors){const avg=(r+g+b)/3;pixels[index]=clamp(Math.round(avg+(r-avg)*1.12),0,255);pixels[index+1]=clamp(Math.round(avg+(g-avg)*1.12),0,255);pixels[index+2]=clamp(Math.round(avg+(b-avg)*1.12),0,255)}pixels[index+3]=alpha}
 context.putImageData(data,0,0);return out;
}
async function finalizeMagicCutout(){
 if(magicSession.stage!=='review'||!magicSession.variantsReady||magicSession.prepared.length!==magicSession.sources.length||magicSession.applying)return;clearError();magicSession.applying=true;ui.magicConfirm.disabled=true;ui.magicCutoutOnly.disabled=true;setMagicBusy(true);
 try{
  magicStep('apply','active');magicProgress(88,'Aplicando o recorte escolhido…');const variants=magicVariantRegistry(),groupId=magicSession.prepared.length>1?uid():null,objects=magicSession.prepared.map((item,index)=>{const source=item.source,artwork=applyMagicFinishing(item.canvas),x=clamp(source.object.x,0,Math.max(0,canvas.width-item.w)),y=clamp(source.object.y,0,Math.max(0,canvas.height-item.h));return {id:uid(),name:source.name+' — recorte',x,y,w:item.w,h:item.h,visible:true,opacity:source.object.opacity==null?1:source.object.opacity,locked:false,groupId,canvas:artwork,baseCanvas:canvasClone(artwork),aiOriginalCanvas:canvasClone(source.canvas),aiOriginalW:source.w,aiOriginalH:source.h,aiFrameW:source.canvas.width,aiFrameH:source.canvas.height,aiTrimW:item.trim.w,aiTrimH:item.trim.h,originalW:artwork.width,originalH:artwork.height,sourceType:'ai-cutout',intelligentVariantSetId:variants.ids[index],intelligentVariantId:item.id,intelligentVariantRotated:false}});pushHistory('Antes de aplicar o recorte da IA');state.intelligentVariantSets=variants.registry;state.objects=objects;state.selectedIds=objects.map(object=>object.id);state.selectedId=objects[0]?.id||null;state.transparent=true;if(ui.transparent)ui.transparent.checked=true;render();updateSelection();updateObjectUI();magicStep('apply','done');finishMagicResult('Recorte aplicado.',objects.length+' recorte'+(objects.length===1?' aplicado':'s aplicados'));
 }catch(error){magicStep('apply','error');magicProgress(72,'Falha ao aplicar. O projeto foi preservado.');showError('Falha ao aplicar recorte',error)}finally{magicSession.applying=false;setMagicBusy(false);ui.magicCancel.disabled=false}
}
async function runMagicFill(){
 if(magicSession.complete){closeMagicDialog();return}
 if(magicSession.stage==='review'){if(!magicSession.variantsReady||magicSession.preparing)return;advanceMagicLayout();return}
 if(magicSession.stage!=='layout')return;
 if(magicSession.preparing||!updateMagicCapacity())return;
 const packing=currentMagicPacking();if(!packing||!packing.fits)return;const token=++magicSession.token;clearError();magicSession.applying=true;magicSession.stage='applying';ui.magicConfirm.disabled=true;ui.magicFill.disabled=true;ui.magicFill.setAttribute('aria-busy','true');magicStep('layout','active');setMagicBusy(true);
 try{
  magicProgress(84,'Montagem calculada. Preparando as cópias…');magicStep('layout','done');magicStep('apply','active');const variants=magicVariantRegistry(),objects=[],rotatedCache=new Map(),sourceCounts=new Map();
  for(let index=0;index<packing.placements.length;index++){
   if(token!==magicSession.token)return;
   const place=packing.placements[index],prepared=magicSession.prepared[place.sourceIndex];if(!prepared)throw new Error('A montagem encontrou uma imagem sem recorte preparado.');let sourceCanvas=applyMagicFinishing(prepared.canvas);if(place.rotated){if(!rotatedCache.has(place.sourceIndex))rotatedCache.set(place.sourceIndex,rotateCanvas90(sourceCanvas));sourceCanvas=rotatedCache.get(place.sourceIndex)}const artwork=canvasClone(sourceCanvas),base=canvasClone(sourceCanvas),count=(sourceCounts.get(place.sourceIndex)||0)+1;sourceCounts.set(place.sourceIndex,count);objects.push({id:uid(),name:prepared.source.name+' '+count+(place.rotated?' — 90°':''),x:place.x,y:place.y,w:place.w,h:place.h,visible:true,opacity:prepared.source.object.opacity==null?1:prepared.source.object.opacity,locked:false,groupId:null,canvas:artwork,baseCanvas:base,aiOriginalCanvas:canvasClone(prepared.source.canvas),aiOriginalW:prepared.source.w,aiOriginalH:prepared.source.h,aiFrameW:prepared.source.canvas.width,aiFrameH:prepared.source.canvas.height,aiTrimW:prepared.trim.w,aiTrimH:prepared.trim.h,originalW:artwork.width,originalH:artwork.height,sourceType:place.rotated?'ai-cutout-rotated':'ai-cutout',intelligentVariantSetId:variants.ids[place.sourceIndex],intelligentVariantId:prepared.id,intelligentVariantRotated:place.rotated});if(index&&index%25===0){magicProgress(86+Math.round(index/packing.placements.length*10),'Criando cópia '+(index+1)+' de '+packing.placements.length+'…');await new Promise(resolve=>requestAnimationFrame(resolve))}
  }
  if(token!==magicSession.token)return;pushHistory('Antes da montagem inteligente');if(packing.autoHeight){state.workHmm=Math.max(10,packing.requiredHeight*25.4/state.dpi);syncWorkspaceUI()}state.intelligentVariantSets=variants.registry;state.objects=objects;state.selectedId=objects.length?objects[0].id:null;state.selectedIds=state.selectedId?[state.selectedId]:[];state.transparent=true;if(ui.transparent)ui.transparent.checked=true;render();updateSelection();updateObjectUI();magicStep('apply','done');finishMagicResult('Montagem concluída.',packing.total+' objeto'+(packing.total===1?' criado':'s criados')+' — separados com 4 mm'+(packing.autoHeight?' · altura ajustada para '+state.workHmm.toFixed(1)+' mm':''));
 }catch(error){if(token!==magicSession.token)return;magicSession.stage='layout';magicStep('apply','error');ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='A montagem não foi aplicada: '+(error&&error.message?error.message:String(error));magicProgress(82,'Falha ao aplicar. O projeto foi preservado.');ui.magicConfirm.disabled=false;finish('Erro');log('Falha ao aplicar montagem inteligente',error&&error.message?error.message:error)}finally{magicSession.applying=false;setMagicBusy(false);ui.magicFill.disabled=false;ui.magicFill.removeAttribute('aria-busy')}
}
if(ui.magicFill)ui.magicFill.addEventListener('click',openMagicDialog);
if(ui.magicCancel)ui.magicCancel.addEventListener('click',closeMagicDialog);
if(ui.magicConfirm)ui.magicConfirm.addEventListener('click',runMagicFill);
if(ui.magicCutoutOnly)ui.magicCutoutOnly.addEventListener('click',finalizeMagicCutout);
if(ui.magicAutoHeight)ui.magicAutoHeight.addEventListener('change',()=>{magicSession.autoHeight=ui.magicAutoHeight.checked;updateMagicCapacity();renderMagicLayoutPreview()});
if(ui.magicUploadButton)ui.magicUploadButton.addEventListener('click',()=>{ui.magicUpload.value='';ui.magicUpload.click()});
if(ui.magicUpload)ui.magicUpload.addEventListener('change',()=>{const file=ui.magicUpload.files&&ui.magicUpload.files[0];if(file)loadMagicUpload(file);ui.magicUpload.value=''});
if(ui.magicModal){ui.magicModal.addEventListener('pointerdown',event=>{if(event.target===ui.magicModal)closeMagicDialog()});ui.magicModal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeMagicDialog()}})}

async function loadPdfLib(){if(window.pdfjsLib)return window.pdfjsLib;const urls=['https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js','https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js'];let last=null;for(const u of urls){try{await new Promise((res,rej)=>{const s=document.createElement('script');s.src=u;s.onload=()=>window.pdfjsLib?res():rej(new Error('PDF.js carregou sem API'));s.onerror=()=>rej(new Error('Falha ao carregar '+u));document.head.appendChild(s)});if(window.pdfjsLib)return window.pdfjsLib}catch(e){last=e}}throw last||new Error('PDF.js não disponível')}
async function openPdf(bytes){const lib=await loadPdfLib();try{lib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'}catch(e){}try{return await lib.getDocument({data:bytes}).promise}catch(e){log('PDF worker falhou, tentando modo alternativo',e.message);try{lib.GlobalWorkerOptions.workerSrc='';return await lib.getDocument({data:bytes,disableWorker:true}).promise}catch(e2){throw e2}}}
async function convertPdfPage(pdf,pageNo,dpi){const page=await pdf.getPage(pageNo);const base=page.getViewport({scale:1});let scale=dpi/72;let est=base.width*base.height*scale*scale;if(est>MAX_PX)scale=Math.sqrt(MAX_PX/(base.width*base.height));const vp=page.getViewport({scale});const off=document.createElement('canvas');off.width=Math.ceil(vp.width);off.height=Math.ceil(vp.height);const oc=off.getContext('2d',{alpha:false,willReadFrequently:true});progress(38,'Renderizando página '+pageNo+' em '+dpi+' DPI...');await page.render({canvasContext:oc,viewport:vp,background:'rgb(255,255,255)'}).promise;progress(62,'Criando PNG real...');return canvasToBlob(off,'image/png',1)}
function parsePdfPageSelection(value,total){const pages=new Set(),invalid=[];for(const raw of String(value||'').split(/[,;\s]+/).map(token=>token.trim()).filter(Boolean)){const range=raw.match(/^(\d+)\s*[-–]\s*(\d+)$/),single=raw.match(/^\d+$/);if(!range&&!single){invalid.push(raw);continue}const start=range?Number(range[1]):Number(raw),end=range?Number(range[2]):start;if(start<1||end<1||start>total||end>total||start>end){invalid.push(raw);continue}for(let page=start;page<=end;page++)pages.add(page)}return {pages:[...pages].sort((a,b)=>a-b),invalid}}
function choosePdfPages(total){if(total<=1)return Promise.resolve([1]);return new Promise(resolve=>{const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-pdf-page-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','dtfPdfChoiceTitle');modal.innerHTML='<div class="dtf-properties-box dtf-pdf-page-box"><h3 id="dtfPdfChoiceTitle">Importar PDF com '+total+' páginas</h3><p class="dtf-pdf-page-intro">Escolha quais páginas devem ser importadas. Elas serão colocadas em sequência vertical.</p><div class="dtf-pdf-page-options"><label><input type="radio" name="dtfPdfImportMode" value="all" checked> Importar todas as páginas (1–'+total+')</label><label><input type="radio" name="dtfPdfImportMode" value="selected"> Selecionar páginas</label></div><div class="dtf-pdf-page-select"><input type="text" data-pdf-pages placeholder="Ex.: 1, 3-5" disabled><small>Use vírgulas ou intervalos.</small></div><small class="dtf-pdf-page-error" data-pdf-error></small><div class="dtf-properties-actions"><button type="button" data-pdf-cancel>Cancelar</button><button type="button" data-pdf-confirm>Importar</button></div></div>';document.body.appendChild(modal);const all=modal.querySelector('input[value="all"]'),selected=modal.querySelector('input[value="selected"]'),input=modal.querySelector('[data-pdf-pages]'),error=modal.querySelector('[data-pdf-error]');const close=pages=>{modal.remove();resolve(pages)};const update=()=>{input.disabled=!selected.checked;if(selected.checked)input.focus();error.textContent=''};all.addEventListener('change',update);selected.addEventListener('change',update);modal.querySelector('[data-pdf-cancel]').addEventListener('click',()=>close(null));modal.querySelector('[data-pdf-confirm]').addEventListener('click',()=>{if(all.checked){close(Array.from({length:total},(_,index)=>index+1));return}const result=parsePdfPageSelection(input.value,total);if(result.invalid.length||!result.pages.length){error.textContent=result.invalid.length?'Página inválida: '+result.invalid.join(', '):'Informe ao menos uma página.';input.focus();return}close(result.pages)});modal.addEventListener('pointerdown',event=>{if(event.target===modal)close(null)});modal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close(null)}});all.focus()})}
function addImportedObjects(objects,label){if(!objects.length)return;pushHistory('Antes de importar '+label);if(ensureWorkspaceFitsImported(objects))syncWorkspaceUI();state.objects.push(...objects);objects.forEach(o=>{pendingRemovalGuideIds.add(o.id);scheduleSmartAreaGuidePreparation(o)});state.selectedIds=objects.map(o=>o.id);state.selectedId=objects[objects.length-1].id;render();updateSelection();updateObjectUI();fitWidthZoom(false)}
async function loadFile(f){clearError();if(!f)return;try{progress(5,'Lendo arquivo...');setStatus('Carregando '+(f.name||'imagem')+'...');if(/\.pdf$/i.test(f.name)||f.type==='application/pdf'){state.pdfFileName=f.name.replace(/\.pdf$/i,'');const bytes=await f.arrayBuffer();state.pdf=await openPdf(bytes);state.pdfDpi=parseInt(ui.pdfDpi.value,10)||300;progress(18,'PDF aberto — '+state.pdf.numPages+' páginas...');const pages=await choosePdfPages(state.pdf.numPages);if(!pages||!pages.length){state.pdf=null;finish('Importação cancelada');setStatus('Importação do PDF cancelada');return}state.pdfPage=pages[0];const imported=[],gap=physicalMmToPx(4);let y=0;for(let index=0;index<pages.length;index++){const pageNo=pages[index];progress(20+Math.round(index/pages.length*65),'Preparando página '+pageNo+' de '+pages.length+'...');const blob=await convertPdfPage(state.pdf,pageNo,state.pdfDpi),img=await loadBlobImage(blob),obj=imageToObject(img,state.pdfFileName+'_pagina_'+pageNo,'pdf-png',{x:state.pdfDpi,y:state.pdfDpi},{allowWorkspaceExpand:true});obj.pdfPage=pageNo;obj.pdfPages=state.pdf.numPages;obj.pdfDpi=state.pdfDpi;obj.x=0;obj.y=y;imported.push(obj);y+=obj.h+gap;await nextPaint()}if(imported.length)y-=gap;addImportedObjects(imported,'PDF');ui.pdfPage.innerHTML=Array.from({length:state.pdf.numPages},(_,index)=>'<option value="'+(index+1)+'">Página '+(index+1)+'</option>').join('');ui.pdfPage.value=String(state.pdfPage);ui.pdfPage.disabled=false;ui.pdfConvert.disabled=false;finish('PDF importado');setStatus(pages.length+' página'+(pages.length===1?'':'s')+' do PDF importada'+(pages.length===1?'':'s')+' em sequência vertical; área ajustada quando necessário');return}
 if(isTiffFile(f)){const pages=await decodeTiffFile(f),name=(f.name||'imagem').replace(/\.[^.]+$/,''),imported=[],gap=physicalMmToPx(4);let y=0;for(let index=0;index<pages.length;index++){const page=pages[index],pageName=pages.length>1?name+'_pagina_'+(index+1):name,obj=imageToObject(page.canvas,pageName,'tiff',page.resolution,{allowWorkspaceExpand:true});obj.x=0;obj.y=y;imported.push(obj);y+=obj.h+gap}addImportedObjects(imported,'TIFF');ui.pdfPage.innerHTML='<option value="1">1</option>';ui.pdfPage.disabled=true;ui.pdfConvert.disabled=true;state.pdf=null;finish('TIFF importado');setStatus(pages.length+' página'+(pages.length===1?'':'s')+' do TIFF importada'+(pages.length===1?'':'s')+' com transparência, orientação e DPI preservados');return}
 const [img,resolution]=await Promise.all([loadBlobImage(f),getImageResolution(f)]);const obj=imageToObject(img,(f.name||'imagem').replace(/\.[^.]+$/,''),'image',resolution,{allowWorkspaceExpand:true});if(!obj.canvas.width||!obj.canvas.height)throw new Error('A imagem foi lida, mas não tem dimensões válidas.');addObject(obj);render();fitWidthZoom(false);ui.pdfPage.innerHTML='<option value="1">1</option>';ui.pdfPage.disabled=true;ui.pdfConvert.disabled=true;state.pdf=null;finish('Imagem carregada');setStatus('Imagem carregada — medida física '+pxToMm(obj.w).toFixed(1)+' × '+pxToMm(obj.h).toFixed(1)+' mm; área ajustada quando necessário')}catch(e){finish('Erro');const detail=new Error((e&&e.message?e.message:String(e))+' Formatos aceitos: PNG, JPEG, WebP, TIFF e PDF.');showError('Falha ao carregar arquivo',detail)}}

fileInput.addEventListener('change',async e=>{const files=Array.from(e.target.files||[]);if(!files.length){setStatus('Nenhum arquivo selecionado');return}for(let i=0;i<files.length;i++){setStatus('Lendo '+files[i].name+' ('+(i+1)+'/'+files.length+')…');await loadFile(files[i])}fileInput.value=''});
function chooseEditorFile(){fileInput.value='';fileInput.click();}
ui.load.addEventListener('click',chooseEditorFile);
function canvasToProjectData(c){if(!c||!c.width||!c.height)throw new Error('Objeto sem imagem válida para salvar.');return c.toDataURL('image/png')}
function projectData(){return {format:'PrintWay Editor Project',formatVersion:2,appVersion:VERSION,savedAt:new Date().toISOString(),workspace:{widthMm:state.workWmm,heightMm:state.workHmm,dpi:state.dpi,bgColor:state.bgColor,transparent:state.transparent,unit:unitSelect?unitSelect.value:'mm'},guides:state.customGuides.map(guide=>({id:guide.id,orientation:guide.orientation,valueMm:guide.valueMm})),selectedId:state.selectedId,selectedIds:[...(state.selectedIds||[])],intelligentVariantSets:Object.fromEntries(Object.entries(state.intelligentVariantSets||{}).map(([id,set])=>[id,{id,options:(set.options||[]).map(option=>({id:option.id,label:option.label,mode:option.mode,w:option.w,h:option.h,trim:option.trim?{...option.trim}:null,canvas:canvasToProjectData(option.canvas)}))}])),objects:state.objects.map(o=>({id:o.id,name:o.name,x:o.x,y:o.y,w:o.w,h:o.h,visible:o.visible!==false,opacity:o.opacity==null?1:o.opacity,locked:o.locked===true,sourceType:o.sourceType||'image',groupId:o.groupId||null,originalW:o.originalW||o.canvas.width,originalH:o.originalH||o.canvas.height,aiOriginalW:o.aiOriginalW||null,aiOriginalH:o.aiOriginalH||null,aiFrameW:o.aiFrameW||null,aiFrameH:o.aiFrameH||null,aiTrimW:o.aiTrimW||null,aiTrimH:o.aiTrimH||null,pdfPage:o.pdfPage||null,pdfPages:o.pdfPages||null,pdfDpi:o.pdfDpi||null,intelligentVariantSetId:o.intelligentVariantSetId||null,intelligentVariantId:o.intelligentVariantId||null,intelligentVariantRotated:o.intelligentVariantRotated===true,canvas:canvasToProjectData(o.canvas),baseCanvas:canvasToProjectData(o.baseCanvas||o.canvas),aiOriginalCanvas:o.aiOriginalCanvas?canvasToProjectData(o.aiOriginalCanvas):null}))}}
const projectPickerTypes=[{description:'Projeto do Editor PrintWay',accept:{'application/json':['.pwedit']}}];
async function writeProjectFile(handle,blob){const writable=await handle.createWritable();try{await writable.write(blob);await writable.close()}catch(e){try{await writable.abort()}catch(_){}throw e}}
async function saveEditorProject(){try{progress(8,'Preparando projeto...');const payload=JSON.stringify(projectData());const blob=new Blob([payload],{type:'application/x-printway-editor-project'});const stamp=new Date().toISOString().replace(/[:.]/g,'-').replace('T','_').slice(0,19),base=(currentProjectName||('projeto_printway_'+stamp)).replace(/[^a-z0-9_-]+/gi,'_');let savedInPlace=false;if(currentProjectHandle&&typeof currentProjectHandle.createWritable==='function'){await writeProjectFile(currentProjectHandle,blob);savedInPlace=true}else if(window.isSecureContext&&typeof window.showSaveFilePicker==='function'){const handle=await window.showSaveFilePicker({suggestedName:base+'.pwedit',types:projectPickerTypes,excludeAcceptAllOption:false});await writeProjectFile(handle,blob);currentProjectHandle=handle;currentProjectName=String(handle.name||base).replace(/\.pwedit$/i,'');savedInPlace=true}else{saveBlob(blob,base+'.pwedit')}clearAutosave();setProjectDirty(false);finish('Projeto salvo');setStatus(savedInPlace?'Projeto salvo no mesmo arquivo local':'Projeto .pwedit baixado localmente');return true}catch(e){if(e&&e.name==='AbortError'){finish('Cancelado');setStatus('Salvamento cancelado');return false}finish('Erro');showError('Falha ao salvar projeto',e);return false}}
function canvasFromProjectData(data){return new Promise((resolve,reject)=>{if(typeof data!=='string'||!data.startsWith('data:image/')){reject(new Error('Imagem do projeto inválida.'));return}const image=new Image();image.onload=()=>{try{const c=document.createElement('canvas');c.width=image.naturalWidth||image.width;c.height=image.naturalHeight||image.height;if(!c.width||!c.height)throw new Error('Imagem do projeto sem dimensões.');c.getContext('2d').drawImage(image,0,0);resolve(c)}catch(e){reject(e)}};image.onerror=()=>reject(new Error('Não foi possível ler uma imagem do projeto.'));image.src=data})}
function confirmUnsavedChanges(action){if(!projectDirty)return Promise.resolve('discard');return new Promise(resolve=>{const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-unsaved-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','dtfUnsavedTitle');modal.innerHTML='<div class="dtf-properties-box dtf-unsaved-box"><h3 id="dtfUnsavedTitle">Alterações não salvas</h3><p>O projeto atual foi alterado. Deseja salvá-lo antes de '+action+'?</p><div class="dtf-properties-actions"><button type="button" data-cancel>Cancelar</button><button type="button" data-discard>Não salvar</button><button type="button" data-save>Salvar</button></div></div>';document.body.appendChild(modal);let closed=false;const done=value=>{if(closed)return;closed=true;modal.remove();resolve(value)};modal.querySelector('[data-cancel]').onclick=()=>done('cancel');modal.querySelector('[data-discard]').onclick=()=>done('discard');modal.querySelector('[data-save]').onclick=async()=>{const button=modal.querySelector('[data-save]');button.disabled=true;button.textContent='Salvando…';if(await saveEditorProject())done('saved');else{button.disabled=false;button.textContent='Salvar'}};modal.addEventListener('pointerdown',e=>{if(e.target===modal)done('cancel')});modal.addEventListener('keydown',e=>{if(e.key==='Escape')done('cancel')});modal.querySelector('[data-save]').focus()})}
async function openEditorProject(file,handle=null){if(!file)return false;const decision=await confirmUnsavedChanges('abrir outro projeto');if(decision==='cancel')return false;try{progress(8,'Abrindo projeto...');const raw=await file.text(),data=JSON.parse(raw);if(!data||data.format!=='PrintWay Editor Project'||!Array.isArray(data.objects))throw new Error('Arquivo não é um projeto .pwedit válido.');const workspace=data.workspace||{},savedWidth=Number(workspace.widthMm??data.workWmm),savedHeight=Number(workspace.heightMm??data.workHmm),width=clamp(Number.isFinite(savedWidth)&&savedWidth>0?savedWidth:280,10,1000),height=clamp(Number.isFinite(savedHeight)&&savedHeight>0?savedHeight:100,10,10000),dpi=Math.max(1,Number(workspace.dpi)||300),intelligentVariantSets={};for(const [setId,set] of Object.entries(data.intelligentVariantSets||{})){const options=[];for(const option of set.options||[])options.push({id:String(option.id||uid()),label:String(option.label||'Recorte inteligente'),mode:String(option.mode||'standard'),w:Number(option.w)||1,h:Number(option.h)||1,trim:option.trim&&Number(option.trim.w)>0&&Number(option.trim.h)>0?{x:Number(option.trim.x)||0,y:Number(option.trim.y)||0,w:Number(option.trim.w),h:Number(option.trim.h)}:null,canvas:await canvasFromProjectData(option.canvas)});if(options.length)intelligentVariantSets[setId]={id:setId,options}}const objects=[];for(let i=0;i<data.objects.length;i++){const item=data.objects[i];progress(15+Math.round(i/Math.max(1,data.objects.length)*55),'Carregando objeto '+(i+1)+' de '+data.objects.length+'...');const c=await canvasFromProjectData(item.canvas),base=await canvasFromProjectData(item.baseCanvas||item.canvas),aiOriginal=item.aiOriginalCanvas?await canvasFromProjectData(item.aiOriginalCanvas):null;objects.push({id:String(item.id||uid()),name:String(item.name||'Objeto '+(i+1)),x:Number(item.x)||0,y:Number(item.y)||0,w:Number(item.w)||c.width,h:Number(item.h)||c.height,visible:item.visible!==false,opacity:clamp(Number(item.opacity==null?1:item.opacity),0,1),locked:item.locked===true,sourceType:item.sourceType||'image',groupId:item.groupId||null,originalW:Number(item.originalW)||c.width,originalH:Number(item.originalH)||c.height,aiOriginalW:Number(item.aiOriginalW)||null,aiOriginalH:Number(item.aiOriginalH)||null,aiFrameW:Number(item.aiFrameW)||null,aiFrameH:Number(item.aiFrameH)||null,aiTrimW:Number(item.aiTrimW)||null,aiTrimH:Number(item.aiTrimH)||null,pdfPage:item.pdfPage||null,pdfPages:item.pdfPages||null,pdfDpi:item.pdfDpi||null,intelligentVariantSetId:item.intelligentVariantSetId&&intelligentVariantSets[item.intelligentVariantSetId]?item.intelligentVariantSetId:null,intelligentVariantId:item.intelligentVariantId||null,intelligentVariantRotated:item.intelligentVariantRotated===true,canvas:c,baseCanvas:base,aiOriginalCanvas:aiOriginal})}state.historyLock=true;state.workWmm=width;state.workHmm=height;state.dpi=dpi;state.bgColor=typeof workspace.bgColor==='string'?workspace.bgColor:'#ffffff';state.transparent=workspace.transparent!==false;state.intelligentVariantSets=intelligentVariantSets;state.objects=objects;state.selectedIds=(Array.isArray(data.selectedIds)?data.selectedIds:[]).filter(id=>state.objects.some(o=>o.id===id));state.selectedId=state.objects.some(o=>o.id===data.selectedId)?data.selectedId:(state.selectedIds[state.selectedIds.length-1]||null);state.undo=[];state.redo=[];state.pdf=null;if(unitSelect&&['mm','cm','m','px'].includes(workspace.unit))unitSelect.value=workspace.unit;state.historyLock=false;syncWorkspaceUI();state.objects.forEach(o=>{o.w=clamp(o.w,1,canvas.width);o.h=clamp(o.h,1,canvas.height);o.x=clamp(o.x,0,Math.max(0,canvas.width-o.w));o.y=clamp(o.y,0,Math.max(0,canvas.height-o.h))});currentProjectName=String(file.name||'projeto').replace(/\.pwedit$/i,'');currentProjectHandle=handle;fitWidthZoom(false);render();updateSelection();updateObjectUI();updateHistoryUI();setProjectDirty(false);finish('Projeto aberto');setStatus('Projeto .pwedit aberto com '+state.objects.length+' objeto(s)');return true}catch(e){state.historyLock=false;finish('Erro');showError('Falha ao abrir projeto',e);return false}}
async function offerAutosaveRecovery(){
 if(autosaveRecoveryShown)return;autosaveRecoveryShown=true;
 const saved=readAutosave();if(!saved)return;
 const date=new Date(saved.savedAt).toLocaleString();
 const recover=window.confirm('Foi encontrado um salvamento automático de '+date+'. Deseja recuperar este projeto?');
 if(!recover){clearAutosave();return}
 try{
  const file=new File([JSON.stringify(saved.project)],'recuperacao-automatica.pwedit',{type:'application/json'});
  const opened=await openEditorProject(file);
  if(opened){setProjectDirty(true);setStatus('Projeto recuperado automaticamente. Salve uma cópia para mantê-lo.');}
 }catch(error){log('Falha ao recuperar salvamento automático',error&&error.message?error.message:error);setStatus('Não foi possível recuperar o salvamento automático.');}
}
async function newEditorProject(){const decision=await confirmUnsavedChanges('criar um novo projeto');if(decision==='cancel')return;state.historyLock=true;state.workWmm=280;state.workHmm=100;state.dpi=300;state.bgColor='#ffffff';state.transparent=true;state.objects=[];state.intelligentVariantSets={};state.selectedId=null;state.selectedIds=[];state.zoom=1;state.panX=0;state.panY=0;state.drag=null;state.marquee=null;state.tool='select';state.undo=[];state.redo=[];state.clipboard=[];state.pdf=null;state.pdfFileName='documento';state.pdfPage=1;state.pdfDpi=300;state.historyLock=false;autoToleranceSuggestedIds.clear();currentProjectName='';currentProjectHandle=null;propertyClipboard=null;guidesEnabled=false;if(inheritPaste)inheritPaste.disabled=true;if(pastePropMenu)pastePropMenu.style.display='none';if(unitSelect)unitSelect.value='mm';if(ui.eraser)ui.eraser.classList.remove('active');if(ui.restoreBrush)ui.restoreBrush.classList.remove('active');[clickRemove,areaRemoveButton,globalColorRemoveButton,fillTool].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});guidesButton.classList.remove('active');guidesButton.setAttribute('aria-pressed','false');updateGuidesOverlay();editorRoot.classList.remove('dtf-color-remove-cursor');try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:280,h:100,dpi:300}));localStorage.setItem('printway_dtf_unit','mm')}catch(_){}clearAutosave();syncWorkspaceUI();fitWidthZoom(false);render();updateSelection();updateObjectUI();updateHistoryUI();setProjectDirty(false);setStatus('Novo projeto criado')}
/* Mantém as guias nos projetos .pwedit antigos e novos sem alterar a leitura
 * compatível dos objetos já existentes. */
const openEditorProjectWithoutGuides=openEditorProject;
openEditorProject=async function(file,handle=null){
 let savedGuides=[];
 try{const data=JSON.parse(await file.text());savedGuides=Array.isArray(data.guides)?data.guides:[]}catch(_){}
 const opened=await openEditorProjectWithoutGuides(file,handle);
 if(opened){state.customGuides=sanitizeCustomGuides(savedGuides);state.selectedGuideId=null;renderCustomGuides()}
 return opened;
};
newEditorProject=async function(){
 const decision=await confirmUnsavedChanges('criar um novo projeto');if(decision==='cancel')return;
 state.historyLock=true;state.workWmm=280;state.workHmm=100;state.dpi=300;state.bgColor='#ffffff';state.transparent=true;
 state.objects=[];state.intelligentVariantSets={};state.selectedId=null;state.selectedIds=[];state.customGuides=[];state.selectedGuideId=null;
 state.zoom=1;state.panX=0;state.panY=0;state.drag=null;state.marquee=null;state.tool='select';state.undo=[];state.redo=[];state.clipboard=[];
 state.pdf=null;state.pdfFileName='documento';state.pdfPage=1;state.pdfDpi=300;state.historyLock=false;
 autoToleranceSuggestedIds.clear();currentProjectName='';currentProjectHandle=null;propertyClipboard=null;guidesEnabled=false;
 if(inheritPaste)inheritPaste.disabled=true;if(pastePropMenu)pastePropMenu.style.display='none';if(unitSelect)unitSelect.value='mm';
 if(ui.eraser)ui.eraser.classList.remove('active');if(ui.restoreBrush)ui.restoreBrush.classList.remove('active');
 [clickRemove,areaRemoveButton,globalColorRemoveButton,fillTool].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});
 guidesButton.classList.remove('active');guidesButton.setAttribute('aria-pressed','false');updateGuidesOverlay();renderCustomGuides();editorRoot.classList.remove('dtf-color-remove-cursor');
 try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:280,h:100,dpi:300}));localStorage.setItem('printway_dtf_unit','mm')}catch(_){}
 clearAutosave();syncWorkspaceUI();fitWidthZoom(false);render();updateSelection();updateObjectUI();updateHistoryUI();setProjectDirty(false);setStatus('Novo projeto criado');
};
const projectInput=$id('dtfProjectInput');
async function chooseProjectFile(){if(window.isSecureContext&&typeof window.showOpenFilePicker==='function'){try{const handles=await window.showOpenFilePicker({multiple:false,types:projectPickerTypes,excludeAcceptAllOption:false}),handle=handles&&handles[0];if(handle){const file=await handle.getFile();await openEditorProject(file,handle)}return}catch(e){if(e&&e.name==='AbortError')return}}if(projectInput){projectInput.value='';projectInput.click()}}
if(projectInput)projectInput.addEventListener('change',e=>{const file=e.target.files&&e.target.files[0];if(file)openEditorProject(file);projectInput.value=''})
if(ui.newProject)ui.newProject.addEventListener('click',newEditorProject);
if(ui.openProject)ui.openProject.addEventListener('click',chooseProjectFile);
if(ui.saveProject)ui.saveProject.addEventListener('click',saveEditorProject);
window.addEventListener('beforeunload',e=>{if(!projectDirty)return;writeAutosave();e.preventDefault();e.returnValue=''})

async function replaceSelectedWithPdfPage(){if(!state.pdf)return;if(!selected()){setStatus('Nenhum objeto selecionado');return}try{pushHistory('Antes de reconverter página PDF');progress(8,'Convertendo página PDF...');const blob=await convertPdfPage(state.pdf,state.pdfPage,state.pdfDpi);const img=await loadBlobImage(blob);const o=selected();const fresh=imageToObject(img,o.name,'pdf-png',{x:state.pdfDpi,y:state.pdfDpi});o.canvas=fresh.canvas;o.baseCanvas=fresh.baseCanvas;o.w=fresh.w;o.h=fresh.h;o.x=(canvas.width-o.w)/2;o.y=(canvas.height-o.h)/2;render();finish('Página PDF atualizada');setStatus('Página '+state.pdfPage+' atualizada em '+state.pdfDpi+' DPI')}catch(e){finish('Erro');showError('Falha ao reconverter página PDF',e)}}

ui.applyWork.addEventListener('click',()=>{
  const nw=clamp(unitToMm(parseFloat(ui.workW.value)||280),10,1000);
  const nh=clamp(unitToMm(parseFloat(ui.workH.value)||100),10,10000);
  const nd=parseInt(ui.dpi.value,10)||300;
  if(nw===state.workWmm&&nh===state.workHmm&&nd===state.dpi){
    setStatus('Área de trabalho sem alteração');
    return;
  }
  pushHistory('Antes de alterar área de trabalho');

  /*
   * IMPORTANTE:
   * Alterar a área de trabalho NÃO altera x/y/w/h dos objetos.
   * O objeto mantém exatamente o mesmo tamanho e a mesma posição em pixels.
   * Apenas o "papel" (canvas) muda de tamanho.
   *
   * Isso corrige o problema anterior que escalava a arte junto com a área.
   */
  state.workWmm=nw;
  state.workHmm=nh;
  state.dpi=nd;
  try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:nw,h:nh,dpi:nd}))}catch(_){ }

  syncWorkspaceUI();
  render();
  setStatus('Área de trabalho alterada: objetos mantidos no mesmo tamanho e posição');
})
function applyObjectBackground(o,color){const c=document.createElement('canvas');c.width=o.canvas.width;c.height=o.canvas.height;const cc=c.getContext('2d');cc.fillStyle=color;cc.fillRect(0,0,c.width,c.height);cc.drawImage(o.canvas,0,0);o.canvas=c}
ui.bgColor.addEventListener('input',()=>{state.bgColor=ui.bgColor.value;const items=selectedObjects();if(items.length){pushHistory('Antes de aplicar cor de fundo');items.forEach(o=>applyObjectBackground(o,state.bgColor))}else setProjectDirty(true);render()});ui.transparent.addEventListener('change',()=>{state.transparent=ui.transparent.checked;setProjectDirty(true);render()});


  ui.pdfPage.addEventListener('change',async()=>{
    if(!state.pdf) return;
    state.pdfPage=clamp(parseInt(ui.pdfPage.value,10)||1,1,state.pdf.numPages);
    const o=selected();
    if(!o||o.sourceType!=='pdf-png'){setStatus('Selecione o objeto convertido do PDF');return;}
    try{
      pushHistory('Antes de trocar página PDF');
      progress(8,'Convertendo página '+state.pdfPage+'...');
      const blob=await convertPdfPage(state.pdf,state.pdfPage,parseInt(ui.pdfDpi.value,10)||300);
      const img=await loadBlobImage(blob);
      const pdfResolution=parseInt(ui.pdfDpi.value,10)||300,constFresh=imageToObject(img,o.name,'pdf-png',{x:pdfResolution,y:pdfResolution});
      o.canvas=constFresh.canvas;o.baseCanvas=constFresh.baseCanvas;o.w=constFresh.w;o.h=constFresh.h;o.x=(canvas.width-o.w)/2;o.y=(canvas.height-o.h)/2;
      render();finish('Página PDF convertida');setStatus('Página '+state.pdfPage+' carregada como PNG');
    }catch(err){finish('Erro');showError('Falha ao trocar página do PDF',err)}
  });

  ui.pdfConvert.addEventListener('click',async()=>{
    if(!state.pdf){setStatus('Nenhum PDF carregado');return;}
    const o=selected();if(!o||o.sourceType!=='pdf-png'){setStatus('Selecione o objeto PDF');return;}
    state.pdfPage=clamp(parseInt(ui.pdfPage.value,10)||1,1,state.pdf.numPages);
    state.pdfDpi=clamp(parseInt(ui.pdfDpi.value,10)||300,72,600);
    try{
      pushHistory('Antes de reconverter PDF');progress(8,'Reconstruindo PNG em '+state.pdfDpi+' DPI...');
      const blob=await convertPdfPage(state.pdf,state.pdfPage,state.pdfDpi);
      const img=await loadBlobImage(blob);
      o.canvas=imgToCanvas(img);o.baseCanvas=canvasFromData(cloneCanvasData(o.canvas));
      render();finish('PNG PDF atualizado');setStatus('PDF atualizado em '+state.pdfDpi+' DPI');
    }catch(err){finish('Erro');showError('Falha ao reconverter PDF',err)}
  });

function whiteRemove(o,t){const c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data,th=t/100*Math.sqrt(3*255*255),soft=Math.max(1,th*(0.02+parseInt(ui.edge.value,10)/100*.16));const mask=new Uint8Array(w*h);for(let i=0;i<w*h;i++){const j=i*4;if(a[j+3]&&Math.hypot(255-a[j],255-a[j+1],255-a[j+2])<=th)mask[i]=1}const bg=new Uint8Array(w*h),q=[];const push=i=>{if(i>=0&&i<w*h&&!bg[i]&&mask[i]){bg[i]=1;q.push(i)}};for(let x=0;x<w;x++){push(x);push((h-1)*w+x)}for(let y=0;y<h;y++){push(y*w);push(y*w+w-1)}for(let head=0;head<q.length;head++){const i=q[head],x=i%w,y=Math.floor(i/w);if(x)push(i-1);if(x<w-1)push(i+1);if(y)push(i-w);if(y<h-1)push(i+w)}let changed=0;for(let i=0;i<w*h;i++){if(!bg[i])continue;const j=i*4;const dist=Math.hypot(255-a[j],255-a[j+1],255-a[j+2]);if(dist<=Math.max(0,th-soft)){a[j+3]=0;changed++}else if(dist<=th){a[j+3]=Math.round(a[j+3]*((dist-(th-soft))/soft))}}cc.putImageData(d,0,0);return changed}
function borderColorProfile(data,w,h){
 const perimeter=Math.max(1,2*w+2*h-4),step=Math.max(1,Math.floor(perimeter/420)),bins=new Map(),samples=[];let sampled=0,transparent=0;
 const add=index=>{sampled++;const alpha=data[index*4+3];if(alpha<8){transparent++;return}const r=data[index*4],g=data[index*4+1],b=data[index*4+2],key=(r>>5)+'/'+(g>>5)+'/'+(b>>5),entry=bins.get(key)||{count:0,r:0,g:0,b:0};entry.count++;entry.r+=r;entry.g+=g;entry.b+=b;bins.set(key,entry);samples.push({r,g,b})};
 for(let x=0;x<w;x+=step){add(x);if(h>1)add((h-1)*w+x)}for(let y=step;y<h-1;y+=step){add(y*w);if(w>1)add(y*w+w-1)}
 const colors=[...bins.values()].sort((a,b)=>b.count-a.count).slice(0,4).map(entry=>({r:entry.r/entry.count,g:entry.g/entry.count,b:entry.b/entry.count,count:entry.count}));
 return {colors,samples,sampled,transparent};
}
function rgbDistance(a,b){return Math.hypot(a.r-b.r,a.g-b.g,a.b-b.b)}
function recommendedBackgroundTolerance(o){
 try{const c=o.canvas,context=c.getContext('2d',{willReadFrequently:true}),data=context.getImageData(0,0,c.width,c.height).data,profile=borderColorProfile(data,c.width,c.height),primary=profile.colors[0];if(!primary||!profile.samples.length)return 20;const dominantShare=primary.count/profile.samples.length,spread=profile.samples.reduce((sum,color)=>sum+Math.min(255,rgbDistance(color,primary)),0)/profile.samples.length;return clamp(Math.round(10+spread*.19+(1-dominantShare)*16),10,58)}catch(_){return 20}
}
function setRecommendedBackgroundTolerance(o){
 if(!o||autoToleranceSuggestedIds.has(o.id)||!ui.tol)return false;
 const suggestion=recommendedBackgroundTolerance(o);autoToleranceSuggestedIds.add(o.id);ui.tol.value=String(suggestion);if(ui.tolValue)ui.tolValue.textContent=String(suggestion);return true;
}
function smartBackgroundRemove(o,t){
 const c=o.canvas,cc=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height,image=cc.getImageData(0,0,w,h),a=image.data,profile=borderColorProfile(a,w,h),primary=profile.colors[0];if(!primary||profile.transparent>profile.sampled*.35)return 0;
 const threshold=clamp(18+(Number(t)||0)*2.65,18,283),secondary=profile.colors.filter((color,index)=>index===0||color.count>=primary.count*.42&&rgbDistance(color,primary)<threshold*.72),seen=new Uint8Array(w*h),queue=new Int32Array(w*h);let head=0,tail=0,changed=0;
 const distance=index=>{const offset=index*4,pixel={r:a[offset],g:a[offset+1],b:a[offset+2]};return Math.min(...secondary.map(color=>rgbDistance(pixel,color)))};
 const seed=index=>{if(index<0||index>=seen.length||seen[index]||a[index*4+3]<8||distance(index)>threshold)return;seen[index]=1;queue[tail++]=index};
 for(let x=0;x<w;x++){seed(x);if(h>1)seed((h-1)*w+x)}for(let y=1;y<h-1;y++){seed(y*w);if(w>1)seed(y*w+w-1)}
 while(head<tail){const index=queue[head++],offset=index*4,dist=distance(index),fadeStart=threshold*.84;a[offset+3]=dist<=fadeStart?0:Math.round(a[offset+3]*((dist-fadeStart)/Math.max(1,threshold-fadeStart)));changed++;const x=index%w,y=Math.floor(index/w),neighbors=[x?index-1:-1,x<w-1?index+1:-1,y?index-w:-1,y<h-1?index+w:-1];for(const next of neighbors)seed(next)}
 cc.putImageData(image,0,0);return changed;
}
/* Operações de pixels grandes rodam em Web Worker quando disponível. Isso
 * mantém a interface responsiva durante a remoção de fundo e mantém um
 * caminho síncrono de segurança em navegadores antigos. */
const PIXEL_WORKER_SOURCE=`self.onmessage=function(event){const msg=event.data||{};if(msg.type!=='process')return;const id=msg.id,w=msg.width,h=msg.height,total=w*h,data=new Uint8ClampedArray(msg.data),t=Math.max(0,Number(msg.tolerance)||0);const report=value=>self.postMessage({id,type:'progress',value:Math.max(0,Math.min(1,value))});try{let changed=0;if(msg.operation==='background'){let sr=0,sg=0,sb=0,count=0;const add=index=>{const o=index*4;if(data[o+3]<8)return;sr+=data[o];sg+=data[o+1];sb+=data[o+2];count++};for(let x=0;x<w;x++){add(x);if(h>1)add((h-1)*w+x)}for(let y=1;y<h-1;y++){add(y*w);if(w>1)add(y*w+w-1)}if(!count){report(1);self.postMessage({id,type:'done',data:data.buffer,changed},[data.buffer]);return}sr/=count;sg/=count;sb/=count;const threshold=Math.max(18,Math.min(283,18+t*2.65)),seen=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0;const distance=index=>{const o=index*4;return Math.hypot(data[o]-sr,data[o+1]-sg,data[o+2]-sb)};const seed=index=>{if(index<0||index>=total||seen[index]||data[index*4+3]<8||distance(index)>threshold)return;seen[index]=1;queue[tail++]=index};for(let x=0;x<w;x++){seed(x);if(h>1)seed((h-1)*w+x)}for(let y=1;y<h-1;y++){seed(y*w);if(w>1)seed(y*w+w-1)}while(head<tail){const index=queue[head++],o=index*4,dist=distance(index),fadeStart=threshold*.84;data[o+3]=dist<=fadeStart?0:Math.round(data[o+3]*((dist-fadeStart)/Math.max(1,threshold-fadeStart)));changed++;const x=index%w,y=Math.floor(index/w);seed(x?index-1:-1);seed(x<w-1?index+1:-1);seed(y?index-w:-1);seed(y<h-1?index+w:-1);if((head&8191)===0)report(head/Math.max(1,tail)*.8)} }else if(msg.operation==='color'){const color=msg.color||{r:255,g:255,b:255},threshold=t/100*Math.sqrt(3*255*255);for(let index=0;index<total;index++){const o=index*4;if(data[o+3]&&Math.hypot(data[o]-color.r,data[o+1]-color.g,data[o+2]-color.b)<=threshold){data[o+3]=0;changed++}if((index&16383)===0)report(index/Math.max(1,total)*.9)}}else throw new Error('Operação de pixels desconhecida.');report(1);self.postMessage({id,type:'done',data:data.buffer,changed},[data.buffer])}catch(error){self.postMessage({id,type:'error',message:error&&error.message?error.message:String(error)})}};`;
const PIXEL_WORKER_SOURCE_V2=`self.onmessage=function(event){const msg=event.data||{};if(msg.type!=='process')return;const id=msg.id,w=msg.width,h=msg.height,total=w*h,data=new Uint8ClampedArray(msg.data),t=Math.max(0,Number(msg.tolerance)||0);const report=value=>self.postMessage({id,type:'progress',value:Math.max(0,Math.min(1,value))});try{let changed=0;if(msg.operation==='background'){const bins=new Map(),sample=index=>{const o=index*4;if(data[o+3]<8)return;const key=(data[o]>>5)+'/'+(data[o+1]>>5)+'/'+(data[o+2]>>5),entry=bins.get(key)||{count:0,r:0,g:0,b:0};entry.count++;entry.r+=data[o];entry.g+=data[o+1];entry.b+=data[o+2];bins.set(key,entry)};for(let x=0;x<w;x++){sample(x);if(h>1)sample((h-1)*w+x)}for(let y=1;y<h-1;y++){sample(y*w);if(w>1)sample(y*w+w-1)}const colors=[...bins.values()].sort((a,b)=>b.count-a.count).slice(0,4).map(entry=>({r:entry.r/entry.count,g:entry.g/entry.count,b:entry.b/entry.count,count:entry.count})),primary=colors[0];if(!primary){report(1);self.postMessage({id,type:'done',data:data.buffer,changed},[data.buffer]);return}const threshold=Math.max(18,Math.min(283,18+t*2.65)),secondary=colors.filter((color,index)=>index===0||color.count>=primary.count*.42&&Math.hypot(color.r-primary.r,color.g-primary.g,color.b-primary.b)<threshold*.72),distance=index=>{const o=index*4;return Math.min(...secondary.map(color=>Math.hypot(data[o]-color.r,data[o+1]-color.g,data[o+2]-color.b)))},seen=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0;const seed=index=>{if(index<0||index>=total||seen[index]||data[index*4+3]<8||distance(index)>threshold)return;seen[index]=1;queue[tail++]=index};for(let x=0;x<w;x++){seed(x);if(h>1)seed((h-1)*w+x)}for(let y=1;y<h-1;y++){seed(y*w);if(w>1)seed(y*w+w-1)}while(head<tail){const index=queue[head++],o=index*4,dist=distance(index),fadeStart=threshold*.84;data[o+3]=dist<=fadeStart?0:Math.round(data[o+3]*((dist-fadeStart)/Math.max(1,threshold-fadeStart)));changed++;const x=index%w,y=Math.floor(index/w);seed(x?index-1:-1);seed(x<w-1?index+1:-1);seed(y?index-w:-1);seed(y<h-1?index+w:-1);if((head&8191)===0)report(head/Math.max(1,tail)*.8)}}else if(msg.operation==='color'){const color=msg.color||{r:255,g:255,b:255},threshold=t/100*Math.sqrt(3*255*255);for(let index=0;index<total;index++){const o=index*4;if(data[o+3]&&Math.hypot(data[o]-color.r,data[o+1]-color.g,data[o+2]-color.b)<=threshold){data[o+3]=0;changed++}if((index&16383)===0)report(index/Math.max(1,total)*.9)}}else throw new Error('Operação de pixels desconhecida.');report(1);self.postMessage({id,type:'done',data:data.buffer,changed},[data.buffer])}catch(error){self.postMessage({id,type:'error',message:error&&error.message?error.message:String(error)})}};`;
let pixelWorker=null,pixelWorkerUrl='',pixelWorkerId=0,pixelWorkerPending=new Map();
function getPixelWorker(){if(pixelWorker)return pixelWorker;if(typeof Worker==='undefined')throw new Error('Web Worker não disponível');pixelWorkerUrl=URL.createObjectURL(new Blob([PIXEL_WORKER_SOURCE_V2],{type:'application/javascript'}));pixelWorker=new Worker(pixelWorkerUrl);pixelWorker.onmessage=event=>{const message=event.data||{},pending=pixelWorkerPending.get(message.id);if(!pending)return;if(message.type==='progress'){if(pending.onProgress)pending.onProgress(message.value*100);return}pixelWorkerPending.delete(message.id);if(message.type==='error'){pending.reject(new Error(message.message||'Falha no processamento em segundo plano'));return}if(message.type==='done'){const imageData=new ImageData(new Uint8ClampedArray(message.data),pending.width,pending.height);pending.resolve({imageData,changed:Number(message.changed)||0})}};pixelWorker.onerror=event=>{const error=new Error(event&&event.message||'Falha no processamento em segundo plano');pixelWorkerPending.forEach(pending=>pending.reject(error));pixelWorkerPending.clear();pixelWorker=null};return pixelWorker}
function runPixelWorker(imageData,operation,options={},onProgress){const worker=getPixelWorker(),id='pixel_'+(++pixelWorkerId),buffer=imageData.data.buffer.slice(0);return new Promise((resolve,reject)=>{pixelWorkerPending.set(id,{resolve,reject,width:imageData.width,height:imageData.height,onProgress});try{worker.postMessage({type:'process',id,operation,width:imageData.width,height:imageData.height,data:buffer,tolerance:Number(options.tolerance)||0,color:options.color||null},[buffer])}catch(error){pixelWorkerPending.delete(id);reject(error)}})}
async function smartBackgroundRemoveAsync(o,t,onProgress){const context=o.canvas.getContext('2d',{willReadFrequently:true}),image=context.getImageData(0,0,o.canvas.width,o.canvas.height);try{const result=await runPixelWorker(image,'background',{tolerance:t},onProgress);context.putImageData(result.imageData,0,0);return result.changed}catch(error){log('Processamento em segundo plano indisponível; usando processamento local',error&&error.message?error.message:error);await nextPaint();return smartBackgroundRemove(o,t)}}
function selectedColorRemove(o,color,t){const c=o.canvas,cc=c.getContext('2d'),d=cc.getImageData(0,0,c.width,c.height),a=d.data,th=t/100*Math.sqrt(3*255*255);let n=0;for(let i=0;i<a.length;i+=4){if(!a[i+3])continue;if(Math.hypot(a[i]-color.r,a[i+1]-color.g,a[i+2]-color.b)<=th){a[i+3]=0;n++}}cc.putImageData(d,0,0);return n}
async function selectedColorRemoveAsync(o,color,t,onProgress){const context=o.canvas.getContext('2d',{willReadFrequently:true}),image=context.getImageData(0,0,o.canvas.width,o.canvas.height);try{const result=await runPixelWorker(image,'color',{tolerance:t,color},onProgress);context.putImageData(result.imageData,0,0);return result.changed}catch(error){log('Remoção de cor em segundo plano indisponível; usando processamento local',error&&error.message?error.message:error);await nextPaint();return selectedColorRemove(o,color,t)}}
function contiguousRemove(o,wx,wy,t){const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height);const c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data;if(lx<0||ly<0||lx>=w||ly>=h)return 0;const ti=(ly*w+lx)*4,target={r:a[ti],g:a[ti+1],b:a[ti+2]},th=t/100*Math.sqrt(3*255*255),seen=new Uint8Array(w*h),q=[ly*w+lx];seen[ly*w+lx]=1;let n=0;while(q.length){const i=q.pop(),j=i*4;if(!a[j+3])continue;const dist=Math.hypot(a[j]-target.r,a[j+1]-target.g,a[j+2]-target.b);if(dist>th)continue;const edge=Math.max(Math.abs(a[j]-a[j+4]||0),Math.abs(a[j+1]-a[j+5]||0),Math.abs(a[j+2]-a[j+6]||0));a[j+3]=dist>th*.72?Math.round(a[j+3]*.35):0;n++;const x=i%w,y=Math.floor(i/w);for(const ni of [x?i-1:-1,x<w-1?i+1:-1,y?i-w:-1,y<h-1?i+w:-1])if(ni>=0&&!seen[ni]){seen[ni]=1;q.push(ni)}}cc.putImageData(d,0,0);return n}
// A IA local produz uma máscara de primeiro plano que serve somente como
// guia de contorno. Ela não apaga nada sozinha: a escolha de cor continua
// sendo do usuário e o resultado fica limitado à região em que ele clicou.
const smartAreaGuideCache=new WeakMap(),smartAreaGuideTasks=new WeakMap();
let smartAreaRemovalBusy=false,smartAreaRemovalToken=0,smartAreaStroke=null;
const SMART_AREA_LEARNING_KEY='printway_dtf_ai_area_learning_v1';
let pendingSmartAreaFeedback=null;
function loadSmartAreaLearning(){try{const saved=JSON.parse(localStorage.getItem(SMART_AREA_LEARNING_KEY)||'null');return {version:1,boundaryBias:clamp(Number(saved&&saved.boundaryBias)||0,0,18),examples:Array.isArray(saved&&saved.examples)?saved.examples.slice(-24):[]}}catch(_){return {version:1,boundaryBias:0,examples:[]}}}
const smartAreaLearning=loadSmartAreaLearning();
function saveSmartAreaLearning(){try{localStorage.setItem(SMART_AREA_LEARNING_KEY,JSON.stringify(smartAreaLearning))}catch(_){}}
function settleSmartAreaFeedback(accepted){if(!pendingSmartAreaFeedback)return;const example={...pendingSmartAreaFeedback,accepted:!!accepted,at:Date.now()};smartAreaLearning.examples.push(example);smartAreaLearning.examples=smartAreaLearning.examples.slice(-24);smartAreaLearning.boundaryBias=clamp(smartAreaLearning.boundaryBias+(accepted?-.15:2.5),0,18);pendingSmartAreaFeedback=null;saveSmartAreaLearning()}
function rememberSmartAreaFeedback(stroke){const total=Math.max(1,stroke.width*stroke.height);pendingSmartAreaFeedback={rule:3,historyDepth:state.undo.length,tolerance:Number(stroke.tolerance)||35,effectiveSensitivity:Number(stroke.effectiveSensitivity)||35,removedRatio:Number((stroke.changed/total).toFixed(5)),usedAi:!!stroke.usedAi,protectedForeground:!!stroke.protectForeground}}
function smartAreaProtectionMask(guide,width,height,sensitivity){if(!guide||!guide.alpha||guide.alpha.length!==width*height)return null;const threshold=clamp(Math.round(76-sensitivity*.42),28,72),source=new Uint8Array(width*height);for(let index=0;index<source.length;index++)if(guide.alpha[index]>=threshold)source[index]=1;const radius=clamp(Math.round(Math.min(width,height)/750)+1,1,4);let current=source;for(let pass=0;pass<radius;pass++){const next=current.slice();for(let y=0;y<height;y++)for(let x=0;x<width;x++){const index=y*width+x;if(current[index])continue;if((x&&current[index-1])||(x<width-1&&current[index+1])||(y&&current[index-width])||(y<height-1&&current[index+width]))next[index]=1}current=next}return current}
async function getSmartAreaGuide(o,onProgress){
 const cached=smartAreaGuideCache.get(o);if(cached&&cached.source===o.canvas)return cached;
 const pending=smartAreaGuideTasks.get(o);if(pending)return pending;
 const task=(async()=>{
  const source=o.canvas;if(!source||!source.width||!source.height)throw new Error('A imagem não possui uma área válida para analisar.');
  if(onProgress)onProgress(8,'Preparando a IA local para reconhecer as bordas…');
  const api=await preloadBrowserBgModel(),input=await canvasToBlob(source,'image/png',1);let last=-1;
  const output=await api.removeBackground(input,{device:navigator.gpu?'gpu':'cpu',model:'isnet_fp16',output:{format:'image/png',type:'foreground'},progress:(key,current,total)=>{if(!onProgress||!total)return;const amount=18+Math.round(Math.max(0,Math.min(1,current/total))*66);if(amount>last){last=amount;onProgress(amount,'IA local identificando as bordas da área…')}}});
  if(!/^image\//i.test(output&&output.type||''))throw new Error('A IA não devolveu uma máscara de bordas válida.');
  const result=imgToCanvas(await loadBlobImage(output)),guideCanvas=document.createElement('canvas');guideCanvas.width=source.width;guideCanvas.height=source.height;guideCanvas.getContext('2d',{alpha:true}).drawImage(result,0,0,guideCanvas.width,guideCanvas.height);
  const guidePixels=guideCanvas.getContext('2d',{willReadFrequently:true}).getImageData(0,0,guideCanvas.width,guideCanvas.height).data,alpha=new Uint8Array(source.width*source.height);
  for(let index=0,pixel=3;index<alpha.length;index++,pixel+=4)alpha[index]=guidePixels[pixel];
  const guide={source,width:source.width,height:source.height,alpha};smartAreaGuideCache.set(o,guide);if(onProgress)onProgress(88,'Bordas reconhecidas. Aplicando a remoção apenas nesta área…');return guide;
 })();
 smartAreaGuideTasks.set(o,task);try{return await task}finally{if(smartAreaGuideTasks.get(o)===task)smartAreaGuideTasks.delete(o)}
}
const smartAreaGuideWarmTimers=new WeakMap();let smartAreaGuideWarmQueue=Promise.resolve();
function scheduleSmartAreaGuidePreparation(o){const cached=o&&smartAreaGuideCache.get(o);if(!o||!o.canvas||cached&&cached.source===o.canvas||smartAreaGuideTasks.has(o)||smartAreaGuideWarmTimers.has(o))return;const timer=window.setTimeout(()=>{smartAreaGuideWarmTimers.delete(o);smartAreaGuideWarmQueue=smartAreaGuideWarmQueue.catch(()=>{}).then(()=>state.objects.includes(o)?getSmartAreaGuide(o):null).catch(()=>{})},80);smartAreaGuideWarmTimers.set(o,timer)}
function removeColorInClosedArea(o,wx,wy,t,guide=null){
 const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height,total=w*h;if(lx<0||ly<0||lx>=w||ly>=h)return 0;
 const image=cc.getImageData(0,0,w,h),data=image.data,start=ly*w+lx,startOffset=start*4;if(data[startOffset+3]<8)return 0;
 const usableGuide=guide&&guide.width===w&&guide.height===h&&guide.alpha&&guide.alpha.length===total?guide:null,target={r:data[startOffset],g:data[startOffset+1],b:data[startOffset+2]},edgeThreshold=clamp(155-(Number(t)||35)*1.2,35,135),colorThreshold=clamp(8+(Number(t)||35)*2.2,8,228),region=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0,removed=0;queue[tail++]=start;region[start]=1;
 const guideSide=index=>usableGuide&&usableGuide.alpha[index]>=72;
 const isBoundary=(from,to)=>{const a=from*4,b=to*4;if(data[b+3]<8)return true;if(usableGuide&&guideSide(from)!==guideSide(to))return true;return Math.hypot(data[a]-data[b],data[a+1]-data[b+1],data[a+2]-data[b+2])>=edgeThreshold};
 while(head<tail){const index=queue[head++],x=index%w,y=Math.floor(index/w),neighbors=[x?index-1:-1,x<w-1?index+1:-1,y?index-w:-1,y<h-1?index+w:-1];for(const next of neighbors){if(next<0||region[next]||isBoundary(index,next))continue;region[next]=1;queue[tail++]=next}}
 for(let index=0;index<total;index++){if(!region[index])continue;const offset=index*4;if(data[offset+3]&&Math.hypot(data[offset]-target.r,data[offset+1]-target.g,data[offset+2]-target.b)<=colorThreshold){data[offset+3]=0;removed++}}
 if(removed)cc.putImageData(image,0,0);return removed;
}
function smartAreaStrokeLocalPoint(stroke,point){const o=stroke.object,w=o.canvas.width,h=o.canvas.height,lx=Math.floor((point.x-o.x)/o.w*w),ly=Math.floor((point.y-o.y)/o.h*h);return lx>=0&&ly>=0&&lx<w&&ly<h?{x:lx,y:ly,index:ly*w+lx}:null}
function queueSmartAreaStrokePoint(stroke,point){
 if(!stroke||stroke.cancelled)return;const previous=stroke.lastPoint||point,dx=point.x-previous.x,dy=point.y-previous.y,distance=Math.hypot(dx,dy),step=Math.max(.6,3/Math.max(.1,state.zoom)),samples=Math.max(1,Math.min(240,Math.ceil(distance/step)));
 for(let sample=1;sample<=samples;sample++){const ratio=sample/samples,candidate={x:previous.x+dx*ratio,y:previous.y+dy*ratio};if(smartAreaStrokeLocalPoint(stroke,candidate)){if(stroke.points.length-stroke.pointHead<1600)stroke.points.push(candidate);else stroke.points[stroke.points.length-1]=candidate}}
 stroke.lastPoint={x:point.x,y:point.y};if(stroke.ready)scheduleSmartAreaStroke(stroke);
}
function smartAreaStrokeRegion(stroke,start){
 let regionId=stroke.regionLabels[start];if(regionId>0)return stroke.regions.get(regionId);const data=stroke.image.data,total=stroke.width*stroke.height,queue=new Int32Array(total),startOffset=start*4,startX=start%stroke.width,startY=Math.floor(start/stroke.width),seed=[data[startOffset],data[startOffset+1],data[startOffset+2]],seedProtected=!!(stroke.guideProtection&&stroke.guideProtection[start]),maxReachSq=stroke.maxRegionRadius*stroke.maxRegionRadius;let head=0,tail=0;regionId=stroke.nextRegionId++;stroke.regionLabels[start]=regionId;queue[tail++]=start;
 const guide=stroke.guide,guideSide=index=>guide&&guide.alpha[index]>=72,isBoundary=(from,to)=>{const a=from*4,b=to*4,toX=to%stroke.width,toY=Math.floor(to/stroke.width);if(stroke.originalAlpha[to]<8||data[b+3]<8)return true;if((toX-startX)*(toX-startX)+(toY-startY)*(toY-startY)>maxReachSq)return true;if(stroke.guideProtection&&seedProtected!==!!stroke.guideProtection[to])return true;if(guide&&!stroke.guideProtection&&guideSide(from)!==guideSide(to))return true;const localDifference=Math.hypot(data[a]-data[b],data[a+1]-data[b+1],data[a+2]-data[b+2]),seedDifference=Math.hypot(data[b]-seed[0],data[b+1]-seed[1],data[b+2]-seed[2]);return localDifference>=stroke.edgeThreshold||seedDifference>=stroke.seedColorThreshold};
 while(head<tail){const index=queue[head++],x=index%stroke.width,y=Math.floor(index/stroke.width),neighbors=[x?index-1:-1,x<stroke.width-1?index+1:-1,y?index-stroke.width:-1,y<stroke.height-1?index+stroke.width:-1];for(const next of neighbors){if(next<0||stroke.regionLabels[next]||isBoundary(index,next))continue;stroke.regionLabels[next]=regionId;queue[tail++]=next}}
const region={id:regionId,pixels:queue.slice(0,tail),removed:false};stroke.regions.set(regionId,region);return region;
}
const SMART_AREA_CURSOR_RADIUS=8;
function smartAreaCursorRadius(){return SMART_AREA_CURSOR_RADIUS/Math.max(.1,state.zoom)}
function smartAreaStrokeSamples(stroke,point){const center=smartAreaStrokeLocalPoint(stroke,point);if(!center)return[];const radius=stroke.radiusWorkspace||smartAreaCursorRadius(),rx=Math.max(1,radius/stroke.object.w*stroke.width),ry=Math.max(1,radius/stroke.object.h*stroke.height),samples=[],centerProtected=!!(stroke.guideProtection&&stroke.guideProtection[center.index]),minX=Math.max(0,Math.floor(center.x-rx)),maxX=Math.min(stroke.width-1,Math.ceil(center.x+rx)),minY=Math.max(0,Math.floor(center.y-ry)),maxY=Math.min(stroke.height-1,Math.ceil(center.y+ry));for(let ly=minY;ly<=maxY;ly++)for(let lx=minX;lx<=maxX;lx++){const dx=(lx-center.x)/rx,dy=(ly-center.y)/ry;if(dx*dx+dy*dy>1)continue;const index=ly*stroke.width+lx;if(stroke.covered[index]||stroke.guideProtection&&centerProtected!==!!stroke.guideProtection[index])continue;stroke.covered[index]=1;samples.push({x:lx,y:ly,index})}return samples}
function removeSmartAreaStrokeSample(stroke,local){const data=stroke.image.data,startOffset=local.index*4;if(data[startOffset+3]<8||stroke.originalAlpha[local.index]<8)return 0;const region=smartAreaStrokeRegion(stroke,local.index);if(region.removed)return 0;region.removed=true;let removed=0;for(const index of region.pixels){const offset=index*4;if(data[offset+3]){data[offset+3]=0;removed++}}return removed}
function removeSmartAreaStrokePoint(stroke,point){return smartAreaStrokeSamples(stroke,point).reduce((total,sample)=>total+removeSmartAreaStrokeSample(stroke,sample),0)}
function completeSmartAreaStroke(stroke){
 if(!stroke||stroke!==smartAreaStroke)return;if(stroke.frame){cancelAnimationFrame(stroke.frame);stroke.frame=0}if(stroke.changed)stroke.context.putImageData(stroke.image,0,0);render();finish(stroke.changed?'Arraste concluído':'Nenhuma cor removida');setStatus(stroke.changed?(stroke.usedAi?'Arraste inteligente concluído com proteção de contorno: ':'Arraste por área concluído: ')+stroke.changed+' pixels removidos somente nas regiões próximas e delimitadas.':'Nenhuma área segura foi encontrada no trecho percorrido.');if(stroke.changed)rememberSmartAreaFeedback(stroke);smartAreaStroke=null;smartAreaRemovalBusy=false;
}
function drainSmartAreaStroke(stroke){
 if(!stroke||stroke!==smartAreaStroke||stroke.cancelled||!stroke.ready)return;stroke.frame=0;let processed=0,changed=0;while(stroke.pointHead<stroke.points.length&&processed<3){changed+=removeSmartAreaStrokePoint(stroke,stroke.points[stroke.pointHead++]);processed++}if(changed){stroke.changed+=changed;stroke.context.putImageData(stroke.image,0,0);render()}if(stroke.pointHead<stroke.points.length)scheduleSmartAreaStroke(stroke);else{stroke.points=[];stroke.pointHead=0;if(stroke.ended)completeSmartAreaStroke(stroke)}
}
function scheduleSmartAreaStroke(stroke){if(!stroke||stroke!==smartAreaStroke||stroke.cancelled||stroke.frame)return;stroke.frame=requestAnimationFrame(()=>drainSmartAreaStroke(stroke))}
function endSmartAreaStroke(pointerId){const stroke=smartAreaStroke;if(!stroke||pointerId!=null&&stroke.pointerId!==pointerId)return;stroke.ended=true;if(stroke.ready)scheduleSmartAreaStroke(stroke)}
function cancelSmartAreaStroke(){const stroke=smartAreaStroke;if(!stroke)return;stroke.cancelled=true;if(stroke.frame)cancelAnimationFrame(stroke.frame);smartAreaStroke=null;smartAreaRemovalBusy=false;finish(stroke.changed?'Arraste interrompido':'Processamento cancelado');if(stroke.changed)render()}
function beginSmartAreaStroke(object,point,tolerance,pointerId){
if(smartAreaRemovalBusy){setStatus('A IA ainda está preparando o arraste atual.');return false}settleSmartAreaFeedback(true);const token=++smartAreaRemovalToken,stroke={token,object,source:object.canvas,tolerance,pointerId,radiusWorkspace:smartAreaCursorRadius(),points:[],pointHead:0,lastPoint:null,ended:false,cancelled:false,ready:false,usedAi:true,changed:0,frame:0};smartAreaStroke=stroke;smartAreaRemovalBusy=true;queueSmartAreaStrokePoint(stroke,point);progress(6,'IA local identificando e reforçando os contornos…');
 (async()=>{let guide=null;try{guide=await getSmartAreaGuide(object,(amount,message)=>progress(amount,message))}catch(error){stroke.usedAi=false;log('A IA de limites não ficou disponível; usando as bordas locais.',error&&error.message?error.message:error)}if(stroke.cancelled||stroke!==smartAreaStroke||token!==smartAreaRemovalToken||state.tool!=='areaRemove'||!state.objects.includes(object)||object.canvas!==stroke.source){if(stroke===smartAreaStroke)cancelSmartAreaStroke();return}const context=object.canvas.getContext('2d',{willReadFrequently:true}),image=context.getImageData(0,0,object.canvas.width,object.canvas.height),originalAlpha=new Uint8Array(object.canvas.width*object.canvas.height),width=object.canvas.width,height=object.canvas.height,effectiveSensitivity=clamp((Number(tolerance)||35)+smartAreaLearning.boundaryBias,1,100);for(let index=0,pixel=3;index<originalAlpha.length;index++,pixel+=4)originalAlpha[index]=image.data[pixel];const guideProtection=smartAreaProtectionMask(guide,width,height,effectiveSensitivity),initial=smartAreaStrokeLocalPoint(stroke,stroke.points[0]||stroke.lastPoint||point),protectForeground=!!(guideProtection&&initial&&!guideProtection[initial.index]),rawRadiusX=Math.max(1,stroke.radiusWorkspace/object.w*width),rawRadiusY=Math.max(1,stroke.radiusWorkspace/object.h*height),reachFactor=clamp(8-effectiveSensitivity/25,4,7.5),maxRegionRadius=clamp(Math.max(rawRadiusX,rawRadiusY)*reachFactor,18,Math.min(260,Math.max(width,height)*.32));Object.assign(stroke,{guide,guideProtection,protectForeground,context,image,originalAlpha,covered:new Uint8Array(originalAlpha.length),width,height,effectiveSensitivity,edgeThreshold:clamp(150-effectiveSensitivity*1.12,32,132),seedColorThreshold:clamp(255-effectiveSensitivity*1.35,75,235),maxRegionRadius,regionLabels:new Int32Array(width*height),regions:new Map(),nextRegionId:1,ready:true});pushHistory('Antes do arraste de remoção por área');progress(92,stroke.ended?'Contornos protegidos. Aplicando somente a área próxima…':'Contornos protegidos. Continue arrastando dentro do fundo externo…');scheduleSmartAreaStroke(stroke)})();return true;
}
if(ui.removeMode)ui.removeMode.addEventListener('change',()=>setStatus('Remover fundo'));
ui.tol.addEventListener('input',()=>{ui.tolValue.textContent=ui.tol.value});ui.tolMinus.addEventListener('click',()=>{ui.tol.value=Math.max(0,parseInt(ui.tol.value)-1);ui.tol.dispatchEvent(new Event('input'))});ui.tolPlus.addEventListener('click',()=>{ui.tol.value=Math.min(100,parseInt(ui.tol.value)+1);ui.tol.dispatchEvent(new Event('input'))});ui.edge.addEventListener('input',()=>ui.edgeValue.textContent=ui.edge.value)
let pickedColor={r:255,g:255,b:255};ui.pickPreview.addEventListener('input',()=>{const h=ui.pickPreview.value;pickedColor={r:parseInt(h.slice(1,3),16),g:parseInt(h.slice(3,5),16),b:parseInt(h.slice(5,7),16)}})
ui.pickColor.addEventListener('click',()=>{if(selected()){state.tool='pick';setStatus('Clique na cor do objeto para selecionar');}else setStatus('Selecione um objeto primeiro')});
ui.dehalo.addEventListener('click',()=>{const o=selected();if(!o){setStatus('Selecione um objeto primeiro');return}pushHistory('Antes de limpar halos');const c=o.canvas,cc=c.getContext('2d'),d=cc.getImageData(0,0,c.width,c.height),a=d.data,w=c.width,h=c.height;let n=0;for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;if(!a[i+3])continue;let near=false;for(let oy=-2;oy<=2&&!near;oy++)for(let ox=-2;ox<=2;ox++){const nx=x+ox,ny=y+oy;if(nx<0||ny<0||nx>=w||ny>=h){near=true;break}if(a[(ny*w+nx)*4+3]===0){near=true;break}}if(near&&Math.max(a[i],a[i+1],a[i+2])>205&&Math.min(a[i],a[i+1],a[i+2])>185&&(Math.max(a[i],a[i+1],a[i+2])-Math.min(a[i],a[i+1],a[i+2]))<45){a[i+3]=Math.round(a[i+3]*.18);n++}}cc.putImageData(d,0,0);render();setStatus('Halos limpos: '+n+' pixels')});
ui.reset.addEventListener('click',async()=>{const items=selectedObjects();if(!items.length){setStatus('Selecione uma imagem primeiro');return}pushHistory('Antes de remover fundo');const t=Number.isFinite(Number(ui.tol.value))?Number(ui.tol.value):20;progress(8,'Restaurando a imagem original para testar a nova tolerância...');await nextPaint();let changed=0,total=0;for(let i=0;i<items.length;i++){const o=items[i];o.canvas=canvasFromData(cloneCanvasData(o.baseCanvas));total+=o.canvas.width*o.canvas.height;progress(14+Math.round(i/items.length*12),'Aplicando a tolerância '+t+' em '+(i+1)+' de '+items.length+'...');await nextPaint();const start=30+Math.round(i/items.length*58),span=58/items.length;changed+=await smartBackgroundRemoveAsync(o,t,value=>progress(start+Math.round(value/100*span),'Processando remoção em segundo plano '+(i+1)+' de '+items.length+'...'));progress(30+Math.round((i+1)/items.length*58),'Removendo fundo '+(i+1)+' de '+items.length+'...');await nextPaint()}render();finish('Remoção de fundo concluída');const weak=changed<Math.max(24,total*.001);setStatus(weak?'Pouco fundo foi identificado. Ajuste a tolerância e clique novamente: a imagem será restaurada antes da nova tentativa.':'Fundo removido em '+items.length+' objeto'+(items.length===1?'':'s')+' · tolerância '+t+' aplicada a partir da imagem original.');showRemovalGuide(weak&&ui.magicFill?ui.magicFill:ui.reset,weak?'Para imagens muito complexas, use Montagem inteligente (IA).':'Cada clique restaura a imagem original antes de aplicar a tolerância escolhida.',weak?'complex':'simple',6500)});ui.original.addEventListener('click',()=>{const items=selectedObjects();if(!items.length){setStatus('Selecione um objeto primeiro');return}pushHistory('Antes de restaurar original');items.forEach(o=>o.canvas=canvasFromData(cloneCanvasData(o.baseCanvas)));render();setStatus('Objetos restaurados: '+items.length)});

// Selection / move / resize.
let resizeState=null;
 selection.querySelectorAll('.dtf-handle').forEach(h=>h.addEventListener('pointerdown',e=>{const o=selected();if(!o)return;e.preventDefault();e.stopPropagation();const p=clientToWorkspace(e);resizeState={handle:h.dataset.handle,startX:p.x,startY:p.y,ox:o.x,oy:o.y,ow:o.w,oh:o.h,ratio:o.w/o.h,lock:['nw','ne','se','sw'].includes(h.dataset.handle)};try{h.setPointerCapture(e.pointerId)}catch(_){} }));
selection.addEventListener('pointermove',e=>{if(!resizeState)return;const o=selected(),p=clientToWorkspace(e);if(!o||!p)return;e.preventDefault();const dx=p.x-resizeState.startX,dy=p.y-resizeState.startY;let x=resizeState.ox,y=resizeState.oy,w=resizeState.ow,h=resizeState.oh;const hs=resizeState.handle;if(hs.includes('e'))w=Math.max(8,resizeState.ow+dx);if(hs.includes('s'))h=Math.max(8,resizeState.oh+dy);if(hs.includes('w')){w=Math.max(8,resizeState.ow-dx);x=resizeState.ox+(resizeState.ow-w)}if(hs.includes('n')){h=Math.max(8,resizeState.oh-dy);y=resizeState.oy+(resizeState.oh-h)}if(resizeState.lock){if(hs==='e'||hs==='w')h=w/resizeState.ratio;else if(hs==='n'||hs==='s')w=h*resizeState.ratio;else{const byW=w/resizeState.ratio;const byH=h; if(Math.abs(dx)>Math.abs(dy)){h=byW}else{w=byH*resizeState.ratio}}if(hs.includes('n'))y=resizeState.oy+(resizeState.oh-h);if(hs.includes('w'))x=resizeState.ox+(resizeState.ow-w)}o.x=x;o.y=y;o.w=Math.min(w,canvas.width-Math.max(0,x));o.h=Math.min(h,canvas.height-Math.max(0,y));render();});
selection.addEventListener('pointerup',e=>{if(resizeState){pushHistory('Redimensionar objeto');resizeState=null}});selection.addEventListener('pointercancel',()=>resizeState=null)


// Resize com oito alças da caixa de seleção. O lock mantém proporção.
selection.querySelectorAll('.dtf-handle').forEach(handle=>{
  handle.addEventListener('pointerdown',e=>{
    const o=selected();if(!o)return;
    e.preventDefault();e.stopPropagation();
    const p=clientToWorkspace(e);if(!p)return;
    pushHistory('Antes de redimensionar');
    resizeDrag={handle:handle.dataset.handle,sx:p.x,sy:p.y,ox:o.x,oy:o.y,ow:o.w,oh:o.h,ratio:o.w/o.h,lock:['nw','ne','se','sw'].includes(handle.dataset.handle),pointerId:e.pointerId,items:selectedObjects().map(item=>({o:item,x:item.x,y:item.y,w:item.w,h:item.h}))};
    try{handle.setPointerCapture(e.pointerId)}catch(_){ }
  });
});

document.addEventListener('pointermove',e=>{
  if(!resizeDrag || e.pointerId!==resizeDrag.pointerId)return;
  const o=selected(),p=clientToWorkspace(e);if(!o||!p)return;
  e.preventDefault();
  const dx=p.x-resizeDrag.sx,dy=p.y-resizeDrag.sy;
  let x=resizeDrag.ox,y=resizeDrag.oy,w=resizeDrag.ow,h=resizeDrag.oh;const hs=resizeDrag.handle;
  if(hs.includes('e'))w=Math.max(4,resizeDrag.ow+dx);if(hs.includes('s'))h=Math.max(4,resizeDrag.oh+dy);
  if(hs.includes('w')){w=Math.max(4,resizeDrag.ow-dx);x=resizeDrag.ox+resizeDrag.ow-w}
  if(hs.includes('n')){h=Math.max(4,resizeDrag.oh-dy);y=resizeDrag.oy+resizeDrag.oh-h}
  if(resizeDrag.lock){
    if(hs==='e'||hs==='w')h=w/resizeDrag.ratio;
    else if(hs==='n'||hs==='s')w=h*resizeDrag.ratio;
    else if(Math.abs(dx)>=Math.abs(dy))h=w/resizeDrag.ratio;
    else w=h*resizeDrag.ratio;
    if(hs.includes('n'))y=resizeDrag.oy+resizeDrag.oh-h;
    if(hs.includes('w'))x=resizeDrag.ox+resizeDrag.ow-w;
  }
  w=Math.min(w,canvas.width);h=Math.min(h,canvas.height);
  if(hs.includes('e'))w=Math.min(w,canvas.width-resizeDrag.ox);if(hs.includes('s'))h=Math.min(h,canvas.height-resizeDrag.oy);if(hs.includes('w')){w=Math.min(w,resizeDrag.ox+resizeDrag.ow);x=resizeDrag.ox+resizeDrag.ow-w}if(hs.includes('n')){h=Math.min(h,resizeDrag.oy+resizeDrag.oh);y=resizeDrag.oy+resizeDrag.oh-h}
  if(resizeDrag.lock&&hs.length===2){let scale=Math.min(w/resizeDrag.ow,h/resizeDrag.oh);const maxScale=Math.min(hs.includes('e')?(canvas.width-resizeDrag.ox)/resizeDrag.ow:Infinity,hs.includes('s')?(canvas.height-resizeDrag.oy)/resizeDrag.oh:Infinity,hs.includes('w')?(resizeDrag.ox+resizeDrag.ow)/resizeDrag.ow:Infinity,hs.includes('n')?(resizeDrag.oy+resizeDrag.oh)/resizeDrag.oh:Infinity);scale=Math.max(.01,Math.min(scale,maxScale));w=resizeDrag.ow*scale;h=resizeDrag.oh*scale;if(hs.includes('w'))x=resizeDrag.ox+resizeDrag.ow-w;if(hs.includes('n'))y=resizeDrag.oy+resizeDrag.oh-h}
  x=clamp(x,0,Math.max(0,canvas.width-w));y=clamp(y,0,Math.max(0,canvas.height-h));
  o.x=x;o.y=y;o.w=w;o.h=h;const sx=w/resizeDrag.ow,sy=h/resizeDrag.oh;resizeDrag.items.filter(item=>item.o!==o).forEach(item=>{item.o.w=Math.max(4,Math.min(canvas.width,item.w*sx));item.o.h=Math.max(4,Math.min(canvas.height,item.h*sy));item.o.x=clamp(o.x+(item.x-resizeDrag.ox)*sx,0,canvas.width-item.o.w);item.o.y=clamp(o.y+(item.y-resizeDrag.oy)*sy,0,canvas.height-item.o.h)});render();
});

document.addEventListener('pointerup',e=>{if(resizeDrag&&e.pointerId===resizeDrag.pointerId){resizeDrag=null;setStatus('Objeto redimensionado')}});
document.addEventListener('pointercancel',e=>{if(resizeDrag&&e.pointerId===resizeDrag.pointerId)resizeDrag=null});

function fillClosedRegion(o,wx,wy,color,tol){const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data;if(lx<0||ly<0||lx>=w||ly>=h)return 0;const start=(ly*w+lx),sj=start*4,target=[a[sj],a[sj+1],a[sj+2]],th=Math.max(2,tol/100*441),seen=new Uint8Array(w*h),q=[start];seen[start]=1;const rgb=color.match(/[0-9a-f]{2}/gi).map(v=>parseInt(v,16));let n=0;while(q.length){const i=q.pop(),j=i*4;if(!a[j+3]||Math.hypot(a[j]-target[0],a[j+1]-target[1],a[j+2]-target[2])>th)continue;a[j]=rgb[0];a[j+1]=rgb[1];a[j+2]=rgb[2];n++;const x=i%w,y=Math.floor(i/w);for(const ni of [x?i-1:-1,x<w-1?i+1:-1,y?i-w:-1,y<h-1?i+w:-1])if(ni>=0&&!seen[ni]){seen[ni]=1;q.push(ni)}}cc.putImageData(d,0,0);return n}
// Flood fill que também reconhece regiões transparentes fechadas.
const legacyFillClosedRegion=fillClosedRegion;
fillClosedRegion=function(o,wx,wy,color,tol){const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data;if(lx<0||ly<0||lx>=w||ly>=h)return 0;const start=ly*w+lx,sj=start*4,targetA=a[sj+3],target=[a[sj],a[sj+1],a[sj+2]],th=Math.max(2,(Number(tol)||20)/100*441),alphaTh=targetA<16?32:8,seen=new Uint8Array(w*h),q=[start],rgb=(String(color||'#8c3f00').match(/[0-9a-f]{2}/gi)||['8c','3f','00']).map(v=>parseInt(v,16));const similar=i=>{const j=i*4;if(Math.abs(a[j+3]-targetA)>alphaTh)return false;return Math.hypot(a[j]-target[0],a[j+1]-target[1],a[j+2]-target[2])<=th};seen[start]=1;let n=0;while(q.length){const i=q.pop();if(!similar(i))continue;const j=i*4;a[j]=rgb[0];a[j+1]=rgb[1];a[j+2]=rgb[2];a[j+3]=255;n++;const x=i%w,y=Math.floor(i/w);for(const ni of [x?i-1:-1,x<w-1?i+1:-1,y?i-w:-1,y<h-1?i+w:-1])if(ni>=0&&!seen[ni]){seen[ni]=1;q.push(ni)}}cc.putImageData(d,0,0);return n};
let pointerSpace=false,panDrag=null;
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.dtf-user-guide')||state.tool!=='fill')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit)return; e.preventDefault();e.stopImmediatePropagation();pushHistory('Antes de preencher região');const n=fillClosedRegion(hit,p.x,p.y,fillColor?fillColor.value:'#8c3f00',Number(ui.tol.value)||20);render();setStatus('Região preenchida: '+n+' pixels')},{capture:true});
if(fillTool)fillTool.addEventListener('click',()=>{if(state.tool==='fill'){deactivateSpecialTool();return}if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}deactivateSpecialTool(true);deactivateBrush();state.tool='fill';fillTool.classList.add('active');fillTool.setAttribute('aria-pressed','true');editorRoot.classList.add('dtf-color-remove-cursor');setStatus('Clique em uma área fechada para preencher')});
if(fillColor)fillColor.addEventListener('input',()=>{if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}deactivateSpecialTool(true);deactivateBrush();state.tool='fill';fillTool.classList.add('active');fillTool.setAttribute('aria-pressed','true');editorRoot.classList.add('dtf-color-remove-cursor');setStatus('Cor escolhida — clique em uma área fechada para preencher')});
function isRemovalTool(tool=state.tool){return tool==='colorRemove'||tool==='areaRemove'||tool==='globalColorRemove'}
function deactivateSpecialTool(quiet=false){if(!isRemovalTool()&&state.tool!=='fill')return;if(state.tool==='areaRemove'){smartAreaRemovalToken++;cancelSmartAreaStroke()}state.tool='select';[clickRemove,areaRemoveButton,globalColorRemoveButton,fillTool].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});editorRoot.classList.remove('dtf-color-remove-cursor','dtf-area-remove-cursor');eraserCursor.classList.remove('show','dtf-area-cursor');if(!quiet)setStatus('Ferramenta desativada')}
document.addEventListener('pointerdown',e=>{if(!isRemovalTool()&&state.tool!=='fill')return;if(e.target.closest('#dtfClickRemove,#dtfAreaRemove,#dtfColorRemove,#dtfFillTool'))return;const inside=e.target.closest('#dtfViewport');if(inside){const p=clientToWorkspace(e);if(p&&hitTest(p.x,p.y))return}deactivateSpecialTool()},{capture:true});
viewport.addEventListener('pointermove',e=>{if(state.tool!=='select')return;const p=clientToWorkspace(e);viewport.classList.toggle('dtf-can-drag',!!(p&&hitTest(p.x,p.y)))},{capture:true});
viewport.addEventListener('pointerdown',e=>{if(state.tool==='select'){const p=clientToWorkspace(e);if(p&&hitTest(p.x,p.y))viewport.classList.add('dtf-dragging')}},{capture:true});
viewport.addEventListener('pointerup',()=>viewport.classList.remove('dtf-dragging'),{capture:true});viewport.addEventListener('pointercancel',()=>viewport.classList.remove('dtf-dragging'),{capture:true});
viewport.addEventListener('pointerdown',e=>{const p=clientToWorkspace(e);if(p&&hitTest(p.x,p.y)&&state.tool==='select')selection.classList.add('dtf-measuring')},{capture:true});
viewport.addEventListener('pointerup',()=>selection.classList.remove('dtf-measuring'),{capture:true});viewport.addEventListener('pointercancel',()=>selection.classList.remove('dtf-measuring'),{capture:true});
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.dtf-user-guide')||!isRemovalTool())return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit){deactivateSpecialTool();return}e.preventDefault();e.stopImmediatePropagation();const tolerance=Number(ui.clickTol&&ui.clickTol.value)||35;
 if(state.tool==='colorRemove'){pushHistory('Antes de remover por clique');const n=contiguousRemove(hit,p.x,p.y,tolerance);render();setStatus('Região conectada removida: '+n+' pixels');return}
 if(state.tool==='areaRemove'){if(beginSmartAreaStroke(hit,p,tolerance,e.pointerId)){try{viewport.setPointerCapture(e.pointerId)}catch(_){}}return}
 const lx=Math.floor((p.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((p.y-hit.y)/hit.h*hit.canvas.height),pixel=hit.canvas.getContext('2d',{willReadFrequently:true}).getImageData(lx,ly,1,1).data,targets=state.selectedIds.includes(hit.id)?selectedObjects():[hit];pushHistory('Antes de remover por cor global');const n=targets.reduce((sum,object)=>sum+selectedColorRemove(object,{r:pixel[0],g:pixel[1],b:pixel[2]},tolerance),0);render();setStatus('Cor removida globalmente, dentro e fora das áreas: '+n+' pixels');
},{capture:true});
viewport.addEventListener('pointermove',e=>{if(state.tool==='areaRemove')updateEraserCursor(e);if(!(e.buttons&1))return;if(state.tool==='areaRemove'&&smartAreaStroke&&smartAreaStroke.pointerId===e.pointerId){const p=clientToWorkspace(e);if(!p)return;e.preventDefault();e.stopImmediatePropagation();queueSmartAreaStrokePoint(smartAreaStroke,p);return}if(state.tool!=='colorRemove')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit)return;e.preventDefault();e.stopImmediatePropagation();contiguousRemove(hit,p.x,p.y,Number(ui.clickTol&&ui.clickTol.value)||35);render()},{capture:true});
viewport.addEventListener('pointerup',e=>endSmartAreaStroke(e.pointerId),{capture:true});viewport.addEventListener('pointercancel',e=>endSmartAreaStroke(e.pointerId),{capture:true});document.addEventListener('pointerup',e=>endSmartAreaStroke(e.pointerId));document.addEventListener('pointercancel',e=>endSmartAreaStroke(e.pointerId));
viewport.addEventListener('pointerdown',e=>{if(state.space){panDrag={sx:e.clientX,sy:e.clientY,px:state.panX,py:state.panY};try{viewport.setPointerCapture(e.pointerId)}catch(_){}return}
 if(state.tool==='eraser'||state.tool==='restore'){startBrush(e);return}
 const p=clientToWorkspace(e);if(!p)return;const hit=hitTest(p.x,p.y);state.lastClick=p;if(isRemovalTool())return;if(state.tool==='pick'){if(hit){const lx=Math.floor((p.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((p.y-hit.y)/hit.h*hit.canvas.height),d=hit.canvas.getContext('2d').getImageData(lx,ly,1,1).data;pickedColor={r:d[0],g:d[1],b:d[2]};ui.pickPreview.value='#'+[d[0],d[1],d[2]].map(v=>v.toString(16).padStart(2,'0')).join('');state.tool='select';setStatus('Cor selecionada: '+ui.pickPreview.value)}return}
 if(hit){const keepGroup=!e.ctrlKey&&state.selectedIds.includes(hit.id);if(!keepGroup)selectObject(hit,e.ctrlKey);const moving=selectedObjects();state.drag={o:hit,sx:p.x,sy:p.y,items:moving.map(o=>({o,ox:o.x,oy:o.y})),moved:false,historySaved:false}}
 else {selectObject(null);state.marquee={sx:p.x,sy:p.y,ex:p.x,ey:p.y};if(marquee){marquee.style.left=p.x+'px';marquee.style.top=p.y+'px';marquee.style.width='0px';marquee.style.height='0px';marquee.classList.add('show')}}
});
viewport.addEventListener('contextmenu',e=>{e.preventDefault();const p=clientToWorkspace(e);const hit=p&&hitTest(p.x,p.y);if(hit&&!state.selectedIds.includes(hit.id))selectObject(hit,false);renderIntelligentContext(hit||null);const menu=$id('dtfCanvasMenu');if(menu){updateGroupMenu();menu.style.left=e.clientX+'px';menu.style.top=e.clientY+'px';menu.classList.add('show');menu.focus()} });
document.addEventListener('pointerdown',e=>{const menu=$id('dtfCanvasMenu');if(menu&&!e.target.closest('#dtfCanvasMenu'))menu.classList.remove('show')});
function snapToNearestObject(anchor,dx,dy){const threshold=Math.max(6,physicalMmToPx(2)),movingX=anchor.ox+dx,movingY=anchor.oy+dy,xs=[movingX,movingX+anchor.o.w/2,movingX+anchor.o.w],ys=[movingY,movingY+anchor.o.h/2,movingY+anchor.o.h];let bestX=null,bestY=null;const targets=state.drag.snapTargets||(state.drag.snapTargets=state.objects.filter(o=>o.visible&&o.id!==anchor.o.id&&!state.drag.items.some(item=>item.o.id===o.id)));targets.forEach(t=>{const tx=[t.x,t.x+t.w/2,t.x+t.w],ty=[t.y,t.y+t.h/2,t.y+t.h];xs.forEach((v,i)=>tx.forEach(target=>{const d=target-v;if(Math.abs(d)<=threshold&&(!bestX||Math.abs(d)<Math.abs(bestX.d)))bestX={d,edge:i}}));ys.forEach((v,i)=>ty.forEach(target=>{const d=target-v;if(Math.abs(d)<=threshold&&(!bestY||Math.abs(d)<Math.abs(bestY.d)))bestY={d,edge:i}}))});return {dx:bestX?dx+bestX.d:dx,dy:bestY?dy+bestY.d:dy}}
function snapToGuides(anchor,dx,dy){const threshold=Math.max(6,physicalMmToPx(2)),inset=Math.min(canvas.width,canvas.height,physicalMmToPx(5)),movingX=anchor.ox+dx,movingY=anchor.oy+dy,xs=[movingX,movingX+anchor.o.w/2,movingX+anchor.o.w],ys=[movingY,movingY+anchor.o.h/2,movingY+anchor.o.h],targetsX=state.customGuides.filter(guide=>guide.orientation==='v').map(guideCanvasPosition),targetsY=state.customGuides.filter(guide=>guide.orientation==='h').map(guideCanvasPosition);if(guidesEnabled){targetsX.push(inset,canvas.width/2,canvas.width-inset,0,canvas.width);targetsY.push(inset,canvas.height/2,canvas.height-inset,0,canvas.height)}let bestX=null,bestY=null;xs.forEach((value,index)=>targetsX.forEach(target=>{const d=target-value;if(Math.abs(d)<=threshold&&(!bestX||Math.abs(d)<Math.abs(bestX.d)))bestX={d,index}}));ys.forEach((value,index)=>targetsY.forEach(target=>{const d=target-value;if(Math.abs(d)<=threshold&&(!bestY||Math.abs(d)<Math.abs(bestY.d)))bestY={d,index}}));return {dx:bestX?dx+bestX.d:dx,dy:bestY?dy+bestY.d:dy}}
let dragRenderQueued=false;function scheduleDragRender(){if(dragRenderQueued)return;dragRenderQueued=true;requestAnimationFrame(()=>{dragRenderQueued=false;if(state.drag)render()})}
viewport.addEventListener('pointermove',e=>{if(panDrag){state.panX=panDrag.px+(e.clientX-panDrag.sx);state.panY=panDrag.py+(e.clientY-panDrag.sy);applyZoom();return}if(state.marquee){const p=clientToWorkspace(e);if(!p)return;state.marquee.ex=p.x;state.marquee.ey=p.y;const x=Math.min(state.marquee.sx,p.x),y=Math.min(state.marquee.sy,p.y),w=Math.abs(p.x-state.marquee.sx),h=Math.abs(p.y-state.marquee.sy);marquee.style.left=x+'px';marquee.style.top=y+'px';marquee.style.width=w+'px';marquee.style.height=h+'px';return}if(state.drag){const p=clientToWorkspace(e);if(!p)return;let dx=p.x-state.drag.sx,dy=p.y-state.drag.sy;const minDx=Math.max(...state.drag.items.map(({o,ox})=>-ox)),maxDx=Math.min(...state.drag.items.map(({o,ox})=>canvas.width-o.w-ox));const minDy=Math.max(...state.drag.items.map(({o,oy})=>-oy)),maxDy=Math.min(...state.drag.items.map(({o,oy})=>canvas.height-o.h-oy));dx=clamp(dx,minDx,maxDx);dy=clamp(dy,minDy,maxDy);const anchor=state.drag.items[0];if(gridEnabled){dx=snapGrid(anchor.ox+dx)-anchor.ox;dy=snapGrid(anchor.oy+dy)-anchor.oy}if(objectSnapEnabled){const snapped=snapToNearestObject(anchor,dx,dy);dx=snapped.dx;dy=snapped.dy}if(guidesEnabled||state.customGuides.length){const snapped=snapToGuides(anchor,dx,dy);dx=snapped.dx;dy=snapped.dy}dx=clamp(dx,minDx,maxDx);dy=clamp(dy,minDy,maxDy);if(Math.abs(dx)<.001&&Math.abs(dy)<.001)return;if(!state.drag.historySaved){pushHistory('Antes de mover objeto');state.drag.historySaved=true}state.drag.items.forEach(({o,ox,oy})=>{o.x=ox+dx;o.y=oy+dy});state.drag.moved=true;scheduleDragRender();return}updateEraserCursor(e)});
viewport.addEventListener('pointerup',e=>{if(panDrag){panDrag=null;return}if(state.marquee){const m=state.marquee;const x1=Math.min(m.sx,m.ex),y1=Math.min(m.sy,m.ey),x2=Math.max(m.sx,m.ex),y2=Math.max(m.sy,m.ey);const picked=state.objects.filter(o=>o.visible&&o.x<x2&&o.x+o.w>x1&&o.y<y2&&o.y+o.h>y1);state.selectedIds=picked.map(o=>o.id);state.selectedId=picked.length?picked[picked.length-1].id:null;state.marquee=null;if(marquee)marquee.classList.remove('show');updateSelection();updateObjectUI();setStatus(picked.length+' objetos selecionados');return}if(state.drag){const moved=state.drag.moved;state.drag=null;if(moved){render();updateObjectUI();setStatus('Objeto movido')}}finishBrush(e)});viewport.addEventListener('pointercancel',e=>{panDrag=null;state.drag=null;state.marquee=null;if(marquee)marquee.classList.remove('show');render();finishBrush(e)});
document.addEventListener('pointerup',e=>{if(state.drag){const moved=state.drag.moved;state.drag=null;if(moved){render();setStatus('Objeto movido')}}if(panDrag)panDrag=null;finishBrush(e)});

let intelligentContextObject=null;
const intelligentMenu=document.createElement('div'),intelligentMenuButton=document.createElement('button'),intelligentSubmenu=document.createElement('div');
intelligentMenu.className='dtf-properties-menu dtf-intelligent-menu';intelligentMenuButton.type='button';intelligentMenuButton.textContent='Ver miniaturas inteligentes ▸';intelligentSubmenu.className='dtf-properties-submenu dtf-intelligent-submenu';intelligentMenu.append(intelligentMenuButton,intelligentSubmenu);if(canvasMenu)canvasMenu.appendChild(intelligentMenu);
function intelligentVariantTargets(object){
 if(!object)return[];const ids=new Set([object.id]);if(state.selectedIds.includes(object.id))state.selectedIds.forEach(id=>ids.add(id));if(object.groupId)state.objects.filter(item=>item.groupId===object.groupId).forEach(item=>ids.add(item.id));return state.objects.filter(item=>ids.has(item.id));
}
function applyIntelligentVariant(object,option,optionIndex=0){
 if(!object||!option)return;const changes=[];intelligentVariantTargets(object).forEach(target=>{const set=state.intelligentVariantSets[target.intelligentVariantSetId],targetOption=set&&set.options&&(set.options.find(item=>item.mode===option.mode)||set.options.find(item=>item.label===option.label)||set.options[optionIndex]);if(targetOption)changes.push({target,option:targetOption})});if(!changes.length)return;pushHistory(changes.length>1?'Trocar recorte inteligente em vários objetos':'Trocar recorte inteligente');changes.forEach(({target,option:targetOption})=>{const trim=magicVariantTrim(targetOption),rotated=target.intelligentVariantRotated===true,oldTrimW=Math.max(1,Number(target.aiTrimW)||trim.w),oldTrimH=Math.max(1,Number(target.aiTrimH)||trim.h),scaleX=target.w/(rotated?oldTrimH:oldTrimW),scaleY=target.h/(rotated?oldTrimW:oldTrimH),art=rotated?rotateCanvas90(trim.canvas):trim.canvas;target.canvas=canvasClone(art);target.baseCanvas=canvasClone(art);target.w=Math.max(1,(rotated?trim.h:trim.w)*scaleX);target.h=Math.max(1,(rotated?trim.w:trim.h)*scaleY);target.x=clamp(target.x,0,Math.max(0,canvas.width-target.w));target.y=clamp(target.y,0,Math.max(0,canvas.height-target.h));target.aiTrimW=trim.w;target.aiTrimH=trim.h;target.aiFrameW=target.aiFrameW||targetOption.canvas.width;target.aiFrameH=target.aiFrameH||targetOption.canvas.height;target.originalW=target.canvas.width;target.originalH=target.canvas.height;target.intelligentVariantId=targetOption.id});render();updateSelection();updateObjectUI();setStatus(changes.length+' imagem'+(changes.length===1?' alterada':'ens alteradas')+' para “'+option.label+'”');
}
function renderIntelligentContext(object){
 intelligentContextObject=object||null;const set=object&&state.intelligentVariantSets[object.intelligentVariantSetId],count=set&&set.options?set.options.length:0;intelligentSubmenu.innerHTML='';intelligentMenu.style.display=count?'block':'none';intelligentMenu.classList.toggle('disabled',count===1);intelligentMenuButton.disabled=count===1;intelligentMenuButton.title=count===1?'Somente uma miniatura inteligente disponível':'';if(!count||count===1)return;
 set.options.forEach((option,optionIndex)=>{const button=document.createElement('button'),image=document.createElement('img'),preview=magicVariantTrim(option);button.type='button';button.className='dtf-intelligent-option';button.title=option.label;button.setAttribute('aria-label',(option.id===object.intelligentVariantId?'Selecionado: ':'Usar ')+option.label);if(option.id===object.intelligentVariantId)button.classList.add('selected');image.src=magicPreviewData(preview.canvas,76,object.w,object.h);image.alt='';if(object.intelligentVariantRotated)image.classList.add('rotated');button.appendChild(image);button.onclick=event=>{event.stopPropagation();const live=state.objects.find(item=>item.id===intelligentContextObject?.id);if(live)applyIntelligentVariant(live,option,optionIndex);canvasMenu.classList.remove('show')};intelligentSubmenu.appendChild(button)});
}

function eraserRadiusWorkspace(o){return state.brushSize}
function updateEraserCursor(e){if(!e&& !state.lastPointer)return;let p=null;if(e)p=clientToWorkspace(e);else p=state.lastPointer;if(p)state.lastPointer=p;const areaTool=state.tool==='areaRemove';if(state.tool!=='eraser'&&state.tool!=='restore'&&!areaTool){eraserCursor.classList.remove('show','dtf-area-cursor');return}if(!p)return;const r=areaTool?smartAreaCursorRadius():state.brushSize,diameter=areaTool?r*2:r,offset=areaTool?r:r/2;eraserCursor.style.width=diameter+'px';eraserCursor.style.height=diameter+'px';eraserCursor.style.left=(p.x-offset)+'px';eraserCursor.style.top=(p.y-offset)+'px';eraserCursor.classList.toggle('dtf-area-cursor',areaTool);eraserCursor.classList.add('show')}
let brushStroke=null;
let resizeDrag=null;
function brushAt(o,wx,wy,erase=true){const lx=(wx-o.x)/o.w*o.canvas.width,ly=(wy-o.y)/o.h*o.canvas.height;const rx=state.brushSize/o.w*o.canvas.width/2,ry=state.brushSize/o.h*o.canvas.height/2;const c=o.canvas,cc=c.getContext('2d');if(erase){cc.save();cc.globalCompositeOperation='destination-out';cc.beginPath();cc.ellipse(lx,ly,Math.max(1,rx),Math.max(1,ry),0,0,Math.PI*2);cc.fill();cc.restore()}else{const base=o.baseCanvas.getContext('2d');const sx=Math.max(0,Math.floor(lx-rx)),sy=Math.max(0,Math.floor(ly-ry)),sw=Math.min(c.width-sx,Math.ceil(rx*2)),sh=Math.min(c.height-sy,Math.ceil(ry*2));if(sw>0&&sh>0){const crop=base.getImageData(sx,sy,sw,sh);const out=cc.getImageData(sx,sy,sw,sh);for(let y=0;y<sh;y++)for(let x=0;x<sw;x++){const dx=x+sx-lx,dy=y+sy-ly;if((dx*dx)/(rx*rx)+(dy*dy)/(ry*ry)<=1){const i=(y*sw+x)*4;out.data[i]=crop.data[i];out.data[i+1]=crop.data[i+1];out.data[i+2]=crop.data[i+2];out.data[i+3]=crop.data[i+3]}}cc.putImageData(out,sx,sy)}}}
function brushAtAll(wx,wy,erase=true){for(const o of state.objects)brushAt(o,wx,wy,erase)}
function startBrush(e){const p=clientToWorkspace(e);if(!p)return;if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}pushHistory(state.tool==='eraser'?'Antes da borracha':'Antes do restaurador');brushStroke={last:p};brushAtAll(p.x,p.y,state.tool==='eraser');render();updateEraserCursor(e);setStatus(state.tool==='eraser'?'Borra circular ativa em toda a tela':'Restaurador circular ativo em toda a tela')}
function continueBrush(e){if(!brushStroke)return;const p=clientToWorkspace(e);if(!p)return;const from=brushStroke.last,dx=p.x-from.x,dy=p.y-from.y,dist=Math.hypot(dx,dy),steps=Math.max(1,Math.ceil(dist/(state.brushSize*.28)));for(let i=1;i<=steps;i++){const t=i/steps;brushAtAll(from.x+dx*t,from.y+dy*t,state.tool==='eraser')}brushStroke.last=p;render();updateEraserCursor(e)}
function finishBrush(){if(brushStroke){brushStroke=null;setStatus('Edição da borracha concluída')}}
function deactivateBrush(){state.tool='select';brushStroke=null;state.lastPointer=null;eraserCursor.classList.remove('show','dtf-area-cursor');ui.eraser.classList.remove('active');ui.restoreBrush.classList.remove('active');ui.eraser.setAttribute('aria-pressed','false');ui.restoreBrush.setAttribute('aria-pressed','false')}
viewport.addEventListener('pointermove',e=>{if(brushStroke)continueBrush(e);else updateEraserCursor(e)});
viewport.addEventListener('pointerleave',()=>eraserCursor.classList.remove('show'));

ui.eraser.addEventListener('click',()=>{if(state.tool==='eraser'){deactivateBrush();setStatus('Borra desativada');return}if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}deactivateSpecialTool(true);deactivateBrush();state.tool='eraser';ui.eraser.classList.add('active');ui.eraser.setAttribute('aria-pressed','true');ui.restoreBrush.setAttribute('aria-pressed','false');setStatus('Borra circular ativa')});
ui.restoreBrush.addEventListener('click',()=>{if(state.tool==='restore'){deactivateBrush();setStatus('Restaurador desativado');return}if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}deactivateSpecialTool(true);deactivateBrush();state.tool='restore';ui.restoreBrush.classList.add('active');ui.restoreBrush.setAttribute('aria-pressed','true');ui.eraser.setAttribute('aria-pressed','false');setStatus('Restaurador circular ativo');updateEraserCursor()});
function activateRemovalTool(tool,button,status){
 if(!selectedObjects().length){setStatus('Selecione um objeto primeiro');return false}
 deactivateSpecialTool(true);deactivateBrush();state.tool=tool;[clickRemove,areaRemoveButton,globalColorRemoveButton].forEach(item=>{if(item){const active=item===button;item.classList.toggle('active',active);item.setAttribute('aria-pressed',String(active))}});editorRoot.classList.add('dtf-color-remove-cursor');editorRoot.classList.toggle('dtf-area-remove-cursor',tool==='areaRemove');setStatus(status);return true;
}
function activateClickRemove(status='Clique em uma cor para remover somente a região conectada'){return activateRemovalTool('colorRemove',clickRemove,status)}
if(clickRemove)clickRemove.addEventListener('click',()=>{if(state.tool==='colorRemove')deactivateSpecialTool();else activateClickRemove()});
if(areaRemoveButton)areaRemoveButton.addEventListener('click',()=>{if(state.tool==='areaRemove')deactivateSpecialTool();else if(activateRemovalTool('areaRemove',areaRemoveButton,'Remoção de fundo de área (com IA): arraste o círculo sobre qualquer sobra. A decisão é recalculada em cada trecho sem atravessar os contornos.'))selectedObjects().forEach(scheduleSmartAreaGuidePreparation)});
if(globalColorRemoveButton)globalColorRemoveButton.addEventListener('click',()=>{if(state.tool==='globalColorRemove')deactivateSpecialTool();else activateRemovalTool('globalColorRemove',globalColorRemoveButton,'Remover por cor global: clique em uma cor para removê-la dentro e fora de todas as regiões da imagem.')});
if(ui.clickTol&&ui.clickTolValue)ui.clickTol.addEventListener('input',()=>{ui.clickTolValue.textContent=ui.clickTol.value;if(state.tool==='areaRemove')setStatus('Remoção de fundo de área (com IA) mantida ativa · sensibilidade de borda '+ui.clickTol.value);else if(state.tool==='colorRemove')setStatus('Remoção de fundo de área (sem IA) mantida ativa · sensibilidade de borda '+ui.clickTol.value);else activateClickRemove('Remoção de fundo de área (sem IA) ativada · clique na região desejada.')});
ui.brush.addEventListener('input',()=>{state.brushSize=clamp(parseInt(ui.brush.value,10)||32,ERASER_MIN,ERASER_MAX);ui.brushValue.textContent=String(state.brushSize);});

function copySelected(){const items=selectedObjects();if(!items.length)return;state.clipboard=items.map(o=>({...o,canvas:cloneCanvasData(o.canvas),baseCanvas:cloneCanvasData(o.baseCanvas),aiOriginal:o.aiOriginalCanvas?cloneCanvasData(o.aiOriginalCanvas):null}));updateContextTools();setStatus(items.length+' objeto'+(items.length===1?'':'s')+' copiado'+(items.length===1?'':'s'))}
let pasteOffset=0;function pasteClipboard(){if(!state.clipboard.length)return;pushHistory('Antes de colar objetos');pasteOffset+=Math.max(12,Math.round(state.dpi/25.4*4));const pasted=state.clipboard.map((s,i)=>({...s,id:uid(),name:s.name+' cópia',x:clamp(s.x+pasteOffset,0,Math.max(0,canvas.width-s.w)),y:clamp(s.y+pasteOffset,0,Math.max(0,canvas.height-s.h)),canvas:canvasFromData(s.canvas),baseCanvas:canvasFromData(s.baseCanvas),aiOriginalCanvas:s.aiOriginal?canvasFromData(s.aiOriginal):null}));state.objects.push(...pasted);state.selectedIds=pasted.map(o=>o.id);state.selectedId=pasted[pasted.length-1].id;render();updateObjectUI();setStatus(pasted.length+' objeto'+(pasted.length===1?'':'s')+' colado'+(pasted.length===1?'':'s'))}
function groupSelected(){const items=selectedObjects();if(items.length<2){setStatus('Selecione pelo menos dois objetos');return}if(items.some(o=>o.groupId)){setStatus('Desagrupe os objetos antes de criar um novo grupo');return}pushHistory('Agrupar objetos');const id=uid();items.forEach(o=>o.groupId=id);updateContextTools();render();setStatus('Objetos agrupados')}
function ungroupSelected(){const items=selectedObjects(),grouped=items.filter(o=>o.groupId);if(!grouped.length){setStatus('Selecione objetos agrupados');return}pushHistory('Desagrupar objetos');const groupIds=new Set(grouped.map(o=>o.groupId));state.objects.filter(o=>groupIds.has(o.groupId)).forEach(o=>delete o.groupId);updateContextTools();render();setStatus('Objetos desagrupados')}

/* Busca rápida de comandos: Ctrl+K abre uma paleta pesquisável sem retirar
 * espaço permanente da faixa de ferramentas. Os atalhos seguem o padrão
 * mais conhecido do Corel Draw para edição e alinhamento. */
const commandPalette=document.createElement('div'),commandInput=document.createElement('input'),commandResults=document.createElement('div');
commandPalette.className='dtf-command-palette';commandPalette.setAttribute('role','dialog');commandPalette.setAttribute('aria-label','Buscar comando');commandInput.type='search';commandInput.placeholder='Digite uma ferramenta ou comando…';commandInput.setAttribute('aria-label','Buscar comando');commandResults.className='dtf-command-results';commandPalette.append(commandInput,commandResults);document.body.appendChild(commandPalette);
const commandButton=document.createElement('button');commandButton.type='button';commandButton.className='dtf-command-search';commandButton.title='Buscar comando (Ctrl+K)';commandButton.setAttribute('aria-label','Buscar comando (Ctrl+K)');commandButton.innerHTML='⌕ Buscar <kbd>Ctrl+K</kbd>';const tabsBar=Q('.dtf-tabs');if(tabsBar)tabsBar.appendChild(commandButton);
const commandEntries=()=>[
 {label:'Desfazer',shortcut:'Ctrl+Z',target:ui.undo},{label:'Refazer',shortcut:'Ctrl+Y',target:ui.redo},
 {label:'Selecionar todos',shortcut:'Ctrl+A',run:()=>{state.selectedIds=state.objects.filter(o=>o.visible).map(o=>o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;updateSelection();updateObjectUI();setStatus(state.selectedIds.length+' objetos selecionados')}},
 {label:'Copiar',shortcut:'Ctrl+C',run:copySelected},{label:'Colar',shortcut:'Ctrl+V',run:pasteClipboard},{label:'Duplicar',shortcut:'Ctrl+D',run:duplicateSelected},
 {label:'Excluir',shortcut:'Delete',target:ui.del},{label:'Agrupar',shortcut:'Ctrl+G',run:groupSelected},{label:'Desagrupar',shortcut:'Ctrl+U',run:ungroupSelected},
 {label:'Alinhar à esquerda',shortcut:'L',target:ui.alignLeft},{label:'Centralizar horizontalmente',shortcut:'C',target:ui.alignCenter},{label:'Alinhar à direita',shortcut:'R',target:ui.alignRight},
 {label:'Alinhar acima',shortcut:'T',target:ui.alignTop},{label:'Centralizar verticalmente',shortcut:'E',target:ui.alignMiddle},{label:'Alinhar abaixo',shortcut:'B',target:ui.alignBottom},
 {label:'Ajustar largura da página',shortcut:'',target:ui.zoomFit},{label:'Ajustar a imagem',shortcut:'',target:fitHeightButton},{label:'Grade',shortcut:'',target:gridButton},{label:'Guias e margens',shortcut:'',target:guidesButton},
 {label:'Excluir todas as guias',shortcut:'',run:clearCustomGuides},
 {label:'Importar imagem',shortcut:'',target:ui.load},{label:'Remover fundo',shortcut:'',target:ui.reset},{label:'Montagem inteligente com IA',shortcut:'',target:ui.magicFill}
];
let commandItems=[],commandIndex=0;
function normalizedCommandText(value){return String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase()}
function updateCommandActive(scroll=false){
 const buttons=Array.from(commandResults.querySelectorAll('.dtf-command-item'));
 buttons.forEach((button,index)=>button.classList.toggle('active',index===commandIndex));
 if(scroll&&buttons[commandIndex])buttons[commandIndex].scrollIntoView({block:'nearest'});
}
function renderCommandResults(){
 const query=normalizedCommandText(commandInput.value).trim();commandResults.innerHTML='';
 commandItems=commandEntries().filter(entry=>!query||normalizedCommandText(entry.label).includes(query)||normalizedCommandText(entry.shortcut).includes(query));
 if(!commandItems.length){const empty=document.createElement('div');empty.className='dtf-command-empty';empty.textContent='Nenhum comando encontrado.';commandResults.appendChild(empty);commandIndex=-1;return}
 commandIndex=clamp(commandIndex,0,commandItems.length-1);
 commandItems.forEach((entry,index)=>{
  const item=document.createElement('button');item.type='button';item.className='dtf-command-item';item.dataset.commandIndex=String(index);
  item.innerHTML='<span>'+entry.label+'</span>'+(entry.shortcut?'<small>'+entry.shortcut+'</small>':'');item.disabled=!!(entry.target&&entry.target.disabled);
  item.addEventListener('mouseenter',()=>{if(commandIndex!==index){commandIndex=index;updateCommandActive(false)}});
  item.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();executeCommand(entry)});
  commandResults.appendChild(item);
 });
 updateCommandActive(false);
}
function executeCommand(entry){
 if(!entry)return;
 if(entry.target&&entry.target.disabled){setStatus(entry.target.title||'Este comando não está disponível agora');return}
 closeCommandPalette();
 try{if(typeof entry.run==='function')entry.run();else if(entry.target&&typeof entry.target.click==='function')entry.target.click();else setStatus('Comando indisponível')}
 catch(error){showError('Falha ao executar '+entry.label,error)}
}
function positionCommandPalette(){const width=commandPalette.offsetWidth,height=commandPalette.offsetHeight;commandPalette.style.left=Math.max(12,(window.innerWidth-width)/2)+'px';commandPalette.style.top=Math.max(12,(window.innerHeight-height)/2)+'px'}
function openCommandPalette(){commandPalette.classList.add('show');commandInput.value='';commandIndex=0;renderCommandResults();positionCommandPalette();window.setTimeout(()=>{commandInput.focus();commandInput.select()},0)}
function closeCommandPalette(){commandPalette.classList.remove('show')}
commandButton.addEventListener('click',openCommandPalette);commandInput.addEventListener('input',()=>{commandIndex=0;renderCommandResults()});commandInput.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeCommandPalette();return}if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(commandItems.length){commandIndex=(commandIndex+(event.key==='ArrowDown'?1:-1)+commandItems.length)%commandItems.length;updateCommandActive(true)}return}if(event.key==='Enter'){event.preventDefault();executeCommand(commandItems[commandIndex])}});document.addEventListener('pointerdown',event=>{if(commandPalette.classList.contains('show')&&!event.target.closest('.dtf-command-palette,.dtf-command-search'))closeCommandPalette()});

document.addEventListener('keydown',e=>{
  if(activeEditorRoot!==editorRoot)return;
  if((e.ctrlKey||e.metaKey)&&!e.altKey&&e.key.toLowerCase()==='k'){e.preventDefault();openCommandPalette();return;}
  if(e.key==='Escape'&&commandPalette.classList.contains('show')){e.preventDefault();closeCommandPalette();return;}
  if(e.key==='Escape'){
    const toolActive=state.tool!=='select'||gridEnabled||objectSnapEnabled||guidesEnabled;
    if(toolActive){e.preventDefault();deactivateSpecialTool();deactivateBrush();state.tool='select';gridEnabled=false;objectSnapEnabled=false;guidesEnabled=false;inner.classList.remove('dtf-grid');inner.style.removeProperty('--dtf-grid-size');gridButton.classList.remove('active');objectButton.classList.remove('active');guidesButton.classList.remove('active');gridButton.setAttribute('aria-pressed','false');objectButton.setAttribute('aria-pressed','false');guidesButton.setAttribute('aria-pressed','false');updateGuidesOverlay();editorRoot.classList.remove('dtf-color-remove-cursor','dtf-area-remove-cursor');eraserCursor.classList.remove('show','dtf-area-cursor');setStatus('Ferramenta desativada — cursor normal');return;}
  }
  const editable=e.target&&(/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)||e.target.isContentEditable);
  if(e.key==='Delete'&&state.selectedGuideId&&!editable){e.preventDefault();e.stopImmediatePropagation();removeCustomGuide(state.selectedGuideId);return}
  const key=e.key.toLowerCase();
  if((e.ctrlKey||e.metaKey)&&!e.altKey&&!editable){
    if(key==='z'&&!e.shiftKey){e.preventDefault();undo();return;}
    if(key==='y'||(key==='z'&&e.shiftKey)){e.preventDefault();redo();return;}
    if(key==='d'&&selected()){e.preventDefault();duplicateSelected();return;}
    if(key==='a'){e.preventDefault();state.selectedIds=state.objects.filter(o=>o.visible).map(o=>o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;updateSelection();updateObjectUI();setStatus(state.selectedIds.length+' objetos selecionados');return;}
    if(key==='g'){e.preventDefault();groupSelected();return;}
    if(key==='u'){e.preventDefault();ungroupSelected();return;}
    if(key==='c'&&selectedObjects().length){e.preventDefault();copySelected();return;}
    if(key==='v'&&state.clipboard.length){e.preventDefault();pasteClipboard();return;}
    if((key==='+'||key==='='||e.code==='NumpadAdd')&&(state.tool==='eraser'||state.tool==='restore')){e.preventDefault();state.brushSize=clamp(state.brushSize+4,ERASER_MIN,ERASER_MAX);ui.brush.value=String(state.brushSize);ui.brushValue.textContent=String(state.brushSize);return;}
    if((key==='-'||key==='_'||e.code==='NumpadSubtract')&&(state.tool==='eraser'||state.tool==='restore')){e.preventDefault();state.brushSize=clamp(state.brushSize-4,ERASER_MIN,ERASER_MAX);ui.brush.value=String(state.brushSize);ui.brushValue.textContent=String(state.brushSize);return;}
  }
  if(!e.ctrlKey&&!e.metaKey&&!e.altKey&&!editable){
    const alignShortcuts={l:ui.alignLeft,r:ui.alignRight,t:ui.alignTop,b:ui.alignBottom,c:ui.alignCenter,e:ui.alignMiddle};
    if(alignShortcuts[key]&&!alignShortcuts[key].disabled){e.preventDefault();alignShortcuts[key].click();return;}
    if(e.key==='F4'){e.preventDefault();ui.zoomFit.click();return;}
  }
  if(e.code==='Space')state.space=true;
});
document.addEventListener('keyup',e=>{if(activeEditorRoot===editorRoot&&e.code==='Space')state.space=false});

if(ui.undo)ui.undo.addEventListener('click',()=>{closeHistoryMenus();undo()});if(ui.redo)ui.redo.addEventListener('click',()=>{closeHistoryMenus();redo()});if(ui.undoMenuBtn)ui.undoMenuBtn.addEventListener('click',e=>{e.stopPropagation();toggleHistoryMenu('undo')});if(ui.redoMenuBtn)ui.redoMenuBtn.addEventListener('click',e=>{e.stopPropagation();toggleHistoryMenu('redo')});
function duplicateSelected(){
 const o=selected();if(!o)return;pushHistory('Antes de duplicar');
 const gap=Math.max(8,Math.round(state.dpi/25.4*2));
 const candidates=[{x:o.x+o.w+gap,y:o.y},{x:gap,y:o.y+o.h+gap}];
 let place=candidates.find(p=>p.x+o.w<=canvas.width&&p.y+o.h<=canvas.height);
 if(!place){const columns=Math.max(1,Math.floor((canvas.width-gap)/(o.w+gap))),index=state.objects.length;place={x:gap+(index%columns)*(o.w+gap),y:gap+Math.floor(index/columns)*(o.h+gap)}}
 const d={...o,id:uid(),name:o.name+' cópia',x:clamp(place.x,0,Math.max(0,canvas.width-o.w)),y:clamp(place.y,0,Math.max(0,canvas.height-o.h)),canvas:canvasFromData(cloneCanvasData(o.canvas)),baseCanvas:canvasFromData(cloneCanvasData(o.baseCanvas)),aiOriginalCanvas:o.aiOriginalCanvas?canvasFromData(cloneCanvasData(o.aiOriginalCanvas)):null};state.objects.push(d);selectObject(d);render();setStatus('Objeto duplicado à direita ou na próxima linha')
}
ui.duplicate.addEventListener('click',duplicateSelected);ui.del.addEventListener('click',()=>{const items=selectedObjects();if(!items.length)return;pushHistory('Antes de excluir');const ids=new Set(items.map(o=>o.id));state.objects=state.objects.filter(x=>!ids.has(x.id));state.selectedId=null;state.selectedIds=[];render();setStatus(items.length+' objeto'+(items.length===1?'':'s')+' excluído'+(items.length===1?'':'s'))});if(ui.center&&ui.center.addEventListener)ui.center.addEventListener('click',()=>{const o=selected();if(!o)return;pushHistory('Antes de centralizar');o.x=(canvas.width-o.w)/2;o.y=(canvas.height-o.h)/2;render();setStatus('Objeto centralizado')});
function withSelected(label,fn){const items=selectedObjects();if(!items.length){setStatus('Selecione um ou mais objetos');return}pushHistory(label);items.forEach(fn);render();setStatus(label)}
if(ui.alignLeft)ui.alignLeft.addEventListener('click',()=>withSelected('Alinhado à esquerda',o=>o.x=0));
if(ui.alignCenter)ui.alignCenter.addEventListener('click',()=>withSelected('Alinhado ao centro',o=>o.x=(canvas.width-o.w)/2));
if(ui.alignRight)ui.alignRight.addEventListener('click',()=>withSelected('Alinhado à direita',o=>o.x=canvas.width-o.w));
if(ui.alignTop)ui.alignTop.addEventListener('click',()=>withSelected('Alinhado acima',o=>o.y=0));
if(ui.alignMiddle)ui.alignMiddle.addEventListener('click',()=>withSelected('Alinhado ao centro vertical',o=>o.y=(canvas.height-o.h)/2));
if(ui.alignBottom)ui.alignBottom.addEventListener('click',()=>withSelected('Alinhado abaixo',o=>o.y=canvas.height-o.h));
if(ui.distributeH)ui.distributeH.addEventListener('click',()=>{const a=selectedObjects().sort((x,y)=>x.x-y.x);if(a.length<2){setStatus('Selecione pelo menos dois objetos');return}pushHistory('Distribuição horizontal');const rowY=Math.min(...a.map(o=>o.y));a.forEach(o=>o.y=rowY);const gap=(canvas.width-a.reduce((n,o)=>n+o.w,0))/(a.length+1);let x=gap;a.forEach(o=>{o.x=x;x+=o.w+gap});render();setStatus('Objetos distribuídos na mesma linha')});
if(ui.distributeV)ui.distributeV.addEventListener('click',()=>{const a=selectedObjects().sort((x,y)=>x.y-y.y);if(a.length<2){setStatus('Selecione pelo menos dois objetos');return}pushHistory('Distribuição vertical');const colX=Math.min(...a.map(o=>o.x));a.forEach(o=>o.x=colX);const gap=(canvas.height-a.reduce((n,o)=>n+o.h,0))/(a.length+1);let y=gap;a.forEach(o=>{o.y=y;y+=o.h+gap});render();setStatus('Objetos distribuídos na mesma coluna')});
const organizeOptions=$id('dtfOrganizeOptions');
function setOrganizeOptionsOpen(open){if(!organizeOptions||!ui.organize)return;organizeOptions.classList.toggle('show',open);ui.organize.classList.toggle('active',open);ui.organize.setAttribute('aria-expanded',String(open));}
if(ui.organize)ui.organize.addEventListener('click',()=>setOrganizeOptionsOpen(!organizeOptions.classList.contains('show')));
if($id('dtfOrganizeApply'))$id('dtfOrganizeApply').addEventListener('click',()=>{const a=selectedObjects();if(!a.length){setStatus('Selecione objetos para organizar');return}const gx=Math.max(0,mmToPx(parseFloat($id('dtfOrganizeH').value)||0)),gy=Math.max(0,mmToPx(parseFloat($id('dtfOrganizeV').value)||0));pushHistory('Organizar objetos');let x=0,y=0,rowH=0;a.forEach(o=>{if(x>0&&x+o.w>canvas.width){x=0;y+=rowH+gy;rowH=0}o.x=Math.min(x,Math.max(0,canvas.width-o.w));o.y=Math.min(y,Math.max(0,canvas.height-o.h));x=o.x+o.w+gx;rowH=Math.max(rowH,o.h)});setOrganizeOptionsOpen(false);render();setStatus('Objetos organizados')});
document.addEventListener('pointerdown',event=>{if(!organizeOptions||!organizeOptions.classList.contains('show'))return;if(event.target===ui.organize||ui.organize.contains(event.target)||organizeOptions.contains(event.target))return;setOrganizeOptionsOpen(false)},{capture:true});
if(unitSelect)unitSelect.addEventListener('change',()=>{document.querySelectorAll('#dtfEditor .dtf-unit-label,#dtfEditor .dtf-mm').forEach(el=>el.textContent=unitSelect.value);try{localStorage.setItem('printway_dtf_unit',unitSelect.value)}catch(_){ }setProjectDirty(true);syncWorkspaceUI();updateObjectUI();render()});try{if(unitSelect)unitSelect.value=localStorage.getItem('printway_dtf_unit')||'mm';document.querySelectorAll('#dtfEditor .dtf-unit-label,#dtfEditor .dtf-mm').forEach(el=>el.textContent=unitSelect?unitSelect.value:'mm')}catch(_){ }

function applyField(el,key){const o=selected();if(!o)return;const v=unitToMm(parseFloat(el.value));if(!Number.isFinite(v))return;const items=selectedObjects();pushHistory('Antes de alterar medidas');const oldW=o.w,oldH=o.h;if(key==='x')o.x=clamp(physicalMmToPx(v),0,canvas.width-o.w);if(key==='y')o.y=clamp(physicalMmToPx(v),0,canvas.height-o.h);if(key==='w'){const nw=Math.max(4,physicalMmToPx(v));if(ui.lockRatio.checked)o.h=nw/(o.w/o.h);o.w=nw}else if(key==='h'){const nh=Math.max(4,physicalMmToPx(v));if(ui.lockRatio.checked)o.w=nh*(o.w/o.h);o.h=nh}o.w=Math.min(o.w,canvas.width);o.h=Math.min(o.h,canvas.height);o.x=clamp(o.x,0,canvas.width-o.w);o.y=clamp(o.y,0,canvas.height-o.h);if(items.length>1&&(key==='w'||key==='h')){const sx=o.w/oldW,sy=o.h/oldH;items.filter(item=>item!==o).forEach(item=>{item.w=Math.min(canvas.width,sx*item.w);item.h=Math.min(canvas.height,sy*item.h);item.x=clamp(item.x,0,canvas.width-item.w);item.y=clamp(item.y,0,canvas.height-item.h)})}render()}
[['objX','x'],['objY','y'],['objW','w'],['objH','h']].forEach(([id,k])=>ui[id].addEventListener('change',()=>applyField(ui[id],k)));

ui.compare.addEventListener('click',()=>{state.compare=!state.compare;ui.compareRange.disabled=!state.compare;ui.compare.classList.toggle('active',state.compare);updateCompareCanvas();setStatus(state.compare?'Comparação ativa':'Comparação desativada')});ui.compareRange.addEventListener('input',()=>{state.comparePct=parseInt(ui.compareRange.value,10)||50;ui.compareValue.textContent=state.comparePct+'%';updateCompareCanvas()});
ui.zoomOut.addEventListener('click',()=>setZoom(state.zoom-.1));ui.zoomIn.addEventListener('click',()=>setZoom(state.zoom+.1));ui.zoom100.addEventListener('click',()=>setZoom(1));ui.zoomFit.addEventListener('click',()=>fitWidthZoom(true));
viewport.addEventListener('wheel',e=>{if(e.ctrlKey||e.metaKey){e.preventDefault();const r=canvas.getBoundingClientRect(),old=state.zoom,next=e.deltaY>0?minimumZoom():clamp(old+.1,minimumZoom(),4);if(e.deltaY>0){state.zoom=next;state.panX=0;state.panY=0}else{const localX=(e.clientX-r.left)/old,localY=(e.clientY-r.top)/old;state.zoom=next;state.panX+=e.clientX-(r.left+localX*next);state.panY+=e.clientY-(r.top+localY*next)}applyZoom();setStatus('Zoom '+Math.round(next*100)+'%')}},{passive:false});

// Workspace resize by dragging when zoomed with Space.
viewport.addEventListener('pointermove',e=>{if(panDrag){state.panX=panDrag.px+(e.clientX-panDrag.sx);state.panY=panDrag.py+(e.clientY-panDrag.sy);applyZoom()}});

// Drag/drop and paste.
viewport.addEventListener('dragover',e=>{e.preventDefault();viewport.classList.add('drag-over')});viewport.addEventListener('dragleave',()=>viewport.classList.remove('drag-over'));viewport.addEventListener('drop',e=>{e.preventDefault();viewport.classList.remove('drag-over');const f=e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files[0];if(f)loadFile(f)});
document.addEventListener('paste',e=>{if(activeEditorRoot!==editorRoot)return;for(const item of e.clipboardData?.items||[]){if(item.kind==='file'){const f=item.getAsFile();if(f){loadFile(f);e.preventDefault();break}}}});

ui.tolValue.textContent=ui.tol.value;

// Export helpers.
function updateOutputInfo(){if(!ui.outputInfo)return;const size=exportPixelSize(exportScaleForFormat()),u=unitSelect?unitSelect.value:'mm',w=Number(mmToUnit(state.workWmm).toFixed(2)),h=Number(mmToUnit(state.workHmm).toFixed(2));ui.outputInfo.textContent='Área: '+w+' × '+h+' '+u+' • '+size.dpiLabel+' • '+size.width+' × '+size.height+' px'}
if(ui.quality)ui.quality.addEventListener('input',updateOutputInfo);
if(unitSelect)unitSelect.addEventListener('change',updateOutputInfo);
const EXPORT_MAX_PIXELS=64000000,EXPORT_MAX_SIDE=16384;
function exportEnhancementEnabled(format=exportFormat){return (format==='jpeg'||format==='webp')&&!!(ui.exportAiEnhance&&ui.exportAiEnhance.checked)}
function sourcePreservingMultiplier(){let multiplier=1;for(const o of state.objects){if(!o.visible||!o.canvas||!o.canvas.width||!o.canvas.height||!o.w||!o.h)continue;multiplier=Math.max(multiplier,(o.canvas.width+.5)/o.w,(o.canvas.height+.5)/o.h)}return Number.isFinite(multiplier)?Math.max(1,multiplier):1}
function exportScaleForFormat(format=exportFormat){const preserve=sourcePreservingMultiplier();if(format==='png'||format==='tiff'||format==='pdf')return preserve;if(exportEnhancementEnabled(format))return Math.max(2,preserve);return 1}
function exportPixelSize(multiplier=1){const baseDpi=Math.max(72,Number(state.dpi)||300),requestedDpi=baseDpi*Math.max(1,Number(multiplier)||1),desiredW=Math.max(1,Math.round(state.workWmm/25.4*requestedDpi)),desiredH=Math.max(1,Math.round(state.workHmm/25.4*requestedDpi));let scale=Math.min(1,EXPORT_MAX_SIDE/desiredW,EXPORT_MAX_SIDE/desiredH,Math.sqrt(EXPORT_MAX_PIXELS/(desiredW*desiredH)));if(!Number.isFinite(scale)||scale<=0)scale=1;const width=Math.max(1,Math.round(desiredW*scale)),height=Math.max(1,Math.round(desiredH*scale)),dpiX=width*25.4/state.workWmm,dpiY=height*25.4/state.workHmm,effectiveDpi=Math.min(dpiX,dpiY),enhanced=requestedDpi>baseDpi+.01;return {width,height,dpiX,dpiY,baseDpi,requestedDpi,effectiveDpi,enhanced,limited:scale<.999,dpiLabel:scale<.999?Math.round(effectiveDpi)+' DPI efetivos (limite seguro)':Math.round(requestedDpi)+' DPI'+(enhanced?' preservando pixels':'')}}
function workspaceExportCanvas(fillJpeg=false,width=canvas.width,height=canvas.height,enhance=false){const c=document.createElement('canvas');c.width=Math.max(1,Math.round(width));c.height=Math.max(1,Math.round(height));const cctx=c.getContext('2d',{alpha:!fillJpeg});cctx.imageSmoothingEnabled=true;cctx.imageSmoothingQuality='high';if(fillJpeg){cctx.fillStyle=state.bgColor;cctx.fillRect(0,0,c.width,c.height)}else if(!state.transparent){cctx.fillStyle=state.bgColor;cctx.fillRect(0,0,c.width,c.height)}const sx=c.width/canvas.width,sy=c.height/canvas.height;for(const o of state.objects){if(!o.visible)continue;const x1=Math.round(o.x*sx),y1=Math.round(o.y*sy),x2=Math.round((o.x+o.w)*sx),y2=Math.round((o.y+o.h)*sy),dw=Math.max(1,x2-x1),dh=Math.max(1,y2-y1),natural=dw===o.canvas.width&&dh===o.canvas.height;cctx.save();cctx.globalAlpha=o.opacity==null?1:o.opacity;cctx.imageSmoothingEnabled=!natural;cctx.imageSmoothingQuality='high';if(enhance)cctx.filter='contrast(1.035) saturate(1.025)';cctx.drawImage(o.canvas,0,0,o.canvas.width,o.canvas.height,x1,y1,dw,dh);cctx.restore()}return c}
function exportObjectPixelBounds(width,height){const sx=width/canvas.width,sy=height/canvas.height;let left=width,top=height,right=0,bottom=0,found=false;for(const o of state.objects){if(!o.visible||!o.canvas||(o.opacity!=null&&o.opacity<=0))continue;const x1=clamp(Math.floor(o.x*sx),0,width),y1=clamp(Math.floor(o.y*sy),0,height),x2=clamp(Math.ceil((o.x+o.w)*sx),0,width),y2=clamp(Math.ceil((o.y+o.h)*sy),0,height);if(x2<=x1||y2<=y1)continue;left=Math.min(left,x1);top=Math.min(top,y1);right=Math.max(right,x2);bottom=Math.max(bottom,y2);found=true}return found?{left,top,right,bottom}:null}
async function trimTransparentCanvas(source,onProgress=null,shouldCancel=null){if(!source||!source.width||!source.height)return {canvas:source,trimmed:false,bounds:null};const candidate=exportObjectPixelBounds(source.width,source.height);if(!candidate)return {canvas:source,trimmed:false,bounds:null};const context=source.getContext('2d',{alpha:true,willReadFrequently:true}),scanWidth=candidate.right-candidate.left,totalRows=candidate.bottom-candidate.top,stripHeight=64;let minX=candidate.right,minY=candidate.bottom,maxX=candidate.left-1,maxY=candidate.top-1,found=false,stripIndex=0;for(let y=candidate.top;y<candidate.bottom;y+=stripHeight){if(shouldCancel&&shouldCancel())return null;const rows=Math.min(stripHeight,candidate.bottom-y),pixels=context.getImageData(candidate.left,y,scanWidth,rows).data;for(let row=0;row<rows;row++){const rowStart=row*scanWidth*4;for(let x=0;x<scanWidth;x++){if(pixels[rowStart+x*4+3]===0)continue;const px=candidate.left+x,py=y+row;minX=Math.min(minX,px);minY=Math.min(minY,py);maxX=Math.max(maxX,px);maxY=Math.max(maxY,py);found=true}}stripIndex++;if(onProgress)onProgress(Math.min(100,(y+rows-candidate.top)/Math.max(1,totalRows)*100));if(stripIndex%4===0)await nextPaint()}if(!found)return {canvas:source,trimmed:false,bounds:null};const cropWidth=maxX-minX+1,cropHeight=maxY-minY+1;if(minX===0&&minY===0&&cropWidth===source.width&&cropHeight===source.height)return {canvas:source,trimmed:false,bounds:{x:minX,y:minY,width:cropWidth,height:cropHeight}};const cropped=document.createElement('canvas');cropped.width=cropWidth;cropped.height=cropHeight;cropped.getContext('2d',{alpha:true}).drawImage(source,minX,minY,cropWidth,cropHeight,0,0,cropWidth,cropHeight);return {canvas:cropped,trimmed:true,bounds:{x:minX,y:minY,width:cropWidth,height:cropHeight}}}
function saveBlob(blob,name){const u=URL.createObjectURL(blob),a=document.createElement('a');a.href=u;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(u),2500)}
function writeU32(bytes,offset,value){const v=Math.max(0,Math.round(value))>>>0;bytes[offset]=v>>>24;bytes[offset+1]=v>>>16&255;bytes[offset+2]=v>>>8&255;bytes[offset+3]=v&255}
function crc32(bytes){let crc=0xffffffff;for(let i=0;i<bytes.length;i++){crc^=bytes[i];for(let bit=0;bit<8;bit++)crc=(crc>>>1)^((crc&1)?0xedb88320:0)}return (crc^0xffffffff)>>>0}
async function pngWithResolution(blob,dpiX,dpiY){const source=new Uint8Array(await blob.arrayBuffer());if(source.length<33||source[0]!==137||source[1]!==80)return blob;const ppmX=Math.max(1,Math.round(dpiX/0.0254)),ppmY=Math.max(1,Math.round(dpiY/0.0254));let offset=8;while(offset+12<=source.length){const length=readU32(source,offset),type=String.fromCharCode(source[offset+4],source[offset+5],source[offset+6],source[offset+7]);if(type==='pHYs'&&length>=9){writeU32(source,offset+8,ppmX);writeU32(source,offset+12,ppmY);source[offset+16]=1;writeU32(source,offset+17,crc32(source.subarray(offset+4,offset+17)));return new Blob([source],{type:'image/png'})}if(type==='IEND'||offset+12+length>source.length)break;offset+=12+length}const chunk=new Uint8Array(21);writeU32(chunk,0,9);chunk.set([112,72,89,115],4);writeU32(chunk,8,ppmX);writeU32(chunk,12,ppmY);chunk[16]=1;writeU32(chunk,17,crc32(chunk.subarray(4,17)));const insert=33,result=new Uint8Array(source.length+chunk.length);result.set(source.subarray(0,insert),0);result.set(chunk,insert);result.set(source.subarray(insert),insert+chunk.length);return new Blob([result],{type:'image/png'})}
async function jpegWithResolution(blob,dpiX,dpiY){let bytes=new Uint8Array(await blob.arrayBuffer()),x=Math.max(1,Math.min(65535,Math.round(dpiX))),y=Math.max(1,Math.min(65535,Math.round(dpiY)));for(let offset=2;offset+16<bytes.length;){if(bytes[offset]!==255){offset++;continue}const marker=bytes[offset+1];if(marker===217||marker===218)break;const length=(bytes[offset+2]<<8)|bytes[offset+3],start=offset+4;if(length<2||start+length-2>bytes.length)break;if(marker===224&&length>=16&&String.fromCharCode(...bytes.subarray(start,start+5))==='JFIF\0'){bytes[start+7]=1;bytes[start+8]=x>>>8;bytes[start+9]=x&255;bytes[start+10]=y>>>8;bytes[start+11]=y&255;return new Blob([bytes],{type:'image/jpeg'})}offset=start+length-2}const app=new Uint8Array([255,224,0,16,74,70,73,70,0,1,1,1,x>>>8,x&255,y>>>8,y&255,0,0]),result=new Uint8Array(bytes.length+app.length);result.set(bytes.subarray(0,2));result.set(app,2);result.set(bytes.subarray(2),2+app.length);return new Blob([result],{type:'image/jpeg'})}
async function exportRaster(type,ext,quality){try{const format=type==='image/jpeg'?'jpeg':type==='image/webp'?'webp':'png',enhance=exportEnhancementEnabled(format),size=exportPixelSize(exportScaleForFormat(format));progress(18,enhance?'Aplicando melhoria inteligente em alta resolução...':'Renderizando sem reduzir os pixels originais...');await nextPaint();let c=workspaceExportCanvas(type==='image/jpeg',size.width,size.height,enhance),cropped=false;if(type==='image/png'&&state.transparent){progress(30,'Localizando o conteúdo visível do PNG...');const trimmed=await trimTransparentCanvas(c,value=>progress(30+value*.28,'Recortando as áreas transparentes do PNG...'));if(trimmed){c=trimmed.canvas;cropped=trimmed.trimmed}}progress(66,'Gerando '+type+' na qualidade máxima...');let b=await canvasToBlob(c,type,quality);if(type==='image/png')b=await pngWithResolution(b,size.dpiX,size.dpiY);else if(type==='image/jpeg')b=await jpegWithResolution(b,size.dpiX,size.dpiY);saveBlob(b,'dtf_uv_'+Date.now()+'.'+ext);finish(type+' exportado');const outW=Number((c.width/size.dpiX*25.4).toFixed(2)),outH=Number((c.height/size.dpiY*25.4).toFixed(2));setStatus(type+' salvo — '+outW+' × '+outH+' mm • '+c.width+' × '+c.height+' px • '+Math.round(size.effectiveDpi)+' DPI'+(cropped?' • transparência recortada':'')+(enhance?' • melhoria aplicada':''))}catch(e){finish('Erro');showError('Falha ao exportar '+type,e)}}

function loadExternalScript(url,ready,label){return new Promise((resolve,reject)=>{const script=document.createElement('script');script.src=url;script.onload=()=>ready()?resolve():reject(new Error(label+' carregou sem API'));script.onerror=()=>reject(new Error('Falha ao carregar '+label));document.head.appendChild(script)})}
let pakoPromise=null;async function ensurePako(){if(window.pako)return window.pako;if(pakoPromise)return pakoPromise;pakoPromise=(async()=>{for(const url of ['https://cdn.jsdelivr.net/npm/pako@1.0.11/dist/pako.min.js','https://unpkg.com/pako@1.0.11/dist/pako.min.js'])try{await loadExternalScript(url,()=>!!window.pako,'Pako');return window.pako}catch(_){}throw new Error('O descompactador necessário para TIFF ZIP não está disponível.')})();try{return await pakoPromise}finally{if(!window.pako)pakoPromise=null}}
let utifPromise=null;async function ensureUTIF(){if(window.UTIF)return window.UTIF;if(utifPromise)return utifPromise;utifPromise=(async()=>{await ensurePako();for(const url of ['https://cdn.jsdelivr.net/npm/utif@3.1.0/UTIF.min.js','https://unpkg.com/utif@3.1.0/UTIF.js'])try{await loadExternalScript(url,()=>!!window.UTIF,'UTIF');return window.UTIF}catch(_){}throw new Error('O decodificador TIFF não está disponível.')})();try{return await utifPromise}finally{if(!window.UTIF)utifPromise=null}}
function tiffResolutionTag(value){const denominator=1000,numerator=Math.max(1,Math.min(4294967295,Math.round(value*denominator)));return [[numerator,denominator]]}
async function exportTiff(){try{progress(8,'Calculando a resolução original das imagens...');const size=exportPixelSize(exportScaleForFormat('tiff'));if(size.limited)throw new Error('Este trabalho exige '+Math.round(size.requestedDpi)+' DPI para preservar todos os pixels e ultrapassa o limite seguro do navegador. O TIFF não foi salvo com qualidade reduzida.');const UTIF=await ensureUTIF();progress(24,'Renderizando TIFF sem reduzir os pixels originais...');await nextPaint();const c=workspaceExportCanvas(false,size.width,size.height),d=c.getContext('2d',{willReadFrequently:true}).getImageData(0,0,c.width,c.height),start=d.data.byteOffset,end=start+d.data.byteLength,rgba=d.data.buffer.slice(start,end);progress(62,'Codificando TIFF RGBA sem perda...');await nextPaint();const metadata={t274:[1],t282:tiffResolutionTag(size.dpiX),t283:tiffResolutionTag(size.dpiY),t296:[2],t305:['PrintWay Editor '+VERSION]},b=UTIF.encodeImage(rgba,c.width,c.height,metadata);saveBlob(new Blob([b],{type:'image/tiff'}),'dtf_uv_'+Date.now()+'.tif');finish('TIFF sem perda exportado');setStatus('TIFF salvo sem redução — '+state.workWmm+' × '+state.workHmm+' mm • '+c.width+' × '+c.height+' px • '+Math.round(size.effectiveDpi)+' DPI')}catch(e){finish('Erro');showError('Falha ao gerar TIFF',e)}}
let pdfStack=null;async function ensurePdfExport(){if(window.jspdf)return window.jspdf;if(pdfStack)return pdfStack;pdfStack=new Promise((resolve,reject)=>{const s=document.createElement('script');s.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';s.onload=()=>window.jspdf?resolve(window.jspdf):reject(new Error('jsPDF carregou sem API'));s.onerror=()=>reject(new Error('jsPDF não carregou'));document.head.appendChild(s)});try{return await pdfStack}finally{if(!window.jspdf)pdfStack=null}}
async function exportPdf(){try{progress(8,'Carregando exportador PDF...');await ensurePdfExport();const size=exportPixelSize(exportScaleForFormat('pdf'));progress(30,'Renderizando a folha sem reduzir os pixels originais...');const c=workspaceExportCanvas(false,size.width,size.height),png=await canvasToBlob(c,'image/png',1),bytes=new Uint8Array(await png.arrayBuffer()),{jsPDF}=window.jspdf,mmW=Number(state.workWmm),mmH=Number(state.workHmm),doc=new jsPDF({orientation:mmW>=mmH?'landscape':'portrait',unit:'mm',format:[mmW,mmH],compress:false,precision:16,putOnlyUsedFonts:true});progress(82,'Montando PDF somente com a imagem...');doc.addImage(bytes,'PNG',0,0,mmW,mmH,undefined,'NONE');saveBlob(doc.output('blob'),'dtf_uv_alta_qualidade.pdf');finish('PDF de imagem exportado');setStatus('PDF salvo somente como imagem, sem fontes — '+mmW+' × '+mmH+' mm • '+c.width+' × '+c.height+' px • '+Math.round(size.effectiveDpi)+' DPI')}catch(e){finish('Erro');showError('Falha ao gerar PDF em alta qualidade',e)}}

let exportFormat='png';
const exportChoices=QA('.dtf-export-choice');
const exportHints={png:'PNG sem perda · recorta automaticamente as áreas transparentes',tiff:'TIFF RGBA sem perda · resolução original automática',webp:'WebP em qualidade máxima · folha completa',jpeg:'JPEG em qualidade máxima · medida física gravada',pdf:'PDF fiel em alta resolução · medida exata'};
let exportPreviewTimer=0,exportPreviewToken=0,exportPreviewZoom=100,exportPreviewView='fit',exportPreviewPanX=0,exportPreviewPanY=0,exportPreviewRendered=null;
function setExportPreviewProgress(value,text){if(!ui.exportPreviewProgress)return;const amount=clamp(Number(value)||0,0,100);ui.exportPreviewProgress.hidden=false;if(ui.exportPreviewEmpty)ui.exportPreviewEmpty.hidden=true;if(ui.exportPreviewProgressBar)ui.exportPreviewProgressBar.style.width=amount+'%';if(ui.exportPreviewProgressText)ui.exportPreviewProgressText.textContent=text||'Criando prévia…'}
function hideExportPreviewProgress(){if(ui.exportPreviewProgress)ui.exportPreviewProgress.hidden=true}
function exportPreviewSource(){
 const solid=exportFormat==='jpeg';
 const enhance=exportEnhancementEnabled();
 const output=exportPixelSize(exportScaleForFormat()),lossy=exportFormat==='jpeg'||exportFormat==='webp',maxSide=lossy?2400:4096,maxPixels=lossy?6000000:12000000,scale=Math.min(1,maxSide/output.width,maxSide/output.height,Math.sqrt(maxPixels/(output.width*output.height))),width=Math.max(1,Math.round(output.width*scale)),height=Math.max(1,Math.round(output.height*scale));
 const source=workspaceExportCanvas(solid,width,height,enhance);
 return {canvas:source,label:(state.objects.some(o=>o.visible)?'Área de trabalho completa':'Área de trabalho vazia')+(enhance?' · melhoria de IA':'')+(scale<.999?' · prévia HD':'')};
}
function drawExportPreview(source,label,token,resetView=false){
 if(token!==exportPreviewToken||!ui.exportPreviewCanvas)return;
 const target=ui.exportPreviewCanvas,context=target.getContext('2d',{alpha:true}),frame=target.parentElement;
 const cssW=Math.max(280,Math.floor(frame.clientWidth||420)),cssH=Math.max(220,Math.floor(frame.clientHeight||300)),ratio=Math.min(2,window.devicePixelRatio||1);
 target.width=Math.round(cssW*ratio);target.height=Math.round(cssH*ratio);context.clearRect(0,0,target.width,target.height);
 if(!source||!source.width||!source.height){exportPreviewRendered=null;hideExportPreviewProgress();if(ui.exportPreviewEmpty){ui.exportPreviewEmpty.hidden=false;ui.exportPreviewEmpty.textContent=label||'Nada para visualizar'};if(ui.exportPreviewMeta)ui.exportPreviewMeta.textContent=label||'';return}
 const previous=exportPreviewRendered;
 if(resetView){exportPreviewView='fit';exportPreviewZoom=100;exportPreviewPanX=0;exportPreviewPanY=0}
 else if(exportPreviewView==='manual'&&previous&&previous.source!==source&&previous.displayWidthCss>0)exportPreviewZoom=clamp(previous.displayWidthCss/source.width*100,1,1000);
 if(exportPreviewView==='fit'){exportPreviewPanX=0;exportPreviewPanY=0}
 const pad=12*ratio,fit=Math.min((target.width-pad*2)/source.width,(target.height-pad*2)/source.height),fitPercent=fit/ratio*100,scale=exportPreviewView==='fit'?fit:ratio*(exportPreviewZoom/100),w=Math.max(1,source.width*scale),h=Math.max(1,source.height*scale),x=(target.width-w)/2+exportPreviewPanX*ratio,y=(target.height-h)/2+exportPreviewPanY*ratio;
 exportPreviewRendered={source,label,fitPercent,displayWidthCss:w/ratio,displayHeightCss:h/ratio};
 context.imageSmoothingEnabled=true;context.imageSmoothingQuality='high';context.drawImage(source,x,y,w,h);
 if(ui.exportPreviewEmpty)ui.exportPreviewEmpty.hidden=true;
 hideExportPreviewProgress();if(ui.exportPreviewMeta)ui.exportPreviewMeta.textContent=label+' · '+source.width+' × '+source.height+' px · '+(exportPreviewView==='fit'?'enquadrado':'zoom '+Math.round(exportPreviewZoom)+'%')+' · roda: zoom até 1000% · dois cliques: alternar';
}
async function renderExportPreview(){
 if(!ui.exportPreviewCanvas||!ui.exportModal||ui.exportModal.hidden)return;
 const token=++exportPreviewToken;
 setExportPreviewProgress(5,'Preparando prévia…');if(ui.exportPreviewEmpty){ui.exportPreviewEmpty.hidden=false;ui.exportPreviewEmpty.textContent='Atualizando prévia…'}
 try{
  const result=exportPreviewSource();
  if(!result.canvas){drawExportPreview(null,result.label,token);return}
  if(exportFormat==='png'&&state.transparent){setExportPreviewProgress(16,'Localizando o conteúdo visível...');await nextPaint();const trimmed=await trimTransparentCanvas(result.canvas,value=>setExportPreviewProgress(16+value*.64,'Recortando transparência da prévia...'),()=>token!==exportPreviewToken);if(!trimmed)return;result.canvas=trimmed.canvas;if(trimmed.trimmed)result.label='PNG recortado ao conteúdo · sem espaço vazio'}
  const lossy=exportFormat==='jpeg'||exportFormat==='webp';
  if(!lossy){setExportPreviewProgress(86,'Renderizando prévia…');await nextPaint();drawExportPreview(result.canvas,result.label,token);return}
  setExportPreviewProgress(42,'Aplicando qualidade…');await nextPaint();
  const maxSide=2400,scale=Math.min(1,maxSide/Math.max(result.canvas.width,result.canvas.height)),sample=document.createElement('canvas');
  sample.width=Math.max(1,Math.round(result.canvas.width*scale));sample.height=Math.max(1,Math.round(result.canvas.height*scale));sample.getContext('2d').drawImage(result.canvas,0,0,sample.width,sample.height);
  const mime=exportFormat==='jpeg'?'image/jpeg':'image/webp',quality=(parseInt(ui.quality.value,10)||100)/100,blob=await canvasToBlob(sample,mime,quality),image=await loadBlobImage(blob),decoded=document.createElement('canvas');
  decoded.width=image.naturalWidth||image.width;decoded.height=image.naturalHeight||image.height;decoded.getContext('2d').drawImage(image,0,0);
  setExportPreviewProgress(88,'Renderizando prévia…');drawExportPreview(decoded,result.label+' · qualidade '+Math.round(quality*100),token);
 }catch(error){if(token===exportPreviewToken){drawExportPreview(null,'Não foi possível gerar a prévia',token);log('Falha na prévia de exportação',error&&error.message)}}
}
function scheduleExportPreview(){clearTimeout(exportPreviewTimer);exportPreviewTimer=window.setTimeout(renderExportPreview,100)}
function syncExportDialog(){const quality=exportFormat==='webp'||exportFormat==='jpeg';exportChoices.forEach(button=>{const active=button.dataset.exportFormat===exportFormat;button.classList.toggle('active',active);button.setAttribute('aria-checked',String(active))});if(ui.exportImageOptions)ui.exportImageOptions.hidden=!quality;if(ui.exportFormatHint)ui.exportFormatHint.textContent=exportHints[exportFormat]||'';updateOutputInfo();scheduleExportPreview()}
function closeExportDialog(){if(!ui.exportModal)return;ui.exportModal.hidden=true;if(ui.exportOpen)ui.exportOpen.focus();else Q('[data-tab="exportar"]')?.focus()}
function openExportDialog(){if(!ui.exportModal)return;const box=ui.exportModal.querySelector('.dtf-export-box');if(box){box.style.left='';box.style.top='';box.style.transform=''}syncExportDialog();ui.exportModal.hidden=false;const active=exportChoices.find(button=>button.dataset.exportFormat===exportFormat);if(active)active.focus()}
async function confirmExport(){const actions={png:()=>exportRaster('image/png','png',1),tiff:exportTiff,webp:()=>exportRaster('image/webp','webp',(parseInt(ui.quality.value,10)||100)/100),jpeg:()=>exportRaster('image/jpeg','jpg',(parseInt(ui.quality.value,10)||100)/100),pdf:exportPdf},action=actions[exportFormat];closeExportDialog();if(action)await action()}
exportChoices.forEach(button=>button.addEventListener('click',()=>{exportFormat=button.dataset.exportFormat||'png';syncExportDialog()}));
if(ui.exportOpen)ui.exportOpen.addEventListener('click',openExportDialog);
if(ui.exportCancel)ui.exportCancel.addEventListener('click',closeExportDialog);
if(ui.exportConfirm)ui.exportConfirm.addEventListener('click',confirmExport);
if(ui.exportModal){ui.exportModal.addEventListener('pointerdown',event=>{if(event.target===ui.exportModal)closeExportDialog()});ui.exportModal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeExportDialog()}})}
if(ui.exportPreviewCanvas){const frame=ui.exportPreviewCanvas.parentElement;frame.addEventListener('wheel',event=>{if(!exportPreviewRendered)return;event.preventDefault();const fitPercent=Math.max(.1,exportPreviewRendered.fitPercent||100),oldPercent=exportPreviewView==='fit'?fitPercent:exportPreviewZoom,newPercent=clamp(oldPercent*(event.deltaY<0?1.18:.85),Math.min(10,fitPercent),1000);if(event.deltaY>0&&newPercent<=fitPercent*1.01){exportPreviewView='fit';exportPreviewPanX=0;exportPreviewPanY=0}else{exportPreviewView='manual';exportPreviewZoom=newPercent;const rect=frame.getBoundingClientRect(),dx=event.clientX-(rect.left+rect.width/2),dy=event.clientY-(rect.top+rect.height/2),factor=newPercent/oldPercent;exportPreviewPanX=(exportPreviewPanX-dx)*factor+dx;exportPreviewPanY=(exportPreviewPanY-dy)*factor+dy}drawExportPreview(exportPreviewRendered.source,exportPreviewRendered.label,exportPreviewToken,false)},{passive:false});frame.addEventListener('dblclick',event=>{if(!exportPreviewRendered)return;event.preventDefault();event.stopPropagation();exportPreviewView=exportPreviewView==='fit'?'manual':'fit';exportPreviewZoom=100;exportPreviewPanX=0;exportPreviewPanY=0;drawExportPreview(exportPreviewRendered.source,exportPreviewRendered.label,exportPreviewToken,false)});let drag=null;frame.addEventListener('pointerdown',event=>{if(exportPreviewView==='fit')return;drag={x:event.clientX,y:event.clientY,px:exportPreviewPanX,py:exportPreviewPanY,id:event.pointerId};frame.setPointerCapture(event.pointerId)});frame.addEventListener('pointermove',event=>{if(!drag||drag.id!==event.pointerId)return;exportPreviewPanX=drag.px+event.clientX-drag.x;exportPreviewPanY=drag.py+event.clientY-drag.y;drawExportPreview(exportPreviewRendered.source,exportPreviewRendered.label,exportPreviewToken,false)});frame.addEventListener('pointerup',()=>drag=null);frame.addEventListener('pointercancel',()=>drag=null)}
if(ui.quality)ui.quality.addEventListener('input',()=>{if(ui.qualityValue)ui.qualityValue.textContent=ui.quality.value;scheduleExportPreview()});if(ui.exportAiEnhance)ui.exportAiEnhance.addEventListener('change',()=>{updateOutputInfo();scheduleExportPreview()});syncExportDialog();

// PDF page is controlled by the selected PDF object: double-click PDF object while selected to move page? For a simple UI, a PDF object keeps page 1. Re-convert is exposed through selecting PDF and pressing original reset if needed.

// Keyboard delete and arrow movement.
document.addEventListener('keydown',e=>{if(activeEditorRoot!==editorRoot)return;const o=selected();if(!o)return;if(e.key==='Delete'&&document.activeElement.tagName!=='INPUT'&&document.activeElement.tagName!=='SELECT'){e.preventDefault();ui.del.click()}if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)&&!e.ctrlKey&&!e.metaKey&&document.activeElement.tagName!=='INPUT'){e.preventDefault();pushHistory('Antes de mover por teclado');const d=e.shiftKey?10:1;if(e.key==='ArrowUp')o.y=clamp(o.y-d,0,canvas.height-o.h);if(e.key==='ArrowDown')o.y=clamp(o.y+d,0,canvas.height-o.h);if(e.key==='ArrowLeft')o.x=clamp(o.x-d,0,canvas.width-o.w);if(e.key==='ArrowRight')o.x=clamp(o.x+d,0,canvas.width-o.w);render()}});

// Keep legacy exposed API for compatibility/debugging.
const editorApi={state,render,selectObject,addObject,fitZoom};
function activateEditor(){activeEditorRoot=editorRoot;window._dtfEditor=editorApi;}
if(!activeEditorRoot)activateEditor();
editorRoot.addEventListener('pointerdown',activateEditor);
editorRoot.addEventListener('focusin',activateEditor);

// Global error logging.
window.addEventListener('error',e=>log('Global JS error',e.error?e.error.stack:e.message));window.addEventListener('unhandledrejection',e=>log('Unhandled promise rejection',e.reason));


// A tolerância é apenas configurada na barra. A análise pesada só acontece ao clicar em Remover fundo.
ui.tol.addEventListener('input',()=>{if(ui.tolValue)ui.tolValue.textContent=ui.tol.value;if(selectedObjects().length)setStatus('Tolerância '+ui.tol.value+' preparada para Remover fundo.');});
let propertyClipboard=null;function openPropertiesModal(o){const oldW=o.w,oldH=o.h,m=document.createElement('div');m.className='dtf-properties-modal';m.innerHTML='<div class="dtf-properties-box"><h3>Propriedades do objeto</h3><label>Largura (mm)<input id="propW" type="number" min="0.1" step="0.1"></label><label>Altura (mm)<input id="propH" type="number" min="0.1" step="0.1"></label><div class="dtf-properties-actions"><button type="button" data-cancel>Cancelar</button><button type="button" data-preview>Prévia</button><button type="button" data-confirm>Confirmar</button></div></div>';document.body.appendChild(m);const w=m.querySelector('#propW'),h=m.querySelector('#propH');w.value=pxToMm(o.w).toFixed(1);h.value=pxToMm(o.h).toFixed(1);const apply=()=>{o.w=Math.min(canvas.width,physicalMmToPx(parseFloat(w.value)||pxToMm(oldW)));o.h=Math.min(canvas.height,physicalMmToPx(parseFloat(h.value)||pxToMm(oldH)));o.x=clamp(o.x,0,canvas.width-o.w);o.y=clamp(o.y,0,canvas.height-o.h);render()};m.querySelector('[data-preview]').onclick=apply;m.querySelector('[data-confirm]').onclick=()=>{pushHistory('Alterar propriedades');apply();m.remove()};m.querySelector('[data-cancel]').onclick=()=>{o.w=oldW;o.h=oldH;render();m.remove()}}
// As propriedades são abertas exclusivamente pelo menu de contexto.
if(copyPropMenu&&pastePropMenu&&canvasMenu){copyPropMenu.style.display='none';pastePropMenu.style.display='none';const pm=document.createElement('div');pm.className='dtf-properties-menu';const pb=document.createElement('button');pb.type='button';pb.textContent='Propriedades ▸';const ps=document.createElement('div');ps.className='dtf-properties-submenu';const edit=document.createElement('button');edit.type='button';edit.textContent='Editar';const cp=copyPropMenu.cloneNode(true),pp=pastePropMenu.cloneNode(true);cp.textContent='Copiar';pp.textContent='Colar';ps.append(edit,cp,pp);pm.append(pb,ps);canvasMenu.appendChild(pm);edit.onclick=()=>{const o=selected();if(o)openPropertiesModal(o);canvasMenu.classList.remove('show')};cp.onclick=()=>copyPropMenu.click();pp.onclick=()=>pastePropMenu.click()}
function openPropertiesModal(o){const old={x:o.x,y:o.y,w:o.w,h:o.h},ratio=o.w/o.h,m=document.createElement('div');m.className='dtf-properties-modal';m.innerHTML='<div class="dtf-properties-box"><h3>Propriedades do objeto</h3><label>Largura (mm)<input id="propW" type="number" min="0.1" step="0.1"></label><label>Altura (mm)<input id="propH" type="number" min="0.1" step="0.1"></label><label><input id="propLock" type="checkbox" checked> Manter proporção</label><hr><small id="propOriginal"></small><label>Posição X (mm)<input id="propX" type="number" step="0.1"></label><label>Posição Y (mm)<input id="propY" type="number" step="0.1"></label><div class="dtf-properties-actions"><button type="button" data-center-h>Centralizar H</button><button type="button" data-center-v>Centralizar V</button><button type="button" data-cancel>Cancelar</button><button type="button" data-confirm>Confirmar</button></div></div>';document.body.appendChild(m);const q=s=>m.querySelector(s),w=q('#propW'),h=q('#propH'),x=q('#propX'),y=q('#propY'),lock=q('#propLock');const set=()=>{w.value=pxToMm(o.w).toFixed(1);h.value=pxToMm(o.h).toFixed(1);x.value=pxToMm(o.x).toFixed(1);y.value=pxToMm(o.y).toFixed(1);q('#propOriginal').textContent='Original: '+pxToMm(old.w).toFixed(1)+' × '+pxToMm(old.h).toFixed(1)+' mm'};set();let applying=false;const live=()=>{if(applying)return;let nw=Math.max(1,physicalMmToPx(parseFloat(w.value)||1)),nh=Math.max(1,physicalMmToPx(parseFloat(h.value)||1));if(lock.checked){if(document.activeElement===w)nh=nw/ratio;else nw=nh*ratio;w.value=pxToMm(nw).toFixed(1);h.value=pxToMm(nh).toFixed(1)}o.w=Math.min(canvas.width,nw);o.h=Math.min(canvas.height,nh);o.x=clamp(physicalMmToPx(parseFloat(x.value)||0),0,canvas.width-o.w);o.y=clamp(physicalMmToPx(parseFloat(y.value)||0),0,canvas.height-o.h);render()};[w,h,x,y].forEach(el=>el.addEventListener('input',live));q('[data-center-h]').onclick=()=>{o.x=(canvas.width-o.w)/2;x.value=pxToMm(o.x).toFixed(1);render()};q('[data-center-v]').onclick=()=>{o.y=(canvas.height-o.h)/2;y.value=pxToMm(o.y).toFixed(1);render()};q('[data-confirm]').onclick=()=>{applying=true;pushHistory('Alterar propriedades');applying=false;m.remove()};q('[data-cancel]').onclick=()=>{o.x=old.x;o.y=old.y;o.w=old.w;o.h=old.h;render();m.remove()}}
const root=editorRoot;
let propertyDrag=null;document.addEventListener('pointerdown',e=>{const h=e.target.closest('.dtf-properties-box h3');if(!h)return;const b=h.parentElement,r=b.getBoundingClientRect();propertyDrag={b,sx:e.clientX,sy:e.clientY,x:r.left,y:r.top};e.preventDefault()});document.addEventListener('pointermove',e=>{if(!propertyDrag)return;propertyDrag.b.style.left=propertyDrag.x+e.clientX-propertyDrag.sx+'px';propertyDrag.b.style.top=propertyDrag.y+e.clientY-propertyDrag.sy+'px';propertyDrag.b.style.transform='none'});document.addEventListener('pointerup',()=>propertyDrag=null);
let activeTab='arquivo', helpOpen=false;
openPropertiesModal=function(o){const oldW=o.w,oldH=o.h,ratio=o.w/o.h,m=document.createElement('div');m.className='dtf-properties-modal';m.innerHTML='<div class="dtf-properties-box"><h3>Propriedades</h3><div class="dtf-properties-fields"><label>Largura (mm)<input id="propW" type="number" min="0.1" step="0.1"></label><label>Altura (mm)<input id="propH" type="number" min="0.1" step="0.1"></label></div><label><input id="propLock" type="checkbox" checked> Manter proporção</label><small>Original: '+pxToMm(oldW).toFixed(1)+' × '+pxToMm(oldH).toFixed(1)+' mm</small><div class="dtf-properties-actions"><button type="button" data-preview>Prévia</button><button type="button" data-cancel>Cancelar</button><button type="button" data-confirm>Confirmar</button></div><small class="dtf-preview-hint">Pressione e mantenha o botão Prévia para visualizar.</small></div>';document.body.appendChild(m);const w=m.querySelector('#propW'),h=m.querySelector('#propH'),lock=m.querySelector('#propLock');w.value=pxToMm(o.w).toFixed(1);h.value=pxToMm(o.h).toFixed(1);const live=()=>{let nw=physicalMmToPx(parseFloat(w.value)||1),nh=physicalMmToPx(parseFloat(h.value)||1);if(lock.checked){if(document.activeElement===w)nh=nw/ratio;else nw=nh*ratio;w.value=pxToMm(nw).toFixed(1);h.value=pxToMm(nh).toFixed(1)}o.w=Math.min(canvas.width,nw);o.h=Math.min(canvas.height,nh);render()};w.oninput=live;h.oninput=live;const pv=m.querySelector('[data-preview]'),box=m.querySelector('.dtf-properties-box');let previewStarted=0,previewTimer=0;const showPreview=()=>{clearTimeout(previewTimer);previewStarted=Date.now();box.style.opacity='.1'};const restorePreview=()=>{clearTimeout(previewTimer);previewTimer=setTimeout(()=>{box.style.opacity='1';previewStarted=0},Math.max(0,2000-(Date.now()-previewStarted)))};pv.onpointerdown=showPreview;pv.onpointerup=pv.onpointerleave=pv.onpointercancel=restorePreview;pv.onclick=()=>{if(!previewStarted){showPreview();restorePreview()}};m.querySelector('[data-confirm]').onclick=()=>{clearTimeout(previewTimer);pushHistory('Alterar propriedades');m.remove()};m.querySelector('[data-cancel]').onclick=()=>{clearTimeout(previewTimer);o.w=oldW;o.h=oldH;render();m.remove()}}
function showEditorTab(tab){
 deactivateSpecialTool(true);deactivateBrush();
 if(tab==='ajuda')helpOpen=!helpOpen;else activeTab=tab;
 QA('[data-tab]').forEach(b=>{
  const on=b.dataset.tab==='ajuda'?helpOpen:b.dataset.tab===activeTab;
  b.classList.toggle('active',on);b.setAttribute('aria-selected',String(on));
  if(b.dataset.tab==='ajuda')b.setAttribute('aria-pressed',String(helpOpen));
 });
 QA('[data-panel]').forEach(p=>p.classList.toggle('active',p.dataset.panel==='ajuda'?helpOpen:!helpOpen&&p.dataset.panel===activeTab));
 QA('[data-help-for]').forEach(c=>c.style.display=c.dataset.helpFor===activeTab?'block':'none');
 $id('dtfHelpTitle').textContent=Q('[data-tab="'+activeTab+'"]').textContent;
 $id('dtfHelpIntro').textContent='Ajuda ativada: acompanha a aba. Clique novamente em Ajuda para fechar.';
 if(activeTab==='editar'&&!helpOpen){scheduleRemovalGuidance();selectedObjects().forEach(scheduleSmartAreaGuidePreparation)}else hideRemovalGuide();
}
QA('[data-tab]').forEach(b=>b.addEventListener('click',()=>{const tab=b.dataset.tab;showEditorTab(tab);if(tab==='exportar'&&!helpOpen)openExportDialog()}));
const selectAllMenu=$id('dtfSelectAll');if(selectAllMenu)selectAllMenu.addEventListener('click',()=>{state.selectedIds=state.objects.filter(o=>o.visible).map(o=>o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;updateObjectUI();updateSelection();$id('dtfCanvasMenu').classList.remove('show');setStatus(state.selectedIds.length+' objetos selecionados')});
const copyMenu=$id('dtfCopyObjects'),pasteMenu=$id('dtfPasteObjects');if(copyMenu)copyMenu.addEventListener('click',()=>{copySelected();$id('dtfCanvasMenu').classList.remove('show')});if(pasteMenu)pasteMenu.addEventListener('click',()=>{pasteClipboard();$id('dtfCanvasMenu').classList.remove('show')});
const duplicateMenu=$id('dtfDuplicateObjects'),deleteMenu=$id('dtfDeleteObjects');if(duplicateMenu)duplicateMenu.addEventListener('click',()=>{duplicateSelected();$id('dtfCanvasMenu').classList.remove('show')});if(deleteMenu)deleteMenu.addEventListener('click',()=>{ui.del.click();$id('dtfCanvasMenu').classList.remove('show')});
function flipSelected(horizontal){const items=selectedObjects();if(!items.length)return;pushHistory(horizontal?'Inverter horizontal':'Inverter vertical');items.forEach(o=>{const c=document.createElement('canvas');c.width=o.canvas.width;c.height=o.canvas.height;const cc=c.getContext('2d');cc.translate(horizontal?c.width:0,horizontal?0:c.height);cc.scale(horizontal?-1:1,horizontal?1:-1);cc.drawImage(o.canvas,0,0);o.canvas=c});render();setStatus(horizontal?'Objetos invertidos horizontalmente':'Objetos invertidos verticalmente')}
const flipH=$id('dtfFlipH'),flipV=$id('dtfFlipV');if(flipH)flipH.addEventListener('click',()=>{flipSelected(true);$id('dtfCanvasMenu').classList.remove('show')});if(flipV)flipV.addEventListener('click',()=>{flipSelected(false);$id('dtfCanvasMenu').classList.remove('show')});
const groupMenu=$id('dtfGroupObjects'),ungroupMenu=$id('dtfUngroupObjects');function updateGroupMenu(){updateContextTools()}
if(groupMenu)groupMenu.addEventListener('click',()=>{groupSelected();updateGroupMenu();$id('dtfCanvasMenu').classList.remove('show')});if(ungroupMenu)ungroupMenu.addEventListener('click',()=>{ungroupSelected();updateGroupMenu();$id('dtfCanvasMenu').classList.remove('show')});
root.addEventListener('click',e=>{
 const button=e.target.closest('button');
 if(button&&!['dtfEraser','dtfRestoreBrush','dtfClickRemove','dtfAreaRemove','dtfColorRemove','dtfFillTool'].includes(button.id)){deactivateSpecialTool(true);deactivateBrush()}
},true);

// Estado de saída.
function updateOutputInfo(){const enhanced=exportEnhancementEnabled(),size=exportPixelSize(exportScaleForFormat()),q=Math.max(1,Math.min(100,Number(ui.quality&&ui.quality.value)||100)),lossy=exportFormat==='jpeg'||exportFormat==='webp',measure=Number(state.workWmm.toFixed(2))+' × '+Number(state.workHmm.toFixed(2))+' mm';if(ui.outputInfo)ui.outputInfo.textContent=measure+' · '+size.width+' × '+size.height+' px · '+size.dpiLabel+(lossy?' · qualidade '+q+'%'+(q===100?' (máxima)':'')+(enhanced?' · melhoria de IA ativa':''):' · sem perda de qualidade')}
if(ui.quality)ui.quality.addEventListener('input',updateOutputInfo);if(ui.dpi)ui.dpi.addEventListener('change',()=>{state.dpi=Number(ui.dpi.value)||state.dpi;setProjectDirty(true);updateOutputInfo();syncWorkspaceUI();render()});updateOutputInfo();
viewport.addEventListener('dblclick',e=>{if(state.tool!=='select')return;const p=clientToWorkspace(e),o=p&&hitTest(p.x,p.y);if(o){e.preventDefault();e.stopPropagation();selectObject(o,false);openPropertiesModal(o)}},{capture:true});
const updateSelectionWithLocks=updateSelection;updateSelection=function(){updateSelectionWithLocks();inner.querySelectorAll('.dtf-lock-badge').forEach(el=>el.remove());state.objects.filter(o=>o.visible&&o.locked).forEach(o=>{const lb=document.createElement('div');lb.className='dtf-lock-badge';lb.textContent='🔒';lb.style.left=o.x+'px';lb.style.top=o.y+'px';inner.appendChild(lb)})};
function keepContextMenuVisible(){const menu=$id('dtfCanvasMenu');if(!menu||!menu.classList.contains('show'))return;const r=menu.getBoundingClientRect();menu.style.left=Math.max(8,Math.min(parseFloat(menu.style.left)||8,window.innerWidth-r.width-8))+'px';menu.style.top=Math.max(8,Math.min(parseFloat(menu.style.top)||8,window.innerHeight-r.height-8))+'px'}window.addEventListener('resize',()=>{keepContextMenuVisible();updateScrollSpace();updateRulers();positionMagicLayoutPreview()});window.addEventListener('scroll',keepContextMenuVisible,true);if(typeof ResizeObserver==='function'){const rulerObserver=new ResizeObserver(()=>updateRulers());rulerObserver.observe(inner);rulerObserver.observe(viewport)}
// Permite digitação natural nos campos (50, 50.5 etc.) sem formatar a cada tecla.
const openPropertiesModalStable=openPropertiesModal;
openPropertiesModal=function(o){
 openPropertiesModalStable(o);
 const m=document.querySelector('.dtf-properties-modal:not(.dtf-quick-properties):not(.dtf-export-modal)');
 if(!m)return;
 let w=m.querySelector('#propW'),h=m.querySelector('#propH'),lock=m.querySelector('#propLock');
 // Substitui os inputs originais para remover listeners/máscaras aplicados pelo tema.
 const unmask=el=>{if(!el)return el;const copy=el.cloneNode(true);copy.type='text';copy.inputMode='decimal';copy.removeAttribute('min');copy.removeAttribute('max');copy.removeAttribute('step');el.replaceWith(copy);return copy};
 w=unmask(w);h=unmask(h);lock=m.querySelector('#propLock');
 [w,h].forEach(el=>el&&el.addEventListener('focus',()=>el.select(),{once:false}));
 const ratio=o.w/o.h;
 const numberValue=el=>{const value=String(el&&el.value||'').trim().replace(',','.');return Number(value)};
 const applyValue=(source)=>{let nw=physicalMmToPx(numberValue(w)),nh=physicalMmToPx(numberValue(h));if(!Number.isFinite(nw)||!Number.isFinite(nh))return;if(lock&&lock.checked){if(source==='w'){nh=nw/ratio;h.value=pxToMm(nh).toFixed(1)}else{nw=nh*ratio;w.value=pxToMm(nw).toFixed(1)}}o.w=Math.min(canvas.width,nw);o.h=Math.min(canvas.height,nh);o.x=clamp(o.x,0,canvas.width-o.w);o.y=clamp(o.y,0,canvas.height-o.h);render()};
 if(w){w.oninput=e=>{e.stopPropagation();applyValue('w')};w.onblur=()=>{const n=numberValue(w);if(Number.isFinite(n))w.value=String(n).replace('.',',')}}
 if(h){h.oninput=e=>{e.stopPropagation();applyValue('h')};h.onblur=()=>{const n=numberValue(h);if(Number.isFinite(n))h.value=String(n).replace('.',',')}}
};
// Propriedades é uma ação direta; Herança permanece responsável por Copiar/Colar.
if(canvasMenu){Array.from(canvasMenu.querySelectorAll('.dtf-properties-menu')).forEach(pm=>{const b=pm.querySelector(':scope > button'),sub=pm.querySelector('.dtf-properties-submenu');if(!b||b.textContent.trim()!=='Propriedades ▸')return;b.textContent='Propriedades';if(sub)sub.remove();b.onclick=()=>{const o=selected();if(o)openPropertiesModal(o);canvasMenu.classList.remove('show')}})}
QA('.dtf-panel .dtf-tool').forEach(button=>{const label=button.querySelector('span:not(.dtf-icon)');if(label&&!button.title)button.title=label.textContent.trim()});
showEditorTab('arquivo');
[['dtfObjCenter',ui.center],['dtfObjDuplicate',ui.duplicate],['dtfObjDelete',ui.del]].forEach(([id,target])=>{const source=$id(id);if(source&&target&&target.click)source.addEventListener('click',()=>target.click())});

syncWorkspaceUI();fitWidthZoom(false);updateHistoryUI();render();setStatus('Pronto — carregue uma imagem ou PDF');
async function revealEditor(){
 const notice=editorRoot.parentElement.querySelector('#dtfStartup');
 try{
  if(document.fonts)await document.fonts.ready;
  if(getComputedStyle(editorRoot).getPropertyValue('--dtf-css-ready').trim()!=='1')throw new Error('Estilos do editor não carregaram.');
  editorRoot.style.display='block';
  await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
  fitWidthZoom(false);render();
  editorRoot.style.visibility='visible';editorRoot.setAttribute('aria-busy','false');
  editorRoot.dataset.dtfInitialized='ready';
  if(notice)notice.style.display='none';
  warmBrowserBgModel();
  window.setTimeout(()=>offerAutosaveRecovery(),500);
 }catch(error){reportStartupError(editorRoot,error);}
}
if(document.readyState==='complete')revealEditor();else window.addEventListener('load',revealEditor,{once:true});
}

function reportStartupError(root,error){
 root.dataset.dtfInitialized='error';root.style.display='none';
 let notice=root.parentElement.querySelector('#dtfStartup');
 if(!notice){notice=document.createElement('div');root.before(notice);}
 notice.style.display='block';notice.setAttribute('role','alert');
 notice.textContent='Não foi possível iniciar o Editor de imagens '+VERSION+': '+error.message;
 console.error('[PrintWay Editor '+VERSION+']',error);
}
function initAllEditors(){
 document.querySelectorAll(ROOT_SELECTOR).forEach(root=>{
  // Shortcodes rendered inside a Help example are never application instances.
  if(root.parentElement.closest(ROOT_SELECTOR))return;
  try{initEditor(root);}catch(error){reportStartupError(root,error);}
 });
}
window.PrintWayImageEditor={version:VERSION,init:initAllEditors};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initAllEditors,{once:true});
else initAllEditors();
// Elementor can insert a shortcode widget after the initial page load.
new MutationObserver(records=>{
 if(document.readyState==='loading')return;
 if(records.some(r=>Array.from(r.addedNodes).some(n=>n.nodeType===1&&(n.matches(ROOT_SELECTOR)||n.querySelector(ROOT_SELECTOR)))))initAllEditors();
}).observe(document.documentElement,{childList:true,subtree:true});
})();

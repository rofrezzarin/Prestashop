(function(){
'use strict';
const VERSION='1.0.457';
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
 const initialDividerHeight=Number(editorRoot.dataset.dtfDividerHeight);if(Number.isFinite(initialDividerHeight)&&initialDividerHeight>=100&&initialDividerHeight<=300)editorRoot.style.setProperty('--dtf-panel-fixed-height',Math.round(initialDividerHeight)+'px');
 let suppressUnsavedBeforeUnload=false;
 const compareVersions=(a,b)=>{const pa=String(a).split('.').map(v=>parseInt(v,10)||0),pb=String(b).split('.').map(v=>parseInt(v,10)||0),length=Math.max(pa.length,pb.length);for(let i=0;i<length;i++){if((pa[i]||0)!==(pb[i]||0))return(pa[i]||0)>(pb[i]||0)?1:-1}return 0};
 const refreshUrl=()=>{const u=new URL(window.location.href);u.searchParams.set('dtf_refresh',Date.now());return u.toString()};
 const showVersionUpdate=availableVersion=>{if(!versionLabel)return;availableVersion=availableVersion||serverVersion||VERSION;let link=versionLabel.querySelector('.dtf-version-update');const newer=compareVersions(availableVersion,VERSION)>0;if(!newer){if(link)link.remove();return}if(!link){link=document.createElement('a');link.className='dtf-version-update';link.addEventListener('click',e=>{e.preventDefault();suppressUnsavedBeforeUnload=true;try{writeAutosave(true)}catch(_){}window.location.replace(link.href)});versionLabel.append(' ',link)}link.href=refreshUrl();link.textContent='Nova versão '+availableVersion+' — Atualizar';link.title='Atualizar agora para '+availableVersion;link.setAttribute('aria-label','Atualizar para a versão '+availableVersion);link.dataset.version=availableVersion;link.classList.remove('dtf-version-update-current')};
 showVersionUpdate(serverVersion);
 const checkForUpdates=async()=>{if(!versionCheckUrl)return;try{const u=new URL(versionCheckUrl,window.location.href);u.searchParams.set('_dtf_version_check',Date.now());const response=await fetch(u.toString(),{cache:'no-store',credentials:'same-origin'});if(!response.ok)return;const payload=await response.json(),availableVersion=payload&&payload.success&&payload.data&&payload.data.version;if(availableVersion)showVersionUpdate(availableVersion)}catch(_){}};
 checkForUpdates();
 window.setInterval(checkForUpdates,10000);
 // Controls must belong to this instance, never to a nested shortcode in Help.
 const QA=s=>Array.from(editorRoot.querySelectorAll(s)).filter(el=>el.closest(ROOT_SELECTOR)===editorRoot);
 const Q=s=>QA(s)[0]||null;
 const $id=id=>Q('[id="'+id+'"]');
 const userSettingsInput=$id('dtfUserSettings');
 let userSettings={};
 try{const parsed=JSON.parse(userSettingsInput&&userSettingsInput.value||'{}');if(parsed&&typeof parsed==='object')userSettings=parsed}catch(_){userSettings={}}
 let userSettingsWriteTimer=0;
 function persistUserSettings(patch){
  userSettings={...userSettings,...patch};
  if(userSettingsWriteTimer)clearTimeout(userSettingsWriteTimer);
  userSettingsWriteTimer=window.setTimeout(()=>{
   const url=editorRoot.dataset.dtfMediaUrl,nonce=editorRoot.dataset.dtfMediaNonce;
   if(!url||!nonce)return;
   fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams({action:'dtf_uv_editor_save_user_settings',nonce,settings:JSON.stringify(userSettings)})}).catch(()=>{});
 },180);
 }

 const editorLogsStorageKey='dtf-editor-errors-v1:'+String(editorRoot.dataset.dtfUserKey||editorRoot.dataset.dtfUserId||'usuario').replace(/[^a-z0-9_-]/gi,'_');
 const editorLogsList=$id('dtfLogsList'),editorLogsStatus=$id('dtfLogsStatus'),editorLogsCopy=$id('dtfLogsCopy'),editorLogsDownload=$id('dtfLogsDownload'),editorLogsClear=$id('dtfLogsClear');
 let editorErrorLogs=[];
 try{const saved=JSON.parse(localStorage.getItem(editorLogsStorageKey)||'[]');if(Array.isArray(saved))editorErrorLogs=saved.filter(item=>item&&item.message).slice(0,100)}catch(_){editorErrorLogs=[]}
 function editorLogText(){return editorErrorLogs.map(item=>{const when=item.time?new Date(item.time).toLocaleString('pt-BR'):'Data desconhecida',context=String(item.context||'Erro do editor'),message=String(item.message||'Erro sem mensagem'),stack=String(item.stack||'');return '['+when+'] '+context+'\n'+message+(stack&&stack!==message?'\n'+stack:'')}).join('\n\n')}
 function renderEditorErrorLogs(){if(editorLogsList)editorLogsList.textContent=editorLogText();if(editorLogsStatus){editorLogsStatus.textContent=editorErrorLogs.length?editorErrorLogs.length+' erro'+(editorErrorLogs.length===1?'':'s')+' registrado'+(editorErrorLogs.length===1?'':'s')+' neste editor.':'Nenhum erro registrado neste editor.';editorLogsStatus.classList.toggle('is-error',editorErrorLogs.length>0)}}
 let editorLogsSaveTimer=0;
 function scheduleEditorErrorLogSave(){if(editorLogsSaveTimer)clearTimeout(editorLogsSaveTimer);editorLogsSaveTimer=window.setTimeout(()=>{const url=editorRoot.dataset.dtfMediaUrl,nonce=editorRoot.dataset.dtfMediaNonce;if(!url||!nonce)return;fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams({action:'dtf_uv_editor_error_logs',mode:'save',nonce,logs:JSON.stringify(editorErrorLogs)})}).catch(()=>{})},250)}
 async function loadEditorErrorLogs(){const url=editorRoot.dataset.dtfMediaUrl,nonce=editorRoot.dataset.dtfMediaNonce;if(!url||!nonce)return;try{const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams({action:'dtf_uv_editor_error_logs',mode:'get',nonce})}),payload=await response.json();if(!response.ok||!payload||!payload.success)return;const incoming=Array.isArray(payload.data&&payload.data.logs)?payload.data.logs:[],merged=[...incoming,...editorErrorLogs],seen=new Set();editorErrorLogs=merged.filter(item=>{const key=String(item.time||'')+'|'+String(item.context||'')+'|'+String(item.message||'');if(!item.message||seen.has(key))return false;seen.add(key);return true}).slice(0,100);try{localStorage.setItem(editorLogsStorageKey,JSON.stringify(editorErrorLogs))}catch(_){}renderEditorErrorLogs();if(editorErrorLogs.length!==incoming.length)scheduleEditorErrorLogSave()}catch(_){}
 }
 function recordEditorError(error,context='Erro do editor',source=''){const message=error&&error.message?String(error.message):String(error||'Erro sem mensagem'),stack=error&&error.stack?String(error.stack):String(source||'');const entry={time:new Date().toISOString(),context:String(context||'Erro do editor'),message,stack};const previous=editorErrorLogs[0];if(previous&&previous.message===entry.message&&previous.context===entry.context&&Date.now()-new Date(previous.time).getTime()<1200)return;editorErrorLogs.unshift(entry);editorErrorLogs=editorErrorLogs.slice(0,100);try{localStorage.setItem(editorLogsStorageKey,JSON.stringify(editorErrorLogs))}catch(_){}renderEditorErrorLogs();scheduleEditorErrorLogSave()}
 function isEditorError(error,source=''){const stack=String(error&&error.stack||''),file=String(source||'');return /editor\.js|dtfEditor|PrintWayImageEditor/i.test(stack+' '+file)}
 window.addEventListener('error',event=>{const target=event&&event.target,source=event&&event.filename||'';if(isEditorError(event&&event.error,source)||(target&&typeof target.closest==='function'&&target.closest('#dtfEditor')))recordEditorError(event&&event.error||event&&event.message||'Erro JavaScript','Erro não tratado no Editor',source)},{capture:true});
 window.addEventListener('unhandledrejection',event=>{const reason=event&&event.reason;if(isEditorError(reason))recordEditorError(reason,'Promessa não tratada no Editor')});
 if(editorLogsCopy)editorLogsCopy.addEventListener('click',async()=>{const text=editorLogText();if(!text){if(editorLogsStatus)editorLogsStatus.textContent='Não há erros para copiar.';return}try{await navigator.clipboard.writeText(text);if(editorLogsStatus)editorLogsStatus.textContent='Logs copiados. Cole o conteúdo na conversa.'}catch(_){const area=document.createElement('textarea');area.value=text;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();try{document.execCommand('copy');if(editorLogsStatus)editorLogsStatus.textContent='Logs copiados. Cole o conteúdo na conversa.'}catch(__){if(editorLogsStatus)editorLogsStatus.textContent='Não foi possível copiar automaticamente; use o botão Baixar.'}area.remove()}});
 if(editorLogsDownload)editorLogsDownload.addEventListener('click',()=>{const text=editorLogText();if(!text){if(editorLogsStatus)editorLogsStatus.textContent='Não há erros para baixar.';return}const link=document.createElement('a');link.href=URL.createObjectURL(new Blob([text],{type:'text/plain;charset=utf-8'}));link.download='editor-de-imagens-erros-'+new Date().toISOString().slice(0,10)+'.txt';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(link.href),1000);if(editorLogsStatus)editorLogsStatus.textContent='Arquivo de logs baixado.'});
 if(editorLogsClear)editorLogsClear.addEventListener('click',()=>{if(!editorErrorLogs.length)return;if(!window.confirm('Limpar somente os erros registrados neste Editor de imagens?'))return;editorErrorLogs=[];try{localStorage.removeItem(editorLogsStorageKey)}catch(_){}renderEditorErrorLogs();scheduleEditorErrorLogSave()});
 renderEditorErrorLogs();loadEditorErrorLogs();

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
 clickTol:$id('dtfClickTol'), clickTolValue:$id('dtfClickTolValue'), removalApply:$id('dtfRemovalApply'), colorRemoveConnected:$id('dtfColorRemoveConnected'), colorRemoveAll:$id('dtfColorRemoveAll'), colorAreaBrush:$id('dtfColorAreaBrush'), colorAreaBrushValue:$id('dtfColorAreaBrushValue'), colorAreaBrushPreview:$id('dtfColorAreaBrushPreview'),
 newProject:$id('dtfNewProject'), load:$id('dtfLoad'), loadEdit:$id('dtfLoadEdit'), openProject:$id('dtfOpenProject'), saveProject:$id('dtfSaveProject'), reset:$id('dtfReset'), original:$id('dtfOriginal'), workW:$id('dtfWorkW'), workH:$id('dtfWorkH'), applyWork:$id('dtfApplyWork'), bgColor:$id('dtfBgColor'), transparent:$id('dtfTransparent'), dpi:$id('dtfWorkDpi'),
 removeMode:$id('dtfRemoveMode'), tol:$id('dtfTol'), tolValue:$id('dtfTolValue'), tolMinus:$id('dtfTolMinus'), tolPlus:$id('dtfTolPlus'), pickColor:$id('dtfPickColor'), pickPreview:$id('dtfPickColorPreview'), edge:$id('dtfEdge'), edgeValue:$id('dtfEdgeValue'), dehalo:$id('dtfDehalo'), alignLeft:$id('dtfAlignLeft'),alignCenter:$id('dtfAlignCenter'),alignRight:$id('dtfAlignRight'),alignTop:$id('dtfAlignTop'),alignMiddle:$id('dtfAlignMiddle'),alignBottom:$id('dtfAlignBottom'),distributeH:$id('dtfDistributeH'),distributeV:$id('dtfDistributeV'),organize:$id('dtfOrganize'),
 undo:$id('dtfUndo'), redo:$id('dtfRedo'), undoMenuBtn:$id('dtfUndoMenuBtn'), redoMenuBtn:$id('dtfRedoMenuBtn'), undoMenu:$id('dtfUndoMenu'), redoMenu:$id('dtfRedoMenu'), duplicate:$id('dtfDuplicate')||{disabled:true,addEventListener:()=>{}}, del:$id('dtfDelete')||{disabled:true,addEventListener:()=>{}}, eraser:$id('dtfEraser'), restoreBrush:$id('dtfRestoreBrush'), brush:$id('dtfBrush')||{value:'32',addEventListener:()=>{}}, brushValue:$id('dtfBrushValue')||{textContent:'32'},
 selectedName:$id('dtfSelectedName'), objectTotal:$id('dtfObjectTotal'),objectSelected:$id('dtfObjectSelected'), center:$id('dtfCenterObject')||{disabled:true,addEventListener:()=>{}}, objX:$id('dtfObjX')||{value:'',disabled:true,addEventListener:()=>{}}, objY:$id('dtfObjY')||{value:'',disabled:true,addEventListener:()=>{}}, objW:$id('dtfObjW')||{value:'',disabled:true,addEventListener:()=>{}}, objH:$id('dtfObjH')||{value:'',disabled:true,addEventListener:()=>{}}, lockRatio:$id('dtfObjLock')||{checked:true}, textInlineControls:$id('dtfTextInlineControls'), textContentInline:$id('dtfTextInlineContent'), textFontInline:$id('dtfTextInlineFont'), textSizeInline:$id('dtfTextInlineSize'), textSizeOpen:$id('dtfTextInlineSizeOpen'), textColorInline:$id('dtfTextInlineColor'), textAlignInline:$id('dtfTextInlineAlign'), textPaddingInline:$id('dtfTextInlinePadding'), textBoldInline:$id('dtfTextInlineBold'), textItalicInline:$id('dtfTextInlineItalic'), textUnderlineInline:$id('dtfTextInlineUnderline'), textLineHeightInline:$id('dtfTextInlineLineHeight'), textLetterSpacingInline:$id('dtfTextInlineLetterSpacing'), textVerticalAlignInline:$id('dtfTextInlineVerticalAlign'), textDirectionInline:$id('dtfTextInlineDirection'), textIndentInline:$id('dtfTextInlineIndent'), textListInline:$id('dtfTextInlineList'), textStrokeColorInline:$id('dtfTextInlineStrokeColor'), textStrokeWidthInline:$id('dtfTextInlineStrokeWidth'), textBackgroundColorInline:$id('dtfTextInlineBackgroundColor'), textBackgroundOpacityInline:$id('dtfTextInlineBackgroundOpacity'), textBackgroundRadiusInline:$id('dtfTextInlineBackgroundRadius'), textAutoFitInline:$id('dtfTextInlineAutoFit'), textCaseInline:$id('dtfTextInlineCase'), textShadowInline:$id('dtfTextInlineShadow'), textStyleInline:$id('dtfTextInlineStyle'), textSaveStyle:$id('dtfTextSaveStyle'), textDeleteStyle:$id('dtfTextDeleteStyle'), textConvertCurves:$id('dtfTextConvertCurves'), zoomOut:$id('dtfZoomOut'), zoom100:$id('dtfZoom100'), zoomIn:$id('dtfZoomIn'), zoomFit:$id('dtfZoomFit'), zoomFitHeight:$id('dtfZoomFitHeight'), displayModeButton:$id('dtfDisplayModeButton'), displayModeMenu:$id('dtfDisplayModeMenu'), fullscreenButton:$id('dtfFullscreenButton'), compare:$id('dtfCompare'), compareRange:$id('dtfCompareRange'), compareValue:$id('dtfCompareValue'),
 pdfPage:$id('dtfPdfPage'), pdfDpi:$id('dtfPdfDpi'), pdfConvert:$id('dtfPdfConvert'),
 exportOpen:$id('dtfOpenExport'), exportModal:$id('dtfExportModal'), exportConfirm:$id('dtfExportConfirm'), exportCancel:$id('dtfExportCancel'), exportImageOptions:$id('dtfExportImageOptions'), exportPdfOptions:$id('dtfExportPdfOptions'), exportFormatHint:$id('dtfExportFormatHint'), exportPreviewCanvas:$id('dtfExportPreviewCanvas'), exportPreviewEmpty:$id('dtfExportPreviewEmpty'), exportPreviewMeta:$id('dtfExportPreviewMeta'), exportPreviewProgress:$id('dtfExportPreviewProgress'), exportPreviewProgressBar:$id('dtfExportPreviewProgressBar'), exportPreviewProgressText:$id('dtfExportPreviewProgressText'), quality:$id('dtfQuality'), qualityValue:$id('dtfQualityValue'), exportAiEnhance:$id('dtfExportAiEnhance'), outputInfo:$id('dtfOutputInfo'), magicFill:$id('dtfMagicFill'), magicLayout:$id('dtfMagicLayout'), magicModal:$id('dtfMagicModal'), magicTitle:$id('dtfMagicTitle'), magicSourceTitle:$id('dtfMagicSourceTitle'), magicMessage:$id('dtfMagicMessage'), magicConfirm:$id('dtfMagicConfirm'), magicCancel:$id('dtfMagicCancel'), magicCutoutOnly:$id('dtfMagicCutoutOnly'), magicReview:$id('dtfMagicReview'), magicVariants:$id('dtfMagicVariants'), magicZoom:$id('dtfMagicZoom'), magicZoomImage:$id('dtfMagicZoomImage'), magicZoomLabel:$id('dtfMagicZoomLabel'), magicUpload:$id('dtfMagicUpload'), magicUploadButton:$id('dtfMagicUploadButton'), magicUploadWrap:$id('dtfMagicUploadWrap'), magicSources:$id('dtfMagicSources'), magicCapacity:$id('dtfMagicCapacity'), magicAutoHeight:$id('dtfMagicAutoHeight'), magicAutoHeightWrap:$id('dtfMagicAutoHeightWrap'), magicCropEach:$id('dtfMagicCropEach'), magicCropEachWrap:$id('dtfMagicCropEachWrap'), magicProgress:$id('dtfMagicProgress'), magicProgressBar:$id('dtfMagicProgressBar'), magicProgressText:$id('dtfMagicProgressText'), magicSteps:$id('dtfMagicSteps'), magicLayoutPreview:$id('dtfMagicLayoutPreview'), magicLayoutCanvas:$id('dtfMagicLayoutCanvas'), magicLayoutMeta:$id('dtfMagicLayoutMeta'), copyError:$id('dtfCopyError')
};
const unitSelect=$id('dtfUnit');
const fitHeightButton=document.createElement('button');fitHeightButton.type='button';fitHeightButton.className='dtf-tool dtf-align-btn';fitHeightButton.title='Ajusta a largura e a altura da área de trabalho ao limite final das imagens';fitHeightButton.setAttribute('aria-label','Ajustar a imagem: ajustar a área de trabalho às imagens');fitHeightButton.innerHTML='<span class="dtf-icon dtf-fit-height-icon">⤢</span><span>Ajustar a imagem</span>';if(ui.organize&&ui.organize.parentElement)ui.organize.parentElement.appendChild(fitHeightButton);
fitHeightButton.addEventListener('click',()=>{const items=state.objects.filter(o=>o.visible);if(!items.length){setStatus('Nenhuma imagem para ajustar');return}const right=Math.max(...items.map(o=>o.x+o.w)),bottom=Math.max(...items.map(o=>o.y+o.h));if(right<=0||bottom<=0)return;pushHistory('Ajustar área de trabalho às imagens');state.workWmm=Math.max(10,right/state.dpi*25.4);state.workHmm=Math.max(10,bottom/state.dpi*25.4);try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:state.workWmm,h:state.workHmm,dpi:state.dpi}))}catch(_){}persistUserSettings({workspace:{w:state.workWmm,h:state.workHmm,dpi:state.dpi}});syncWorkspaceUI();render();setStatus('Área de trabalho ajustada à largura e à altura final das imagens')});
const clickRemove=$id('dtfClickRemove'),areaRemoveButton=$id('dtfAreaRemove'),outerAreaRemoveButton=$id('dtfOuterAreaRemove'),globalColorRemoveButton=$id('dtfColorRemove'),colorAreaRemoveButton=$id('dtfColorAreaRemove');
const fillTool=$id('dtfFillTool'),fillColor=$id('dtfFillColor');
const mainColorPalette=document.createElement('div');mainColorPalette.className='dtf-rgb-palette';mainColorPalette.setAttribute('role','dialog');mainColorPalette.setAttribute('aria-label','Selecionar cor RGB');
const mainColorPaletteTitle=document.createElement('div');mainColorPaletteTitle.className='dtf-rgb-palette-title';mainColorPaletteTitle.textContent='Selecione uma cor RGB';
const mainColorManual=document.createElement('div');mainColorManual.className='dtf-rgb-manual';
const mainColorInput=document.createElement('input');mainColorInput.type='text';mainColorInput.className='dtf-rgb-input';mainColorInput.placeholder='RGB: 255, 0, 0 ou #ff0000';mainColorInput.setAttribute('aria-label','Digitar cor RGB personalizada');
const mainColorManualPreview=document.createElement('span');mainColorManualPreview.className='dtf-rgb-manual-preview';mainColorManualPreview.setAttribute('aria-hidden','true');
const mainColorApply=document.createElement('button');mainColorApply.type='button';mainColorApply.className='dtf-rgb-apply';mainColorApply.textContent='Aplicar';mainColorApply.title='Aplicar cor digitada à cor principal';
mainColorManual.append(mainColorInput,mainColorManualPreview,mainColorApply);
const mainColorCustomTitle=document.createElement('div');mainColorCustomTitle.className='dtf-rgb-section-title';mainColorCustomTitle.textContent='Personalizadas';
const mainColorCustomGrid=document.createElement('div');mainColorCustomGrid.className='dtf-rgb-custom-grid';
const mainColorFixedTitle=document.createElement('div');mainColorFixedTitle.className='dtf-rgb-section-title';mainColorFixedTitle.textContent='Cores RGB';
const mainColorGrid=document.createElement('div');mainColorGrid.className='dtf-rgb-palette-grid';
const mainNoColorButton=document.createElement('button');mainNoColorButton.type='button';mainNoColorButton.className='dtf-rgb-color dtf-rgb-no-color';mainNoColorButton.dataset.noneColor='1';mainNoColorButton.title='Sem cor';mainNoColorButton.setAttribute('aria-label','Sem cor');mainNoColorButton.innerHTML='<span aria-hidden="true"></span>';mainNoColorButton.addEventListener('click',()=>setStatus('Sem cor: com forma selecionada, clique esquerdo remove o preenchimento.'));mainNoColorButton.addEventListener('contextmenu',event=>{event.preventDefault();setStatus('Sem cor: com forma selecionada, botão direito remove a borda.')});
mainColorPalette.append(mainColorPaletteTitle,mainColorManual,mainColorCustomTitle,mainColorCustomGrid,mainColorFixedTitle,mainColorGrid);document.body.appendChild(mainColorPalette);
const mainColorWrap=document.createElement('div');mainColorWrap.className='dtf-main-color-wrap';
const mainColorSeparator=document.createElement('span');mainColorSeparator.className='dtf-main-color-separator';mainColorSeparator.setAttribute('aria-hidden','true');
const cursorToolSeparator=document.createElement('span');cursorToolSeparator.className='dtf-cursor-tool-separator';cursorToolSeparator.setAttribute('aria-hidden','true');
const mainColorButton=document.createElement('button');mainColorButton.type='button';mainColorButton.id='dtfMainColorButton';mainColorButton.className='dtf-main-color-button';mainColorButton.title='Selecionar cor RGB';mainColorButton.setAttribute('aria-label','Selecionar cor RGB');
const lineWidthButton=document.createElement('button');lineWidthButton.type='button';lineWidthButton.id='dtfLineWidthButton';lineWidthButton.className='dtf-line-width-button';lineWidthButton.title='Espessura da linha';lineWidthButton.setAttribute('aria-label','Espessura da linha');lineWidthButton.setAttribute('aria-expanded','false');lineWidthButton.disabled=true;lineWidthButton.innerHTML='<span class="dtf-line-width-icon" aria-hidden="true"><i></i><i></i><i></i></span>';
const lineWidthPalette=document.createElement('div');lineWidthPalette.className='dtf-line-width-palette';lineWidthPalette.setAttribute('role','dialog');lineWidthPalette.setAttribute('aria-label','Espessura da linha');
const lineWidthTitle=document.createElement('div');lineWidthTitle.className='dtf-line-width-title';lineWidthTitle.textContent='Espessura da linha';
const lineWidthGrid=document.createElement('div');lineWidthGrid.className='dtf-line-width-grid';
const lineWidthManual=document.createElement('div');lineWidthManual.className='dtf-line-width-manual';lineWidthManual.innerHTML='<label>Espessura <input id="dtfLineWidthInput" type="number" min="0" max="100" step="0.5" inputmode="decimal" aria-label="Espessura da linha em pixels"></label><span>px</span><button type="button" id="dtfLineWidthApply">Aplicar</button>';
lineWidthPalette.append(lineWidthTitle,lineWidthGrid,lineWidthManual);document.body.appendChild(lineWidthPalette);
const lineWidthInput=lineWidthManual.querySelector('#dtfLineWidthInput'),lineWidthApply=lineWidthManual.querySelector('#dtfLineWidthApply');
mainColorWrap.append(mainColorButton,lineWidthButton,mainColorSeparator);
function normalizeHexColor(value){const input=String(value||'').trim();if(/^#[0-9a-f]{6}$/i.test(input))return input.toLowerCase();if(/^#[0-9a-f]{3}$/i.test(input))return '#'+input.slice(1).split('').map(ch=>ch+ch).join('').toLowerCase();return '#8c3f00'}
function rgbToHex(r,g,b){return '#'+[r,g,b].map(v=>clamp(Math.round(Number(v)||0),0,255).toString(16).padStart(2,'0')).join('')}
function parseTypedRgbColor(value){
 const input=String(value||'').trim();
 if(!input)return null;
 if(/^#[0-9a-f]{6}$/i.test(input)||/^#[0-9a-f]{3}$/i.test(input))return normalizeHexColor(input);
 const numbers=input.match(/-?\d+(?:[.,]\d+)?/g);
 if(!numbers||numbers.length<3)return null;
 const vals=numbers.slice(0,3).map(v=>clamp(Math.round(Number(v.replace(',','.'))||0),0,255));
 return rgbToHex(vals[0],vals[1],vals[2]);
}
function rgbParts(hex){hex=normalizeHexColor(hex);return {r:parseInt(hex.slice(1,3),16),g:parseInt(hex.slice(3,5),16),b:parseInt(hex.slice(5,7),16)}}
function rgbLabel(hex){const p=rgbParts(hex);return 'RGB '+p.r+', '+p.g+', '+p.b}
function rgbText(hex){const p=rgbParts(hex);return p.r+', '+p.g+', '+p.b}
function syncMainColorButton(){const hex=normalizeHexColor(fillColor&&fillColor.value?fillColor.value:'#8c3f00');mainColorButton.style.backgroundColor=hex;mainColorButton.title='Cor atual — '+rgbLabel(hex);mainColorButton.setAttribute('aria-label','Cor atual — '+rgbLabel(hex))}
const mainColorCustomKey='printway_dtf_custom_colors_v2',mainColorCustomSelectedKey='printway_dtf_custom_selected_v2';
const mainColorUserKey=String(editorRoot.dataset.dtfUserKey||editorRoot.dataset.dtfUserId||'usuario').replace(/[^a-z0-9_-]/gi,'_')||'usuario';
let mainColorCustomScope='novo',mainColorCustomColors=Array(12).fill(''),mainColorCustomSelected=0,pendingEyedropperCustomColor='';
function mainColorScopeId(scope){return String(scope||'novo').replace(/\.pwedit$/i,'').replace(/[^a-z0-9_-]/gi,'_').slice(0,100)||'novo'}
function mainColorStorageKey(base,scope){return base+'_'+mainColorUserKey+'_'+mainColorScopeId(scope)}
function readMainColorScope(scope){const colors=Array(12).fill('');let selected=0;try{const scoped=JSON.parse(localStorage.getItem(mainColorStorageKey(mainColorCustomKey,scope))||'[]');if(Array.isArray(scoped))scoped.slice(0,12).forEach((color,index)=>{if(/^#[0-9a-f]{3,6}$/i.test(String(color||'')))colors[index]=normalizeHexColor(color)});selected=clamp(parseInt(localStorage.getItem(mainColorStorageKey(mainColorCustomSelectedKey,scope))||'0',10)||0,0,11);if(!colors.some(Boolean)){const legacy=JSON.parse(localStorage.getItem('printway_dtf_custom_colors')||'[]');if(mainColorScopeId(scope)==='novo'&&Array.isArray(legacy))legacy.slice(0,12).forEach((color,index)=>{if(/^#[0-9a-f]{3,6}$/i.test(String(color||'')))colors[index]=normalizeHexColor(color)})}}catch(_){}return {colors,selected}}
function syncMainColorScope(scope){const next=readMainColorScope(scope);mainColorCustomScope=String(scope||'novo');mainColorCustomColors=next.colors;mainColorCustomSelected=next.selected;pendingEyedropperCustomColor='';renderMainColorCustoms()}
function saveMainColorCustoms(){try{localStorage.setItem(mainColorStorageKey(mainColorCustomKey,mainColorCustomScope),JSON.stringify(mainColorCustomColors));localStorage.setItem(mainColorStorageKey(mainColorCustomSelectedKey,mainColorCustomScope),String(mainColorCustomSelected))}catch(_){}
}
syncMainColorScope('novo');
function renderMainColorCustoms(){
 mainColorCustomGrid.textContent='';
 mainColorCustomColors.forEach((hex,index)=>{
  const item=document.createElement('button');item.type='button';item.className='dtf-rgb-custom-color'+(index===mainColorCustomSelected?' selected':'')+(hex?'':' empty');
  item.style.backgroundColor=hex||'transparent';item.title=hex?('Personalizada '+(index+1)+' — '+rgbLabel(hex)):('Personalizada '+(index+1)+' — vazia');
  item.setAttribute('aria-label',item.title);
  item.addEventListener('click',()=>{
   mainColorCustomSelected=index;
   if(pendingEyedropperCustomColor){const replacement=pendingEyedropperCustomColor;mainColorCustomColors[index]=replacement;pendingEyedropperCustomColor='';saveMainColorCustoms();renderMainColorCustoms();setMainColor(replacement);setStatus('Cor capturada salva na Personalizada '+(index+1)+'.');return}
   saveMainColorCustoms();
   if(hex){setMainColor(hex);return}
   renderMainColorCustoms();setStatus('Quadrinho personalizado '+(index+1)+' está vazio. Use o conta-gotas para preenchê-lo.');
  });
  mainColorCustomGrid.appendChild(item);
 });
}
function updateMainColorManualPreview(){
 const hex=parseTypedRgbColor(mainColorInput.value)||normalizeHexColor(fillColor&&fillColor.value?fillColor.value:'#8c3f00');
 mainColorManualPreview.style.backgroundColor=hex;
 mainColorManualPreview.title=rgbLabel(hex);
}
function positionMainColorPalette(){if(!mainColorPalette.classList.contains('show'))return;const r=mainColorButton.getBoundingClientRect(),w=mainColorPalette.offsetWidth||360,h=mainColorPalette.offsetHeight||340;mainColorPalette.style.left=Math.max(8,Math.min(r.left,window.innerWidth-w-8))+'px';mainColorPalette.style.top=Math.max(8,Math.min(r.bottom+8,window.innerHeight-h-8))+'px'}
function closeMainColorPalette(){mainColorPalette.classList.remove('show');mainColorButton.setAttribute('aria-expanded','false')}
function openMainColorPalette(){
 const textTarget=typeof textInlineTarget==='function'?textInlineTarget():null;
 if(textTarget&&typeof saveTextSelectionBeforeToolbar==='function')saveTextSelectionBeforeToolbar();
 mainColorInput.value=rgbText(fillColor&&fillColor.value?fillColor.value:'#8c3f00');updateMainColorManualPreview();renderMainColorCustoms();mainColorPalette.classList.add('show');mainColorButton.setAttribute('aria-expanded','true');positionMainColorPalette();
 if(textTarget)setStatus('Paleta aberta: clique em uma cor para aplicar ao texto selecionado.');
 else if(selectedRectangleObjects&&selectedRectangleObjects().length)setStatus('Paleta aberta: clique esquerdo aplica preenchimento, direito aplica borda.');
 setTimeout(()=>{mainColorInput.focus();mainColorInput.select()},0)
}
function setMainColor(hex){
 hex=normalizeHexColor(hex);
 const textTarget=typeof textInlineTarget==='function'?textInlineTarget():null;
 const appliedToText=!!(textTarget&&typeof applyInlineTextProperty==='function'&&applyInlineTextProperty('color',hex));
 if(fillColor)fillColor.value=hex;if(mainColorInput)mainColorInput.value=rgbText(hex);if(typeof updateMainColorManualPreview==='function')updateMainColorManualPreview();syncMainColorButton();try{localStorage.setItem('printway_dtf_fill_color',hex)}catch(_){}persistUserSettings({fillColor:hex});positionMainColorPalette();
 setStatus(appliedToText?'Cor aplicada ao texto selecionado':'Cor atual: '+rgbLabel(hex))
}
function rememberEyedropperColor(hex){
 hex=normalizeHexColor(hex);const existing=mainColorCustomColors.findIndex(color=>color===hex);
 if(existing>=0){mainColorCustomSelected=existing;saveMainColorCustoms();renderMainColorCustoms();setMainColor(hex);setStatus('Cor capturada. Ela já está na Personalizada '+(existing+1)+'.');return}
 const empty=mainColorCustomColors.findIndex(color=>!color);
 if(empty>=0){mainColorCustomColors[empty]=hex;mainColorCustomSelected=empty;saveMainColorCustoms();renderMainColorCustoms();setMainColor(hex);setStatus('Cor capturada e salva na Personalizada '+(empty+1)+'.');return}
 pendingEyedropperCustomColor=hex;openMainColorPalette();setStatus('Não há espaço para mais cores personalizadas. Clique em um quadro para substituir pela cor capturada.');
}
function applyTypedMainColor(){
 const hex=parseTypedRgbColor(mainColorInput.value);
 if(!hex){setStatus('Digite uma cor RGB válida. Exemplo: 255, 0, 0');mainColorInput.focus();return}
 setMainColor(hex);
}
(function buildMainRgbPalette(){mainColorGrid.appendChild(mainNoColorButton);const fixed=['#000000','#1a1a1a','#333333','#4d4d4d','#666666','#808080','#999999','#b3b3b3','#cccccc','#e6e6e6','#ffffff'];fixed.forEach(hex=>{const item=document.createElement('button');item.type='button';item.className='dtf-rgb-color';item.style.backgroundColor=hex;item.title=rgbLabel(hex);item.setAttribute('aria-label',rgbLabel(hex));item.addEventListener('click',()=>setMainColor(hex));mainColorGrid.appendChild(item)});const levels=[0,51,102,153,204,255];levels.forEach(r=>levels.forEach(g=>levels.forEach(b=>{if(r===g&&g===b)return;const hex=rgbToHex(r,g,b);const item=document.createElement('button');item.type='button';item.className='dtf-rgb-color';item.style.backgroundColor=hex;item.title='RGB '+r+', '+g+', '+b;item.setAttribute('aria-label','RGB '+r+', '+g+', '+b);item.addEventListener('click',()=>setMainColor(hex));mainColorGrid.appendChild(item)})))}());
renderMainColorCustoms();
const historyBlock=Q('.dtf-global-history');if(historyBlock)historyBlock.appendChild(mainColorWrap);
let localFillColor='';try{localFillColor=String(localStorage.getItem('printway_dtf_fill_color')||'')}catch(_){}
const serverFillColor=userSettings&&/^#[0-9a-f]{6}$/i.test(String(userSettings.fillColor||''))?String(userSettings.fillColor):'';
const initialFillColor=serverFillColor||localFillColor;
if(fillColor&&initialFillColor)fillColor.value=normalizeHexColor(initialFillColor);
if(!serverFillColor&&/^#[0-9a-f]{6}$/i.test(localFillColor))persistUserSettings({fillColor:normalizeHexColor(localFillColor)});
syncMainColorButton();
if(fillColor)fillColor.addEventListener('input',()=>{const hex=normalizeHexColor(fillColor.value);syncMainColorButton();try{localStorage.setItem('printway_dtf_fill_color',hex)}catch(_){}persistUserSettings({fillColor:hex})});
mainColorInput.addEventListener('input',updateMainColorManualPreview);
mainColorInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();applyTypedMainColor()}});
mainColorApply.addEventListener('click',applyTypedMainColor);
mainColorButton.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();mainColorPalette.classList.contains('show')?closeMainColorPalette():openMainColorPalette()});
document.addEventListener('pointerdown',event=>{if(!mainColorPalette.classList.contains('show'))return;if(event.target===mainColorButton||mainColorButton.contains(event.target)||mainColorPalette.contains(event.target))return;closeMainColorPalette()},{capture:true});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMainColorPalette()});
window.addEventListener('resize',positionMainColorPalette);window.addEventListener('scroll',positionMainColorPalette,true);

function isLineWidthShape(object){return !!object&&['shape-rectangle','shape-line'].includes(String(object.sourceType||''))}
function selectedLineWidthShapes(){const items=selectedObjects();return items.length&&items.every(isLineWidthShape)?items:[]}
function defaultShapeStrokeWidth(object){const source=object&&(object.baseCanvas||object.canvas),w=source&&source.width||object&&object.originalW||object&&object.w||100,h=source&&source.height||object&&object.originalH||object&&object.h||100;return Math.max(2,Math.round(Math.min(w,h)*.02))}
function effectiveShapeStrokeWidth(object){const raw=object&&object.shapeStrokeWidth,value=Number(raw);return raw!=null&&raw!==''&&Number.isFinite(value)&&value>=0?clamp(value,0,100):defaultShapeStrokeWidth(object)}
let lineWidthPreviewSession=null;
function visibleSelectedObjectRect(){
 if(!selected())return null;
 try{
  if(selection&&selection.classList.contains('show')){
   const r=selection.getBoundingClientRect();
   if(r.width>0&&r.height>0)return {left:r.left,top:r.top,right:r.right,bottom:r.bottom,width:r.width,height:r.height}
  }
 }catch(_){}
 return null;
}
function floatingOverlapArea(x,y,w,h,avoid,margin=8){
 if(!avoid)return 0;
 const left=Math.max(x,avoid.left-margin),top=Math.max(y,avoid.top-margin),right=Math.min(x+w,avoid.right+margin),bottom=Math.min(y+h,avoid.bottom+margin);
 return Math.max(0,right-left)*Math.max(0,bottom-top);
}
function chooseFloatingPosition(anchor,w,h,avoid){
 const pad=8,gap=8,maxX=Math.max(pad,window.innerWidth-w-pad),maxY=Math.max(pad,window.innerHeight-h-pad);
 const clampPoint=(x,y)=>({x:clamp(x,pad,maxX),y:clamp(y,pad,maxY)});
 const candidates=[];
 if(anchor){
  candidates.push({name:'button-below',x:anchor.left,y:anchor.bottom+gap});
  candidates.push({name:'button-below-center',x:anchor.left+(anchor.width-w)/2,y:anchor.bottom+gap});
  candidates.push({name:'button-above',x:anchor.left,y:anchor.top-h-gap});
 }
 if(avoid){
  candidates.push({name:'object-right',x:avoid.right+gap,y:avoid.top+(avoid.height-h)/2});
  candidates.push({name:'object-left',x:avoid.left-w-gap,y:avoid.top+(avoid.height-h)/2});
  candidates.push({name:'object-below',x:avoid.left+(avoid.width-w)/2,y:avoid.bottom+gap});
  candidates.push({name:'object-above',x:avoid.left+(avoid.width-w)/2,y:avoid.top-h-gap});
 }
 candidates.push(
  {name:'screen-tr',x:maxX,y:pad},{name:'screen-tl',x:pad,y:pad},
  {name:'screen-br',x:maxX,y:maxY},{name:'screen-bl',x:pad,y:maxY}
 );
 let best=null;
 candidates.forEach((candidate,index)=>{
  const p=clampPoint(candidate.x,candidate.y),overlap=floatingOverlapArea(p.x,p.y,w,h,avoid,10);
  const score=overlap*100000+index;
  const item={...p,score,name:candidate.name,overlap};
  if(!best||item.score<best.score)best=item;
 });
 return best||{x:pad,y:pad,name:'fallback',overlap:0};
}
function positionLineWidthPalette(){
 if(!lineWidthPalette.classList.contains('show'))return;
 const anchor=lineWidthButton.getBoundingClientRect(),w=lineWidthPalette.offsetWidth||330,h=lineWidthPalette.offsetHeight||250,avoid=visibleSelectedObjectRect(),pos=chooseFloatingPosition(anchor,w,h,avoid);
 lineWidthPalette.style.left=pos.x+'px';lineWidthPalette.style.top=pos.y+'px';
}
function lineWidthSessionItems(session=lineWidthPreviewSession){if(!session)return[];return session.items.map(entry=>({entry,item:state.objects.find(object=>object.id===entry.id)})).filter(pair=>pair.item&&isLineWidthShape(pair.item))}
function repaintLineWidthShape(object,width){if(String(object&&object.sourceType||'')==='shape-line')return repaintLineObject(object,{strokeWidth:width});return repaintRectangleObjectColor(object,null,null,width)}
function restoreLineWidthPreview(session=lineWidthPreviewSession,shouldRender=true){if(!session)return false;lineWidthPreviewSession=null;lineWidthSessionItems(session).forEach(({entry,item})=>repaintLineWidthShape(item,entry.width));if(shouldRender){render();syncLineWidthButton()}return true}
function closeLineWidthPalette(cancelPreview=true){if(cancelPreview&&lineWidthPreviewSession)restoreLineWidthPreview(lineWidthPreviewSession,true);lineWidthPalette.classList.remove('show');lineWidthButton.setAttribute('aria-expanded','false');lineWidthGrid.querySelectorAll('.preview').forEach(item=>item.classList.remove('preview'))}
function syncLineWidthButton(){const items=selectedLineWidthShapes(),enabled=items.length>0;lineWidthButton.disabled=!enabled;lineWidthButton.setAttribute('aria-disabled',String(!enabled));if(enabled){const first=effectiveShapeStrokeWidth(items[0]),same=items.every(item=>Math.abs(effectiveShapeStrokeWidth(item)-first)<.01);lineWidthButton.dataset.value=same?String(first):'';lineWidthButton.title=same?(first===0?'Espessura da linha: Nenhuma':'Espessura da linha: '+first+' px'):'Espessura da linha: valores diferentes';if(lineWidthPalette.classList.contains('show')&&!lineWidthPreviewSession)lineWidthInput.value=same?String(first):''}else{lineWidthButton.dataset.value='';lineWidthButton.title='Espessura da linha — selecione um desenho com borda';closeLineWidthPalette(true)}}
function openLineWidthPalette(){const items=selectedLineWidthShapes();if(!items.length){syncLineWidthButton();setStatus('Selecione uma linha ou retângulo para ajustar a espessura.');return}closeMainColorPalette();if(lineWidthPreviewSession)restoreLineWidthPreview(lineWidthPreviewSession,false);const first=effectiveShapeStrokeWidth(items[0]),same=items.every(item=>Math.abs(effectiveShapeStrokeWidth(item)-first)<.01);lineWidthPreviewSession={items:items.map(item=>({id:item.id,width:effectiveShapeStrokeWidth(item)})),previewWidth:same?first:null};lineWidthInput.value=same?String(first):'';lineWidthPalette.classList.add('show');lineWidthButton.setAttribute('aria-expanded','true');positionLineWidthPalette();setStatus('Pré-visualize a espessura. Clique fora ou em Aplicar para confirmar; ESC cancela.')}
function parseLineWidthValue(value){const parsed=Number(String(value).replace(',','.'));return Number.isFinite(parsed)&&parsed>=0?clamp(parsed,0,100):null}
function markLineWidthPreviewOption(width){lineWidthGrid.querySelectorAll('.dtf-line-width-option').forEach(item=>item.classList.toggle('preview',Number(item.dataset.width)===Number(width)))}
function lineWidthPreviewChanged(session=lineWidthPreviewSession,width=session&&session.previewWidth){
 if(!session||width==null||!Number.isFinite(Number(width)))return false;
 const target=Number(width);return session.items.some(entry=>Math.abs(Number(entry.width)-target)>.001)
}
function previewLineWidth(value){if(!lineWidthPreviewSession)return false;const width=parseLineWidthValue(value);if(width===null)return false;const pairs=lineWidthSessionItems();if(!pairs.length){closeLineWidthPalette(true);return false}pairs.forEach(({item})=>repaintLineWidthShape(item,width));lineWidthPreviewSession.previewWidth=width;lineWidthPreviewSession.dirty=lineWidthPreviewChanged(lineWidthPreviewSession,width);markLineWidthPreviewOption(width);render();positionLineWidthPalette();setStatus(width===0?'Prévia: sem borda. Clique fora ou em Aplicar para confirmar.':'Prévia da borda: '+width+' px. Clique fora ou em Aplicar para confirmar.');return true}
function commitLineWidthPreview(){
 if(!lineWidthPreviewSession)return false;
 const session=lineWidthPreviewSession,typed=parseLineWidthValue(lineWidthInput.value),width=typed!==null?typed:session.previewWidth;
 if(width==null||!Number.isFinite(Number(width))){lineWidthPreviewSession=null;closeLineWidthPalette(false);syncLineWidthButton();return false}
 const pairs=lineWidthSessionItems(session);if(!pairs.length){closeLineWidthPalette(true);return false}
 const changed=lineWidthPreviewChanged(session,width);lineWidthPreviewSession=null;
 if(!changed){pairs.forEach(({entry,item})=>repaintLineWidthShape(item,entry.width));render();syncLineWidthButton();closeLineWidthPalette(false);return true}
 pairs.forEach(({entry,item})=>repaintLineWidthShape(item,entry.width));
 pushHistory(width===0?'Remover borda':'Alterar espessura da linha');
 pairs.forEach(({item})=>repaintLineWidthShape(item,width));
 render();syncLineWidthButton();closeLineWidthPalette(false);setStatus(width===0?'Borda removida do desenho selecionado.':'Espessura da linha aplicada: '+width+' px');return true
}
{const none=document.createElement('button');none.type='button';none.className='dtf-line-width-option dtf-line-width-none';none.dataset.width='0';none.title='Nenhuma — remover borda';none.setAttribute('aria-label','Nenhuma espessura — remover borda');none.innerHTML='<span aria-hidden="true"></span><small>Nenhuma</small>';none.addEventListener('click',()=>{lineWidthInput.value='0';previewLineWidth(0)});lineWidthGrid.appendChild(none)}[1,2,3,4,6,8,10,12,16,20].forEach(value=>{const button=document.createElement('button');button.type='button';button.className='dtf-line-width-option';button.dataset.width=String(value);button.title=value+' px';button.setAttribute('aria-label','Espessura '+value+' pixels');button.innerHTML='<span style="height:'+Math.min(20,value)+'px"></span><small>'+value+'</small>';button.addEventListener('click',()=>{lineWidthInput.value=String(value);previewLineWidth(value)});lineWidthGrid.appendChild(button)});
lineWidthButton.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();if(lineWidthPalette.classList.contains('show')){lineWidthPreviewSession&&lineWidthPreviewSession.dirty?commitLineWidthPreview():(lineWidthPreviewSession=null,closeLineWidthPalette(false))}else openLineWidthPalette()});
lineWidthApply.addEventListener('click',()=>commitLineWidthPreview());
lineWidthInput.addEventListener('input',()=>{const width=parseLineWidthValue(lineWidthInput.value);if(width!==null)previewLineWidth(width)});
lineWidthInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();const width=parseLineWidthValue(lineWidthInput.value);if(width!==null)previewLineWidth(width)}});
document.addEventListener('pointerdown',event=>{if(!lineWidthPalette.classList.contains('show'))return;if(event.target===lineWidthButton||lineWidthButton.contains(event.target)||lineWidthPalette.contains(event.target))return;if(lineWidthPreviewSession&&lineWidthPreviewSession.dirty)commitLineWidthPreview();else{lineWidthPreviewSession=null;closeLineWidthPalette(false)}},{capture:true});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&lineWidthPalette.classList.contains('show')){event.preventDefault();closeLineWidthPalette(true)}});window.addEventListener('resize',positionLineWidthPalette);window.addEventListener('scroll',positionLineWidthPalette,true);

function cssColorToHex(value){
 const input=String(value||'').trim();
 if(!input||input==='transparent'||input==='rgba(0, 0, 0, 0)'||input==='rgba(0,0,0,0)')return '';
 if(input[0]==='#')return normalizeHexColor(input);
 const m=input.match(/rgba?\(([^)]+)\)/i);
 if(!m)return '';
 const parts=m[1].split(',').slice(0,3).map(v=>clamp(parseInt(v,10)||0,0,255));
 if(parts.length<3)return '';
 return rgbToHex(parts[0],parts[1],parts[2]);
}
function selectedRectangleObjects(){return selectedObjects().filter(o=>String(o&&o.sourceType||'')==='shape-rectangle')}
function rectanglePaletteHexFromButton(button){
 if(!button||button.classList.contains('empty'))return '';
 if(button.dataset&&button.dataset.noneColor==='1')return 'none';
 const dataHex=String(button.dataset&&button.dataset.hex||'').trim();
 if(dataHex)return normalizeHexColor(dataHex);
 const styleHex=cssColorToHex(button.style.backgroundColor||'');
 if(styleHex)return styleHex;
 try{return cssColorToHex(getComputedStyle(button).backgroundColor||'')}catch(_){return ''}
}
function shapeColorValue(value,fallback){return String(value||'').toLowerCase()==='none'?'none':normalizeHexColor(value||fallback)}
function shapeColorLabel(value){return String(value||'').toLowerCase()==='none'?'sem cor':rgbLabel(value)}
function normalizeShapeCornerRadii(radii,width,height){
 const w=Math.max(1,Number(width)||1),h=Math.max(1,Number(height)||1),src=radii&&typeof radii==='object'?radii:{};
 const out={tl:Math.max(0,Number(src.tl)||0),tr:Math.max(0,Number(src.tr)||0),br:Math.max(0,Number(src.br)||0),bl:Math.max(0,Number(src.bl)||0)};
 const factors=[1];
 const add=(limit,sum)=>{if(sum>0)factors.push(limit/sum)};
 add(w,out.tl+out.tr);add(w,out.bl+out.br);add(h,out.tl+out.bl);add(h,out.tr+out.br);
 const scale=Math.min(...factors);
 if(scale<1){out.tl*=scale;out.tr*=scale;out.br*=scale;out.bl*=scale}
 return out;
}
function objectShapeCornerRadii(object){
 if(!object)return {tl:0,tr:0,br:0,bl:0};
 const source=object.baseCanvas||object.canvas||object.restoreCanvas;
 return normalizeShapeCornerRadii(object.shapeCornerRadii,(source&&source.width)||object.originalW||object.w||1,(source&&source.height)||object.originalH||object.h||1);
}
function roundedRectPath(ctx,x,y,w,h,radii){
 const r=normalizeShapeCornerRadii(radii,w,h),right=x+w,bottom=y+h;
 ctx.beginPath();
 ctx.moveTo(x+r.tl,y);
 ctx.lineTo(right-r.tr,y);
 if(r.tr)ctx.quadraticCurveTo(right,y,right,y+r.tr);else ctx.lineTo(right,y);
 ctx.lineTo(right,bottom-r.br);
 if(r.br)ctx.quadraticCurveTo(right,bottom,right-r.br,bottom);else ctx.lineTo(right,bottom);
 ctx.lineTo(x+r.bl,bottom);
 if(r.bl)ctx.quadraticCurveTo(x,bottom,x,bottom-r.bl);else ctx.lineTo(x,bottom);
 ctx.lineTo(x,y+r.tl);
 if(r.tl)ctx.quadraticCurveTo(x,y,x+r.tl,y);else ctx.lineTo(x,y);
 ctx.closePath();
 return r;
}
function repaintRectangleObjectColor(object,fillHex,strokeHex,strokeWidth=null){
 if(!object||String(object.sourceType||'')!=='shape-rectangle')return;
 const nextFill=shapeColorValue(fillHex!=null?fillHex:object.shapeFillColor,'#8c3f00');
 const nextStroke=shapeColorValue(strokeHex!=null?strokeHex:object.shapeStrokeColor,'#000000');
 const currentCanvas=object.canvas||object.baseCanvas||object.restoreCanvas;
 const rawStrokeWidth=Number(strokeWidth!=null?strokeWidth:effectiveShapeStrokeWidth(object)),nextStrokeWidth=Number.isFinite(rawStrokeWidth)?clamp(rawStrokeWidth,0,100):defaultShapeStrokeWidth(object);
 const drawCanvas=(source)=>rectangleCanvas((source&&source.width)||Math.max(1,Math.round(object.originalW||object.w||1)),(source&&source.height)||Math.max(1,Math.round(object.originalH||object.h||1)),nextFill,nextStroke,nextStrokeWidth,object.shapeCornerRadii);
 object.canvas=drawCanvas(currentCanvas);
 object.baseCanvas=drawCanvas(object.baseCanvas||currentCanvas);
 object.restoreCanvas=drawCanvas(object.restoreCanvas||object.baseCanvas||currentCanvas);
 object.shapeFillColor=nextFill;object.shapeStrokeColor=nextStroke;object.shapeStrokeWidth=nextStrokeWidth;
 object.originalW=object.baseCanvas.width;object.originalH=object.baseCanvas.height;
}
function isLineShape(object){return !!object&&String(object.sourceType||'')==='shape-line'}
function lineGlobalNodes(object){return (Array.isArray(object&&object.lineNodes)?object.lineNodes:[]).map(point=>({x:(Number(object.x)||0)+(Number(point.x)||0),y:(Number(object.y)||0)+(Number(point.y)||0)}))}
function lineCanvasFromNodes(width,height,nodes,stroke,widthPx,closed=false,fill='none'){const c=document.createElement('canvas'),w=Math.max(1,Math.ceil(width)),h=Math.max(1,Math.ceil(height));c.width=w;c.height=h;const c2=c.getContext('2d');if(nodes.length>1){c2.beginPath();c2.moveTo(nodes[0].x,nodes[0].y);nodes.slice(1).forEach(point=>c2.lineTo(point.x,point.y));if(closed)c2.closePath();if(closed&&fill!=='none'){c2.fillStyle=fill;c2.fill()}if(stroke!=='none'&&widthPx>0){c2.strokeStyle=stroke;c2.lineWidth=widthPx;c2.lineCap='round';c2.lineJoin='round';c2.stroke()}}return c}
function rebuildLineObject(object,globalNodes){if(!object||!Array.isArray(globalNodes)||globalNodes.length<2)return false;const closed=object.lineClosed===true,fill=closed?shapeColorValue(object.shapeFillColor,'none'):'none',stroke=shapeColorValue(object.shapeStrokeColor,'#000000'),strokeWidth=effectiveShapeStrokeWidth(object),pad=Math.max(3,strokeWidth/2+2),xs=globalNodes.map(point=>Number(point.x)||0),ys=globalNodes.map(point=>Number(point.y)||0),minX=Math.min(...xs)-pad,minY=Math.min(...ys)-pad,maxX=Math.max(...xs)+pad,maxY=Math.max(...ys)+pad,w=Math.max(1,maxX-minX),h=Math.max(1,maxY-minY),nodes=globalNodes.map(point=>({x:(Number(point.x)||0)-minX,y:(Number(point.y)||0)-minY})),fresh=lineCanvasFromNodes(w,h,nodes,stroke,strokeWidth,closed,fill);object.x=minX;object.y=minY;object.w=w;object.h=h;object.lineNodes=nodes;object.canvas=fresh;object.baseCanvas=canvasFromData(cloneCanvasData(fresh));object.restoreCanvas=canvasFromData(cloneCanvasData(fresh));object.originalW=fresh.width;object.originalH=fresh.height;object.shapeFillColor=closed?fill:'none';object.shapeStrokeColor=stroke;object.shapeStrokeWidth=strokeWidth;return true}
function repaintLineObject(object,options={}){if(!isLineShape(object))return false;if(options.strokeColor!=null)object.shapeStrokeColor=shapeColorValue(options.strokeColor,'#000000');if(options.fillColor!=null&&object.lineClosed===true)object.shapeFillColor=shapeColorValue(options.fillColor,'none');if(options.strokeWidth!=null)object.shapeStrokeWidth=clamp(Number(options.strokeWidth)||0,0,100);return rebuildLineObject(object,lineGlobalNodes(object))}
function applyPaletteColorToSelectedRectangles(hex,mode){
 const rectangles=selectedObjects().filter(item=>['shape-rectangle','shape-circle','shape-triangle','shape-pentagon','shape-star','shape-diamond','shape-heart','shape-arrow','shape-speech','shape-line'].includes(String(item&&item.sourceType||'')));if(!rectangles.length)return false;
 const normalized=String(hex||'').toLowerCase()==='none'?'none':normalizeHexColor(hex),targets=mode==='fill'?rectangles.filter(item=>!isLineShape(item)||item.lineClosed===true):rectangles;
 if(!targets.length){setStatus('Linha aberta não possui interior para preencher. Feche o traço unindo o último nó ao primeiro.');return true}
 pushHistory(mode==='fill'?'Alterar preenchimento da forma':'Alterar borda da forma');
 targets.forEach(item=>{if(isLineShape(item))repaintLineObject(item,mode==='fill'?{fillColor:normalized}:{strokeColor:normalized});else if(String(item.sourceType||'')==='shape-rectangle')repaintRectangleObjectColor(item,mode==='fill'?normalized:null,mode==='stroke'?normalized:null);else repaintPolygonShapeColor(item,mode==='fill'?normalized:null,mode==='stroke'?normalized:null)});
 if(normalized!=='none')setMainColor(normalized);else positionMainColorPalette();
 render();
 const skipped=mode==='fill'&&targets.length<rectangles.length?' (linhas abertas ignoradas)':'';
 setStatus(targets.every(isLineShape)?(mode==='fill'?(normalized==='none'?'Preenchimento removido da linha fechada':'Preenchimento aplicado ao interior da linha fechada'):(normalized==='none'?'Borda removida da linha':'Borda aplicada à linha'))+skipped:(mode==='fill'?(normalized==='none'?'Preenchimento removido da forma selecionada':'Preenchimento aplicado à forma selecionada'):(normalized==='none'?'Borda removida da forma selecionada':'Borda aplicada à forma selecionada'))+skipped);
 return true;
}
[mainColorGrid,mainColorCustomGrid].forEach(grid=>{
 if(!grid)return;
 grid.addEventListener('click',event=>{
  const button=event.target&&event.target.closest?event.target.closest('button'):null;
  if(!button)return;
  const hex=rectanglePaletteHexFromButton(button);
  if(!hex)return;
  if(applyPaletteColorToSelectedRectangles(hex,'fill')){event.preventDefault();event.stopImmediatePropagation();event.stopPropagation()}
 },true);
 grid.addEventListener('contextmenu',event=>{
  const button=event.target&&event.target.closest?event.target.closest('button'):null;
  if(!button)return;
  const hex=rectanglePaletteHexFromButton(button);
  if(!hex)return;
  if(applyPaletteColorToSelectedRectangles(hex,'stroke')){event.preventDefault();event.stopImmediatePropagation();event.stopPropagation()}
 },true);
});
mainColorButton.addEventListener('contextmenu',event=>{
 event.preventDefault();event.stopPropagation();
 mainColorPalette.classList.contains('show')?closeMainColorPalette():openMainColorPalette();
},false);

function dtfMakeToolButton(id,label,title,iconHtml){
 const b=document.createElement('button');b.className='dtf-tool';b.id=id;b.type='button';b.title=title||label;b.setAttribute('aria-label',title||label);b.setAttribute('aria-pressed','false');b.innerHTML='<span class="dtf-icon" aria-hidden="true">'+iconHtml+'</span><span>'+label+'</span>';return b;
}
const cursorSelectButton=dtfMakeToolButton('dtfSelectCursorTool','Selecionar','Cursor padrão / ferramenta de seleção','<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5.2 3.5 19 12.4l-6.9 1.4 3.4 6-2.6 1.5-3.4-6-4.3 5.2V3.5Z" fill="currentColor" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round"/><path d="m7.4 7.2 4.6 4.7" stroke="#fff" stroke-width="1.15" stroke-linecap="round" opacity=".72"/></svg>');
const eyedropperButton=dtfMakeToolButton('dtfEyedropperTool','Conta-gotas','Conta-gotas permanente','<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14.8 2.2c1.1-1.1 2.9-1.1 4 0l3 3c1.1 1.1 1.1 2.9 0 4l-6.3 6.3-1.7-1.7-5.7 5.7c-.8.8-1.7 1.3-2.8 1.6L2 22l.9-3.3c.3-1.1.8-2 1.6-2.8l5.7-5.7-1.7-1.7 6.3-6.3Zm-3 9.6-5.6 5.6c-.5.5-.8 1-.9 1.6l-.2.8.8-.2c.6-.2 1.1-.5 1.6-.9l5.6-5.6-1.3-1.3Zm4.7-7.9-4.6 4.6 3.6 3.6 4.6-4.6c.2-.2.2-.5 0-.7l-3-3c-.2-.2-.5-.2-.6.1Z"/></svg>');
const replaceColorButton=dtfMakeToolButton('dtfReplaceColorTool','Substituir cor','Substituir cor pela cor atual','⇄');
const sharpenButton=dtfMakeToolButton('dtfSharpenTool','Nitidez','Aplicar nitidez/realce','✦');
const visualHistoryButton=dtfMakeToolButton('dtfVisualHistory','Histórico visual','Histórico visual com miniaturas','▦');
const magnifierButton=dtfMakeToolButton('dtfMagnifierTool','Lupa','Zoom por lupa perto do mouse','⌕');
const preflightButton=$id('dtfExportPreflight')||dtfMakeToolButton('dtfPreflightTool','dtfExportPreflight','Pré-verificação','Pré-verificação de exportação','✓');
const actionsToolsPanel=Q('.dtf-edit-actions-group .dtf-tools');
if(mainColorWrap){mainColorWrap.append(eyedropperButton,cursorToolSeparator,cursorSelectButton);}
if(actionsToolsPanel){actionsToolsPanel.append(replaceColorButton,sharpenButton)}
const zoomToolsPanel=ui.zoomFit&&ui.zoomFit.parentElement;
if(zoomToolsPanel)zoomToolsPanel.appendChild(magnifierButton);
function hexToRgb(hex){hex=normalizeHexColor(hex);return {r:parseInt(hex.slice(1,3),16),g:parseInt(hex.slice(3,5),16),b:parseInt(hex.slice(5,7),16)}}
function rgbToHex(r,g,b){return '#'+[r,g,b].map(v=>clamp(Math.round(v),0,255).toString(16).padStart(2,'0')).join('')}
function pixelRgbDistance(a,r,g,b){const dr=a[0]-r,dg=a[1]-g,db=a[2]-b;return Math.sqrt(dr*dr+dg*dg+db*db)}
function canvasPixelAtObject(o,wx,wy){const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height);if(lx<0||ly<0||lx>=o.canvas.width||ly>=o.canvas.height)return null;const d=o.canvas.getContext('2d',{willReadFrequently:true}).getImageData(lx,ly,1,1).data;return {r:d[0],g:d[1],b:d[2],a:d[3],x:lx,y:ly}}
function replaceSimilarColor(o,sourceRgb,targetRgb,tolerance){
 const c=o.canvas,cc=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height,img=cc.getImageData(0,0,w,h),a=img.data,limit=12+(Number(tolerance)||20)*2.1;let changed=0;
 for(let j=0;j<a.length;j+=4){if(a[j+3]<8)continue;if(pixelRgbDistance(a.slice(j,j+3),sourceRgb.r,sourceRgb.g,sourceRgb.b)<=limit){a[j]=targetRgb.r;a[j+1]=targetRgb.g;a[j+2]=targetRgb.b;changed++}}
 if(changed){cc.putImageData(img,0,0);clearDisplayPreview(o);refreshRestoreCanvas(o)}return changed;
}
function sharpenCanvas(o){
 const c=o.canvas,cc=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height,src=cc.getImageData(0,0,w,h),out=cc.createImageData(w,h),s=src.data,d=out.data,k=[0,-1,0,-1,5,-1,0,-1,0];
 const px=(x,y,ch)=>s[((clamp(x,0,w-1)|0)+(clamp(y,0,h-1)|0)*w)*4+ch];
 for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;for(let ch=0;ch<3;ch++){let v=0,n=0;for(let yy=-1;yy<=1;yy++)for(let xx=-1;xx<=1;xx++)v+=px(x+xx,y+yy,ch)*k[n++];d[i+ch]=clamp(v,0,255)}d[i+3]=s[i+3]}
 cc.putImageData(out,0,0);clearDisplayPreview(o);refreshRestoreCanvas(o);
}
function setCustomTool(button,tool,label){
 if(state.tool===tool){deactivateSpecialTool();setCursorSelectButtonActive(true);return false}
 setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();state.tool=tool;[eyedropperButton,replaceColorButton].forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',String(b===button))});editorRoot.classList.toggle('dtf-eyedropper-cursor',tool==='eyedropper');editorRoot.classList.toggle('dtf-replace-color-cursor',tool==='replaceColor');setStatus(label);showToolHint(label);return true;
}
const oldDeactivateSpecialTool=deactivateSpecialTool;
deactivateSpecialTool=function(quiet=false){
 const customActive=state.tool==='eyedropper'||state.tool==='replaceColor';
 if(customActive){state.tool='select';[eyedropperButton,replaceColorButton].forEach(b=>{b.classList.remove('active');b.setAttribute('aria-pressed','false')});editorRoot.classList.remove('dtf-eyedropper-cursor','dtf-replace-color-cursor');hideColorCursorPreview();if(!quiet)setStatus('Ferramenta desativada');return}
 oldDeactivateSpecialTool(quiet);[eyedropperButton,replaceColorButton].forEach(b=>{b.classList.remove('active');b.setAttribute('aria-pressed','false')});editorRoot.classList.remove('dtf-eyedropper-cursor','dtf-replace-color-cursor');
};
function setCursorSelectButtonActive(active){
 if(cursorSelectButton){cursorSelectButton.classList.toggle('active',!!active);cursorSelectButton.setAttribute('aria-pressed',String(!!active))}
}
function resetToDefaultCursor(quiet=false){
 if(typeof closeMainColorPalette==='function')closeMainColorPalette();
 if(typeof cancelOrderTargetPick==='function')try{cancelOrderTargetPick()}catch(_){}
 if(typeof deactivateDrawTool==='function')deactivateDrawTool(true);
 deactivateSpecialTool(true);
 deactivateBrush();
 state.tool='select';
 editorRoot.classList.remove('dtf-color-remove-cursor','dtf-area-remove-cursor','dtf-eyedropper-cursor','dtf-replace-color-cursor','dtf-draw-rect-cursor','dtf-draw-text-cursor','dtf-brush-measure-active','dtf-order-pick-cursor');
 eraserCursor.classList.remove('show','dtf-area-cursor');
 if(typeof hideColorCursorPreview==='function')hideColorCursorPreview();
 if(typeof hideDrawRectMeasure==='function')hideDrawRectMeasure();
 if(typeof hideMagnifier==='function')hideMagnifier();
 [ui.reset,ui.eraser,ui.restoreBrush,fillTool,clickRemove,areaRemoveButton,outerAreaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});
 if(typeof eyedropperButton!=='undefined'&&eyedropperButton){eyedropperButton.classList.remove('active');eyedropperButton.setAttribute('aria-pressed','false')}
 if(typeof replaceColorButton!=='undefined'&&replaceColorButton){replaceColorButton.classList.remove('active');replaceColorButton.setAttribute('aria-pressed','false')}
 if(typeof drawRectangleButton!=='undefined'&&drawRectangleButton){drawRectangleButton.classList.remove('active');drawRectangleButton.setAttribute('aria-pressed','false')}
 if(typeof drawTextButton!=='undefined'&&drawTextButton){drawTextButton.classList.remove('active');drawTextButton.setAttribute('aria-pressed','false')}
 setCursorSelectButtonActive(true);
 if(!quiet)setStatus('Cursor padrão ativo');
}
if(cursorSelectButton){setCursorSelectButtonActive(true);cursorSelectButton.addEventListener('click',()=>resetToDefaultCursor(false))}

eyedropperButton.addEventListener('click',()=>setCustomTool(eyedropperButton,'eyedropper','Conta-gotas ativo: clique em uma cor da imagem.'));
replaceColorButton.addEventListener('click',()=>{if(replaceColorButton.disabled){setStatus('Selecione uma imagem para usar esta ferramenta.');return}if(setCustomTool(replaceColorButton,'replaceColor','Substituir cor: clique na cor que será trocada pela cor atual.'))setRemovalOptionControls('tolerance')});
sharpenButton.addEventListener('click',()=>{const items=selectedObjects();if(!items.length){setStatus('Selecione um objeto primeiro');return}pushHistory('Antes de aplicar nitidez');items.forEach(sharpenCanvas);render();setStatus('Nitidez aplicada em '+items.length+' objeto'+(items.length===1?'':'s'))});
viewport.addEventListener('pointermove',e=>{if(state.tool==='eyedropper'||state.tool==='replaceColor')updateColorCursorPreview(e)},{capture:true});
viewport.addEventListener('pointerdown',e=>{if(state.tool!=='eyedropper'&&state.tool!=='replaceColor')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit)return;e.preventDefault();e.stopImmediatePropagation();const pix=canvasPixelAtObject(hit,p.x,p.y);if(!pix||pix.a<8){setStatus('Aponte para uma área com cor visível');return}const hex=rgbToHex(pix.r,pix.g,pix.b);if(state.tool==='eyedropper'){rememberEyedropperColor(hex);showToolHint('Cor capturada: '+rgbLabel(hex));return}const targets=state.selectedIds.includes(hit.id)?selectedObjects():[hit],targetRgb=hexToRgb(fillColor&&fillColor.value?fillColor.value:'#8c3f00');pushHistory('Antes de substituir cor');const total=targets.reduce((sum,o)=>sum+replaceSimilarColor(o,{r:pix.r,g:pix.g,b:pix.b},targetRgb,Number(ui.tol&&ui.tol.value)||20),0);render();setStatus(total?'Cor substituída: '+total+' pixels':'Nenhuma cor semelhante encontrada')},{capture:true});
const visualHistoryPanel=document.createElement('div');visualHistoryPanel.className='dtf-visual-history-panel';visualHistoryPanel.setAttribute('role','dialog');visualHistoryPanel.setAttribute('aria-label','Histórico visual');document.body.appendChild(visualHistoryPanel);
function snapshotCanvasPreview(snapshot,w=132,h=82){const c=document.createElement('canvas');c.width=w;c.height=h;const cctx=c.getContext('2d'),scale=Math.min(w/(snapshot.dpi*snapshot.workWmm/25.4),h/(snapshot.dpi*snapshot.workHmm/25.4));cctx.fillStyle=snapshot.transparent?'#f5f5f5':(snapshot.bgColor||'#fff');cctx.fillRect(0,0,w,h);cctx.save();cctx.scale(scale,scale);(snapshot.objects||[]).forEach(o=>{if(o.visible===false||!o.canvas)return;try{const oc=canvasFromData(o.canvas);cctx.drawImage(oc,0,0,oc.width,oc.height,o.x,o.y,o.w,o.h)}catch(_){}});cctx.restore();return c}
function closeVisualHistory(){visualHistoryPanel.classList.remove('show')}
function positionVisualHistory(){if(!visualHistoryPanel.classList.contains('show'))return;const r=visualHistoryButton.getBoundingClientRect();visualHistoryPanel.style.left=Math.max(8,Math.min(r.left,window.innerWidth-visualHistoryPanel.offsetWidth-8))+'px';visualHistoryPanel.style.top=Math.max(8,Math.min(r.bottom+8,window.innerHeight-visualHistoryPanel.offsetHeight-8))+'px'}
function openVisualHistory(){visualHistoryPanel.innerHTML='<div class="dtf-visual-history-title">Histórico visual</div>';const list=document.createElement('div');list.className='dtf-visual-history-list';const entries=state.undo.map((s,i)=>({label:s.label||('Etapa '+(i+1)),snapshot:s,index:i})).concat([{label:'Atual',snapshot:projectSnapshot(),index:-1,current:true}]);entries.reverse().forEach(entry=>{const item=document.createElement('button');item.type='button';item.className='dtf-visual-history-item';item.appendChild(snapshotCanvasPreview(entry.snapshot));const label=document.createElement('span');label.textContent=entry.current?'Atual':entry.label;item.appendChild(label);item.addEventListener('click',()=>{if(entry.current){closeVisualHistory();return}pushHistory('Antes de restaurar histórico visual');restoreProject(entry.snapshot);closeVisualHistory();setStatus('Histórico visual restaurado: '+entry.label)});list.appendChild(item)});visualHistoryPanel.appendChild(list);visualHistoryPanel.classList.add('show');positionVisualHistory()}
if(visualHistoryButton)visualHistoryButton.addEventListener('click',e=>{e.preventDefault();visualHistoryPanel.classList.contains('show')?closeVisualHistory():openVisualHistory()});
const magnifierCanvas=document.createElement('canvas');magnifierCanvas.className='dtf-magnifier-canvas';magnifierCanvas.width=150;magnifierCanvas.height=150;document.body.appendChild(magnifierCanvas);let magnifierActive=false;
function hideMagnifier(){magnifierCanvas.classList.remove('show')}
function deactivateMagnifier(quiet=false){const wasActive=magnifierActive||magnifierButton.classList.contains('active');magnifierActive=false;magnifierButton.classList.remove('active');magnifierButton.setAttribute('aria-pressed','false');hideMagnifier();if(wasActive&&!quiet)setStatus('Lupa desativada')}
function updateMagnifier(e){if(!magnifierActive||!e){hideMagnifier();return}const p=clientToWorkspace(e);if(!p){hideMagnifier();return}const size=38,ctxm=magnifierCanvas.getContext('2d');ctxm.imageSmoothingEnabled=false;ctxm.clearRect(0,0,magnifierCanvas.width,magnifierCanvas.height);ctxm.drawImage(canvas,clamp(p.x-size/2,0,canvas.width-size),clamp(p.y-size/2,0,canvas.height-size),size,size,0,0,magnifierCanvas.width,magnifierCanvas.height);ctxm.strokeStyle='rgba(0,0,0,.65)';ctxm.beginPath();ctxm.moveTo(75,0);ctxm.lineTo(75,150);ctxm.moveTo(0,75);ctxm.lineTo(150,75);ctxm.stroke();magnifierCanvas.style.left=(e.clientX+22)+'px';magnifierCanvas.style.top=(e.clientY+22)+'px';magnifierCanvas.classList.add('show')}
magnifierButton.addEventListener('click',()=>{magnifierActive=!magnifierActive;magnifierButton.classList.toggle('active',magnifierActive);magnifierButton.setAttribute('aria-pressed',String(magnifierActive));setStatus(magnifierActive?'Lupa ativada':'Lupa desativada');if(!magnifierActive)hideMagnifier()});
viewport.addEventListener('pointermove',updateMagnifier,{capture:true});viewport.addEventListener('pointerleave',hideMagnifier,{capture:true});
const preflightPanel=document.createElement('div');preflightPanel.className='dtf-preflight-panel';preflightPanel.setAttribute('role','dialog');preflightPanel.setAttribute('aria-label','Pré-verificação de exportação');document.body.appendChild(preflightPanel);
function closePreflight(){preflightPanel.classList.remove('show');const inline=$id('dtfExportPreflightResult');if(inline){inline.hidden=true;inline.classList.remove('show')}}
function runPreflight(){const warnings=[];if(!state.objects.length)warnings.push('Nenhum objeto na área de trabalho.');state.objects.forEach((o,i)=>{const name=o.name||('Objeto '+(i+1));if(!o.visible)warnings.push(name+' está oculto.');if(o.x<0||o.y<0||o.x+o.w>canvas.width||o.y+o.h>canvas.height)warnings.push(name+' está fora da área de trabalho.');if(o.canvas&&(o.canvas.width<o.w*.85||o.canvas.height<o.h*.85))warnings.push(name+' pode perder qualidade: imagem ampliada acima da resolução original.');if(o.opacity!==undefined&&o.opacity<1)warnings.push(name+' tem opacidade menor que 100%.')});if(state.transparent&&exportFormat==='jpeg')warnings.push('JPEG não preserva transparência; o fundo será sólido.');return warnings}
function openPreflight(){
 const warnings=runPreflight(),inline=$id('dtfExportPreflightResult');
 const fillBody=(body)=>{
  if(!warnings.length){body.innerHTML='<p class="dtf-preflight-ok">Tudo certo para exportar.</p>';return}
  const ul=document.createElement('ul');warnings.forEach(w=>{const li=document.createElement('li');li.textContent=w;ul.appendChild(li)});body.innerHTML='';body.appendChild(ul);
 };
 if(inline){
  inline.innerHTML='<div class="dtf-preflight-title">Pré-verificação de exportação</div><div class="dtf-preflight-body"></div>';
  fillBody(inline.querySelector('.dtf-preflight-body'));
  inline.hidden=false;inline.classList.add('show');
  setStatus(warnings.length?warnings.length+' aviso(s) na pré-verificação':'Pré-verificação sem avisos');
  return;
 }
 preflightPanel.innerHTML='<div class="dtf-preflight-title">Pré-verificação de exportação</div><div class="dtf-preflight-body"></div><button type="button" class="dtf-preflight-close">Fechar</button>';
 fillBody(preflightPanel.querySelector('.dtf-preflight-body'));
 preflightPanel.querySelector('.dtf-preflight-close').addEventListener('click',closePreflight);
 preflightPanel.classList.add('show');const r=preflightButton.getBoundingClientRect();
 preflightPanel.style.left=Math.max(8,Math.min(r.left,window.innerWidth-preflightPanel.offsetWidth-8))+'px';
 preflightPanel.style.top=Math.max(8,Math.min(r.bottom+8,window.innerHeight-preflightPanel.offsetHeight-8))+'px';
 setStatus(warnings.length?warnings.length+' aviso(s) na pré-verificação':'Pré-verificação sem avisos')
}
preflightButton.addEventListener('click',openPreflight);
document.addEventListener('pointerdown',e=>{if(visualHistoryButton&&visualHistoryPanel.classList.contains('show')&&!visualHistoryPanel.contains(e.target)&&e.target!==visualHistoryButton&&!visualHistoryButton.contains(e.target))closeVisualHistory();if(preflightPanel.classList.contains('show')&&!preflightPanel.contains(e.target)&&e.target!==preflightButton&&!preflightButton.contains(e.target))closePreflight()},{capture:true});
window.addEventListener('resize',()=>{positionVisualHistory();if(preflightPanel.classList.contains('show'))openPreflight()});
const toolHint=document.createElement('div');toolHint.className='dtf-tool-hint';toolHint.setAttribute('role','status');toolHint.setAttribute('aria-live','polite');document.body.appendChild(toolHint);let toolHintTimer=0,toolHintCountdown=0,toolHintSwapTimer=0,toolHintPending='';
let transientMessagesSuspended=false,transientStatusStyle=null,transientToolHintWasVisible=false;
const transientSurfaceSelector='[role="menu"],[role="dialog"],[role="tooltip"],.dtf-canvas-menu,.dtf-paper-picker-menu,.dtf-drawing-library-modal,.dtf-properties-modal,.dtf-properties-menu,.dtf-submenu';
function renderedTransientSurface(element){if(!element||element===toolHint||element===statusEl||element.hidden)return false;const style=getComputedStyle(element);return style.display!=='none'&&style.visibility!=='hidden'&&style.opacity!=='0'&&element.getClientRects().length>0}
function hasOpenTransientSurface(){return Array.from(document.querySelectorAll(transientSurfaceSelector)).some(renderedTransientSurface)}
function syncTransientMessageVisibility(){const open=hasOpenTransientSurface();if(open&&!transientMessagesSuspended){transientMessagesSuspended=true;transientStatusStyle=statusEl?{visibility:statusEl.style.visibility,opacity:statusEl.style.opacity}:null;transientToolHintWasVisible=toolHint.classList.contains('show');if(statusEl){statusEl.style.visibility='hidden';statusEl.style.opacity='0'}toolHint.classList.remove('show')}else if(!open&&transientMessagesSuspended){transientMessagesSuspended=false;if(statusEl&&transientStatusStyle){statusEl.style.visibility=transientStatusStyle.visibility;statusEl.style.opacity=transientStatusStyle.opacity;statusEl.classList.remove('dtf-status-fade-out');if(statusEl.textContent)statusEl.classList.add('dtf-status-fade-in')}if(transientToolHintWasVisible)toolHint.classList.add('show');transientStatusStyle=null;transientToolHintWasVisible=false}}
const transientSurfaceObserver=new MutationObserver(syncTransientMessageVisibility);transientSurfaceObserver.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class','hidden','style','aria-expanded']});
requestAnimationFrame(syncTransientMessageVisibility);
function positionToolHint(){
 if(!toolHint.classList.contains('show'))return;
 const r=viewport.getBoundingClientRect(),ribbon=editorRoot&&editorRoot.querySelector('.dtf-ribbon'),rr=ribbon&&ribbon.getBoundingClientRect(),bandTop=rr?rr.bottom:Math.max(0,r.top-20),bandHeight=Math.max(0,r.top-bandTop),left=Math.max(8,r.left+32),width=Math.max(180,r.width-32),height=toolHint.offsetHeight;
 toolHint.style.left=left+'px';toolHint.style.right='auto';toolHint.style.width=width+'px';toolHint.style.top=Math.max(0,bandTop+Math.max(0,(bandHeight-height)/2))+'px';
}
function renderToolHintMessage(message,seconds){
 if(toolHintTimer){clearTimeout(toolHintTimer);toolHintTimer=0}if(toolHintCountdown){clearInterval(toolHintCountdown);toolHintCountdown=0}
 const label=document.createElement('span'),countdown=document.createElement('small');label.className='dtf-tool-hint-message';label.textContent=message;countdown.className='dtf-tool-hint-countdown';toolHint.replaceChildren(label,countdown);
 const finishAt=Date.now()+seconds*1000,update=()=>{const remaining=Math.max(0,Math.ceil((finishAt-Date.now())/1000));countdown.textContent=remaining+' s';if(!remaining&&toolHintCountdown){clearInterval(toolHintCountdown);toolHintCountdown=0}};
 update();toolHint.classList.remove('show');void toolHint.offsetWidth;toolHint.classList.add('show');positionToolHint();toolHintCountdown=window.setInterval(update,220);
 toolHintTimer=window.setTimeout(()=>{toolHint.classList.remove('show');if(toolHintCountdown){clearInterval(toolHintCountdown);toolHintCountdown=0}toolHintTimer=0},seconds*1000);
}
function showToolHint(text){
 if(!text)return;
 const message=String(text).trim(),words=message.split(/\s+/).filter(Boolean).length,seconds=clamp(Math.ceil(words/2.35)+1,2,12),current=(toolHint.querySelector('.dtf-tool-hint-message')||{}).textContent||'';
 if(toolHintSwapTimer){toolHintPending=message;return}
 if(toolHint.classList.contains('show')&&current!==message){
  toolHintPending=message;toolHint.classList.remove('show');if(toolHintTimer){clearTimeout(toolHintTimer);toolHintTimer=0}if(toolHintCountdown){clearInterval(toolHintCountdown);toolHintCountdown=0}
  toolHintSwapTimer=window.setTimeout(()=>{const next=toolHintPending||message;toolHintPending='';toolHintSwapTimer=0;const nextWords=next.split(/\s+/).filter(Boolean).length;renderToolHintMessage(next,clamp(Math.ceil(nextWords/2.35)+1,2,12))},280);return;
 }
 renderToolHintMessage(message,seconds);
}
let disabledHoverControl=null;
function disabledControlMessage(control){
 if(!control)return '';
 const id=String(control.id||'');
 if(['dtfReset','dtfAreaRemove','dtfColorRemove','dtfColorAreaRemove','dtfMagicFill','dtfEraser','dtfRestoreBrush','dtfFillTool'].includes(id))return 'Selecione uma imagem primeiro para habilitar esta ferramenta.';
 if(id==='dtfCornerRadius')return 'Selecione uma forma para ajustar os cantos.';
 if(id==='dtfLineWidthButton')return 'Selecione uma forma com borda para ajustar a espessura.';
 if(['dtfDuplicate','dtfDelete','dtfCenterObject','dtfCompare'].includes(id))return 'Selecione um objeto primeiro para habilitar esta ferramenta.';
 if(id==='dtfUndo')return 'Não há alterações para desfazer.';
 if(id==='dtfRedo')return 'Não há alterações para refazer.';
 if(control.closest('.dtf-image-option-tools'))return 'Selecione uma imagem primeiro para habilitar esta ferramenta.';
 if(control.closest('.dtf-align-group,.dtf-order-group,.dtf-object-group'))return 'Selecione um objeto primeiro para habilitar esta ferramenta.';
 return 'Selecione um objeto ou imagem para habilitar esta ferramenta.';
}
function inspectDisabledHover(event){
 const target=document.elementFromPoint(event.clientX,event.clientY),control=target&&target.closest?target.closest('button:disabled,input:disabled,select:disabled,[aria-disabled="true"]'):null;
 if(!control||!editorRoot.contains(control)||control.hidden||getComputedStyle(control).display==='none'){
  disabledHoverControl=null;return;
 }
 if(control===disabledHoverControl)return;
 const message=disabledControlMessage(control);if(!message)return;
 disabledHoverControl=control;setStatus(message);showToolHint(message);
}
// A captura pelo document permite explicar controles realmente desabilitados;
// botões disabled normalmente não propagam click/mouseover por conta própria.
document.addEventListener('pointermove',inspectDisabledHover,{passive:true});
window.addEventListener('resize',positionToolHint);window.addEventListener('scroll',positionToolHint,true);
const lockMenu=document.createElement('button');lockMenu.type='button';lockMenu.id='dtfLockObject';lockMenu.textContent='Bloquear objeto';const canvasMenu=$id('dtfCanvasMenu'),guideDeleteMenu=$id('dtfDeleteGuide');if(canvasMenu)canvasMenu.appendChild(lockMenu);
const inheritMenu=document.createElement('div');inheritMenu.className='dtf-properties-menu';const inheritBtn=document.createElement('button');inheritBtn.type='button';inheritBtn.textContent='Herança ▸';const inheritSub=document.createElement('div');inheritSub.className='dtf-properties-submenu';const inheritCopy=document.createElement('button');inheritCopy.type='button';inheritCopy.textContent='Copiar';const inheritPaste=document.createElement('button');inheritPaste.type='button';inheritPaste.textContent='Colar';inheritPaste.disabled=true;inheritSub.append(inheritCopy,inheritPaste);inheritMenu.append(inheritBtn,inheritSub);if(canvasMenu)canvasMenu.appendChild(inheritMenu);inheritCopy.onclick=()=>{const o=selected();if(o){propertyClipboard={w:o.w,h:o.h,opacity:o.opacity,locked:o.locked};inheritPaste.disabled=false;canvasMenu.classList.remove('show')}};inheritPaste.onclick=()=>{const o=selected();if(o&&propertyClipboard){pushHistory('Colar herança');o.w=Math.min(canvas.width,propertyClipboard.w);o.h=Math.min(canvas.height,propertyClipboard.h);o.opacity=propertyClipboard.opacity;o.locked=propertyClipboard.locked;render();canvasMenu.classList.remove('show')}};
const flipHOld=$id('dtfFlipH'),flipVOld=$id('dtfFlipV');if(flipHOld&&flipVOld&&canvasMenu){flipHOld.style.display='none';flipVOld.style.display='none';const inv=document.createElement('div');inv.className='dtf-invert-menu';const invBtn=document.createElement('button');invBtn.type='button';invBtn.textContent='Inverter ▸';const sub=document.createElement('div');sub.className='dtf-invert-submenu';const bh=document.createElement('button');bh.type='button';bh.textContent='Horizontal';const bv=document.createElement('button');bv.type='button';bv.textContent='Vertical';sub.append(bh,bv);inv.append(invBtn,sub);canvasMenu.appendChild(inv);bh.addEventListener('click',()=>{flipSelected(true);canvasMenu.classList.remove('show')});bv.addEventListener('click',()=>{flipSelected(false);canvasMenu.classList.remove('show')})}

const orderMenu=document.createElement('div'),orderBtn=document.createElement('button'),orderSub=document.createElement('div');
orderMenu.className='dtf-order-menu';orderBtn.type='button';orderBtn.textContent='Ordenar ▸';orderSub.className='dtf-order-submenu';
const makeOrderItem=(id,label,shortcut,action)=>{const btn=document.createElement('button');btn.type='button';btn.id=id;btn.dataset.orderAction=action;btn.innerHTML='<span>'+label+'</span>'+(shortcut?'<kbd>'+shortcut+'</kbd>':'');btn.title=shortcut?label+' ('+shortcut+')':label;btn.setAttribute('aria-label',btn.title);return btn};
const orderFrontPage=makeOrderItem('dtfOrderFrontPage','Para Frente da Página','Ctrl+Início','frontPage');
const orderBackPage=makeOrderItem('dtfOrderBackPage','Para Trás da Página','Ctrl+End','backPage');
const orderFrontLayer=makeOrderItem('dtfOrderFrontLayer','Para frente da camada','Shift+PgUp','frontLayer');
const orderBackLayer=makeOrderItem('dtfOrderBackLayer','Para trás da camada','Shift+PgDn','backLayer');
const orderForwardOne=makeOrderItem('dtfOrderForwardOne','Avançar um','Ctrl+PgUp','forwardOne');
const orderBackOne=makeOrderItem('dtfOrderBackOne','Recuar um','Ctrl+PgDn','backOne');
const orderBeforeTarget=makeOrderItem('dtfOrderBeforeTarget','Na frente de...','','beforeTarget');
const orderAfterTarget=makeOrderItem('dtfOrderAfterTarget','Atrás...','','afterTarget');
const orderSep1=document.createElement('div'),orderSep2=document.createElement('div');orderSep1.className=orderSep2.className='dtf-order-separator';
orderSub.append(orderFrontPage,orderBackPage,orderSep1,orderFrontLayer,orderBackLayer,orderForwardOne,orderBackOne,orderSep2,orderBeforeTarget,orderAfterTarget);orderMenu.append(orderBtn,orderSub);if(canvasMenu)canvasMenu.appendChild(orderMenu);
let orderTargetMode=null;
function selectedLayerIds(){return new Set(selectedObjects().map(o=>o.id))}
function selectedLayerList(){const ids=selectedLayerIds();return state.objects.filter(o=>ids.has(o.id))}
function selectedLayerBounds(){const ids=selectedLayerIds(),indexes=[];state.objects.forEach((o,i)=>{if(ids.has(o.id))indexes.push(i)});return indexes}
function canMoveSelectedLayer(action){
 const ids=selectedLayerIds(),indexes=selectedLayerBounds();if(!ids.size||state.objects.length<2)return false;
 if(action==='forwardOne')return indexes.some(i=>i<state.objects.length-1&&!ids.has(state.objects[i+1].id));
 if(action==='backOne')return indexes.some(i=>i>0&&!ids.has(state.objects[i-1].id));
 if(action==='frontPage'||action==='frontLayer')return indexes.some(i=>i<state.objects.length-1&&!ids.has(state.objects[i+1].id));
 if(action==='backPage'||action==='backLayer')return indexes.some(i=>i>0&&!ids.has(state.objects[i-1].id));
 if(action==='beforeTarget'||action==='afterTarget')return ids.size>0&&state.objects.some(o=>!ids.has(o.id));
 return false;
}
function updateOrderMenu(){
 const hasSelection=selectedObjects().length>0;
 orderMenu.style.display=hasSelection?'block':'none';
 orderBtn.disabled=!hasSelection;
 [orderFrontPage,orderBackPage,orderFrontLayer,orderBackLayer,orderForwardOne,orderBackOne,orderBeforeTarget,orderAfterTarget].forEach(btn=>{btn.disabled=!canMoveSelectedLayer(btn.dataset.orderAction)});
}
function reorderSelectedToExtreme(toFront,historyLabel,statusLabel){
 const ids=selectedLayerIds();if(!ids.size)return false;
 if(!canMoveSelectedLayer(toFront?'frontPage':'backPage')){setStatus(toFront?'Objeto já está na frente':'Objeto já está atrás');return false}
 pushHistory(historyLabel);
 const selectedItems=[],others=[];state.objects.forEach(o=>(ids.has(o.id)?selectedItems:others).push(o));
 state.objects=toFront?others.concat(selectedItems):selectedItems.concat(others);
 render();updateContextTools();updateOrderMenu();setStatus(statusLabel);return true;
}
function moveSelectedOneStep(toFront){
 const ids=selectedLayerIds();if(!ids.size)return false;
 if(!canMoveSelectedLayer(toFront?'forwardOne':'backOne')){setStatus(toFront?'Objeto já está na frente':'Objeto já está atrás');return false}
 pushHistory(toFront?'Avançar objeto uma camada':'Recuar objeto uma camada');
 if(toFront){
  for(let i=state.objects.length-2;i>=0;i--){if(ids.has(state.objects[i].id)&&!ids.has(state.objects[i+1].id)){const tmp=state.objects[i];state.objects[i]=state.objects[i+1];state.objects[i+1]=tmp}}
 }else{
  for(let i=1;i<state.objects.length;i++){if(ids.has(state.objects[i].id)&&!ids.has(state.objects[i-1].id)){const tmp=state.objects[i];state.objects[i]=state.objects[i-1];state.objects[i-1]=tmp}}
 }
 render();updateContextTools();updateOrderMenu();setStatus(toFront?'Objeto avançou uma camada':'Objeto recuou uma camada');return true;
}
function reorderSelectedRelative(target,placeInFront){
 const ids=selectedLayerIds();if(!target||ids.has(target.id)){setStatus('Escolha outro objeto como referência.');return false}
 pushHistory(placeInFront?'Mover objeto na frente de outro':'Mover objeto atrás de outro');
 const selectedItems=[],others=[];state.objects.forEach(o=>(ids.has(o.id)?selectedItems:others).push(o));
 let targetIndex=others.findIndex(o=>o.id===target.id);if(targetIndex<0)return false;
 const insertAt=placeInFront?targetIndex+1:targetIndex;
 others.splice(insertAt,0,...selectedItems);state.objects=others;
 render();updateContextTools();updateOrderMenu();setStatus(placeInFront?'Objeto colocado na frente da referência':'Objeto colocado atrás da referência');return true;
}
function runOrderAction(action){
 if(action==='frontPage')return reorderSelectedToExtreme(true,'Para Frente da Página','Objeto enviado para frente da página');
 if(action==='backPage')return reorderSelectedToExtreme(false,'Para Trás da Página','Objeto enviado para trás da página');
 if(action==='frontLayer')return reorderSelectedToExtreme(true,'Para frente da camada','Objeto enviado para frente da camada');
 if(action==='backLayer')return reorderSelectedToExtreme(false,'Para trás da camada','Objeto enviado para trás da camada');
 if(action==='forwardOne')return moveSelectedOneStep(true);
 if(action==='backOne')return moveSelectedOneStep(false);
 if(action==='beforeTarget'||action==='afterTarget')return beginOrderTargetPick(action);
 return false;
}
function beginOrderTargetPick(action){
 if(!canMoveSelectedLayer(action)){setStatus('Não há outro objeto para usar como referência.');return false}
 orderTargetMode=action;editorRoot.classList.add('dtf-order-pick-cursor');if(canvasMenu)canvasMenu.classList.remove('show');setStatus(action==='beforeTarget'?'Clique no objeto que ficará atrás do selecionado.':'Clique no objeto que ficará à frente do selecionado.');showToolHint(action==='beforeTarget'?'Clique no objeto de referência para colocar na frente dele.':'Clique no objeto de referência para colocar atrás dele.');return true;
}
function cancelOrderTargetPick(){if(orderTargetMode){orderTargetMode=null;editorRoot.classList.remove('dtf-order-pick-cursor');setStatus('Ordenação cancelada')}}
orderSub.addEventListener('click',e=>{const btn=e.target.closest('button[data-order-action]');if(!btn)return;e.preventDefault();e.stopPropagation();const keepOpen=btn.dataset.orderAction==='beforeTarget'||btn.dataset.orderAction==='afterTarget';runOrderAction(btn.dataset.orderAction);if(canvasMenu&&!keepOpen)canvasMenu.classList.remove('show')});
viewport.addEventListener('pointerdown',e=>{if(!orderTargetMode)return;const p=clientToWorkspace(e),target=p&&hitTest(p.x,p.y);e.preventDefault();e.stopImmediatePropagation();const mode=orderTargetMode;orderTargetMode=null;editorRoot.classList.remove('dtf-order-pick-cursor');if(!target){setStatus('Nenhum objeto escolhido para ordenar.');return}reorderSelectedRelative(target,mode==='beforeTarget')},{capture:true});
document.addEventListener('keydown',e=>{if(activeEditorRoot!==editorRoot)return;const editable=e.target&&(/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)||e.target.isContentEditable);if(editable)return;if(e.key==='Escape'&&orderTargetMode){e.preventDefault();cancelOrderTargetPick();return}if(!selectedObjects().length)return;let action=null;if((e.ctrlKey||e.metaKey)&&!e.altKey&&e.key==='Home')action='frontPage';else if((e.ctrlKey||e.metaKey)&&!e.altKey&&e.key==='End')action='backPage';else if(e.shiftKey&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&e.key==='PageUp')action='frontLayer';else if(e.shiftKey&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&e.key==='PageDown')action='backLayer';else if((e.ctrlKey||e.metaKey)&&!e.shiftKey&&!e.altKey&&e.key==='PageUp')action='forwardOne';else if((e.ctrlKey||e.metaKey)&&!e.shiftKey&&!e.altKey&&e.key==='PageDown')action='backOne';if(action){e.preventDefault();runOrderAction(action)}},{capture:true});
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.dtf-user-guide')||state.tool!=='select')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(hit&&hit.locked){e.preventDefault();e.stopImmediatePropagation();setStatus('Objeto bloqueado — use o botão direito para desbloquear')}},{capture:true});
document.addEventListener('contextmenu',()=>{const o=selected();if(lockMenu){lockMenu.style.display=o?'block':'none';lockMenu.textContent=o&&o.locked?'Desbloquear objeto':'Bloquear objeto'}if(typeof updateOrderMenu==='function')updateOrderMenu();},{capture:true});
function positionQuickMenuAt(clientX,clientY){
 const m=$id('dtfCanvasMenu');if(!m||!m.classList.contains('show'))return;
 const pad=8;
 m.classList.remove('dtf-menu-flip-x','dtf-menu-flip-y');
 m.style.maxHeight='calc(100vh - '+(pad*2)+'px)';
 m.style.maxWidth='calc(100vw - '+(pad*2)+'px)';
 let r=m.getBoundingClientRect();
 let w=Math.min(r.width||180,window.innerWidth-pad*2),h=Math.min(r.height||120,window.innerHeight-pad*2);
 let x=clamp(clientX,pad,Math.max(pad,window.innerWidth-w-pad));
 let y=clamp(clientY,pad,Math.max(pad,window.innerHeight-h-pad));
 m.style.left=x+'px';m.style.top=y+'px';
 requestAnimationFrame(()=>{
  if(!m.classList.contains('show'))return;
  const r2=m.getBoundingClientRect(),sub=m.querySelector('.dtf-order-submenu,.dtf-invert-submenu,.dtf-properties-submenu');
  const subW=sub?Math.max(sub.offsetWidth||0,260):0,subH=sub?Math.max(sub.offsetHeight||0,260):0;
  let nx=clamp(r2.left,pad,Math.max(pad,window.innerWidth-Math.min(r2.width,window.innerWidth-pad*2)-pad));
  let ny=clamp(r2.top,pad,Math.max(pad,window.innerHeight-Math.min(r2.height,window.innerHeight-pad*2)-pad));
  m.style.left=nx+'px';m.style.top=ny+'px';
  if(nx+r2.width+subW+pad>window.innerWidth)m.classList.add('dtf-menu-flip-x');
  if(ny+subH+pad>window.innerHeight)m.classList.add('dtf-menu-flip-y');
 });
}
document.addEventListener('contextmenu',e=>{setTimeout(()=>positionQuickMenuAt(e.clientX,e.clientY),0);setTimeout(()=>positionQuickMenuAt(e.clientX,e.clientY),40)},{capture:false});
window.addEventListener('resize',()=>{const m=$id('dtfCanvasMenu');if(m&&m.classList.contains('show'))positionQuickMenuAt(parseFloat(m.style.left)||8,parseFloat(m.style.top)||8)});
function quickSubmenuOf(host){
 return host&&host.querySelector?host.querySelector(':scope > .dtf-order-submenu,:scope > .dtf-invert-submenu,:scope > .dtf-properties-submenu'):null;
}
function resetQuickSubmenus(){
 if(!canvasMenu)return;
 canvasMenu.querySelectorAll('.dtf-order-submenu,.dtf-invert-submenu,.dtf-properties-submenu').forEach(sub=>{
  sub.classList.remove('dtf-submenu-protected');
  delete sub.dataset.dtfProtectedHost;
  ['position','left','right','top','bottom','maxHeight','maxWidth','display','width','height','minWidth','minHeight','overflow','visibility'].forEach(prop=>sub.style.removeProperty(prop));
 });
}
function protectQuickSubmenu(host){
 if(!canvasMenu||!canvasMenu.classList.contains('show')||!host)return;
 const sub=quickSubmenuOf(host);if(!sub)return;
 const pad=8,clearProps=['position','left','right','top','bottom','maxHeight','maxWidth','display','width','height','minWidth','minHeight','overflow','visibility'];
 const hostKey=host.className||host.textContent||'submenu';
 if(sub.classList.contains('dtf-submenu-protected')&&sub.dataset.dtfProtectedHost===hostKey)return;
 canvasMenu.querySelectorAll('.dtf-order-submenu,.dtf-invert-submenu,.dtf-properties-submenu').forEach(other=>{if(other!==sub){other.classList.remove('dtf-submenu-protected');delete other.dataset.dtfProtectedHost;clearProps.forEach(prop=>other.style.removeProperty(prop))}});
 sub.classList.add('dtf-submenu-protected');sub.dataset.dtfProtectedHost=hostKey;
 clearProps.forEach(prop=>sub.style.removeProperty(prop));
 sub.style.setProperty('display','block','important');
 sub.style.setProperty('position','fixed','important');
 sub.style.setProperty('left','0px','important');
 sub.style.setProperty('top','0px','important');
 sub.style.setProperty('right','auto','important');
 sub.style.setProperty('bottom','auto','important');
 sub.style.setProperty('visibility','hidden','important');
 sub.style.setProperty('minHeight','0','important');
 const isProperties=sub.classList.contains('dtf-properties-submenu');
 const maxW=Math.max(40,window.innerWidth-pad*2),maxH=Math.max(40,window.innerHeight-pad*2);
 const stablePropertyWidth=174,stablePropertyHeight=76;
 let naturalW=isProperties?stablePropertyWidth:Math.ceil(Math.max(sub.scrollWidth||0,sub.offsetWidth||0,170));
 let naturalH=isProperties?stablePropertyHeight:Math.ceil(Math.max(sub.scrollHeight||0,sub.offsetHeight||0,64));
 const sw=Math.min(naturalW,maxW),sh=Math.min(naturalH,maxH);
 const hr=host.getBoundingClientRect();
 const openLeft=hr.right+sw+pad>window.innerWidth&&hr.left-sw-pad>=0;
 let left=openLeft?hr.left-sw:hr.right;
 if(left+sw+pad>window.innerWidth)left=window.innerWidth-sw-pad;
 if(left<pad)left=pad;
 let top=hr.top;
 if(top+sh+pad>window.innerHeight)top=window.innerHeight-sh-pad;
 if(top<pad)top=pad;
 sub.style.setProperty('width',sw+'px','important');
 sub.style.setProperty('minWidth',sw+'px','important');
 sub.style.setProperty('maxWidth',sw+'px','important');
 sub.style.setProperty('height',sh+'px','important');
 sub.style.setProperty('minHeight',sh+'px','important');
 sub.style.setProperty('maxHeight',sh+'px','important');
 sub.style.setProperty('overflow',isProperties?'hidden':(naturalH>sh||naturalW>sw?'auto':'hidden'),'important');
 sub.style.setProperty('left',Math.round(left)+'px','important');
 sub.style.setProperty('top',Math.round(top)+'px','important');
 sub.style.removeProperty('visibility');
}
if(canvasMenu){
 canvasMenu.addEventListener('mouseover',event=>{
  const host=event.target&&event.target.closest?event.target.closest('.dtf-order-menu,.dtf-invert-menu,.dtf-properties-menu'):null;
  if(host&&canvasMenu.contains(host))protectQuickSubmenu(host);
 },true);
 canvasMenu.addEventListener('focusin',event=>{
  const host=event.target&&event.target.closest?event.target.closest('.dtf-order-menu,.dtf-invert-menu,.dtf-properties-menu'):null;
  if(host&&canvasMenu.contains(host))protectQuickSubmenu(host);
 },true);
 canvasMenu.addEventListener('mouseleave',resetQuickSubmenus);
 canvasMenu.addEventListener('click',event=>{if(!event.target.closest('.dtf-order-menu,.dtf-invert-menu,.dtf-properties-menu'))resetQuickSubmenus()},true);
}
window.addEventListener('resize',resetQuickSubmenus);
window.addEventListener('scroll',()=>{const m=$id('dtfCanvasMenu');if(m&&m.classList.contains('show'))m.classList.remove('show');resetQuickSubmenus()},true);

viewport.addEventListener('contextmenu',e=>{const p=clientToWorkspace(e),o=p&&hitTest(p.x,p.y);if(o){if(!state.selectedIds.includes(o.id))selectObject(o,false);lockMenu.style.display='block';lockMenu.textContent=o.locked?'Desbloquear objeto':'Bloquear objeto'}else lockMenu.style.display='none';if(typeof updateOrderMenu==='function')updateOrderMenu();},{capture:false});
lockMenu.addEventListener('click',()=>{const o=selected();if(!o)return;pushHistory(o.locked?'Desbloquear objeto':'Bloquear objeto');o.locked=!o.locked;lockMenu.textContent=o.locked?'Desbloquear objeto':'Bloquear objeto';render();canvasMenu.classList.remove('show')});
const copyPropMenu=document.createElement('button'),pastePropMenu=document.createElement('button');copyPropMenu.textContent='Copiar propriedades';pastePropMenu.textContent='Colar propriedades';if(canvasMenu){canvasMenu.append(copyPropMenu,pastePropMenu);pastePropMenu.style.display='none'}copyPropMenu.onclick=()=>{const o=selected();if(o){propertyClipboard={w:o.w,h:o.h,opacity:o.opacity,locked:o.locked};pastePropMenu.style.display='block';canvasMenu.classList.remove('show')}};pastePropMenu.onclick=()=>{const o=selected();if(o&&propertyClipboard){pushHistory('Colar propriedades');o.w=Math.min(canvas.width,propertyClipboard.w);o.h=Math.min(canvas.height,propertyClipboard.h);o.opacity=propertyClipboard.opacity;o.locked=propertyClipboard.locked;render();canvasMenu.classList.remove('show')}};
let gridEnabled=false,gridStepEnabled=false,objectSnapEnabled=false;
const gridStepStorageKey='printway_dtf_grid_step_mm';
let gridStepMm=1;
try{const saved=Number(localStorage.getItem(gridStepStorageKey));if(Number.isFinite(saved)&&saved>=.1&&saved<=100)gridStepMm=saved}catch(_){}
const activeGridStepMm=()=>gridEnabled&&gridStepEnabled?gridStepMm:1;
const gridStep=()=>Math.max(1,physicalMmToPx(activeGridStepMm()));
const snapGrid=v=>Math.round(v/gridStep())*gridStep();
const snapGroup=document.createElement('div');
snapGroup.className='dtf-group dtf-snap-group';
snapGroup.innerHTML='<div class="dtf-caption">ALINHAR À</div><div class="dtf-tools dtf-snap-tools"></div><div class="dtf-grid-step-row" hidden><label><span>Passo</span><input class="dtf-grid-step-input" type="number" min="0.1" max="100" step="0.1" inputmode="decimal" aria-label="Distância de cada passo da grade"><b>mm</b></label></div>';
const snapTools=snapGroup.querySelector('.dtf-snap-tools');
const gridStepRow=snapGroup.querySelector('.dtf-grid-step-row');
const gridStepInput=snapGroup.querySelector('.dtf-grid-step-input');
gridStepInput.value=String(gridStepMm);
const gridButton=document.createElement('button');
gridButton.type='button';gridButton.className='dtf-tool dtf-align-btn';gridButton.title='Alinhar à grade de 1 mm';gridButton.setAttribute('aria-label','Alinhar à grade de 1 mm');gridButton.setAttribute('aria-pressed','false');gridButton.innerHTML='<span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM9 3v18M15 3v18M3 9h18M3 15h18"/></svg></span><span>Grade</span>';
const stepButton=document.createElement('button');
stepButton.type='button';stepButton.className='dtf-tool dtf-align-btn dtf-grid-steps-button';stepButton.title='Passos da grade';stepButton.setAttribute('aria-label','Definir passos da grade');stepButton.setAttribute('aria-pressed','false');stepButton.disabled=true;stepButton.innerHTML='<span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17h16M5 13v8M9 15v6M13 13v8M17 15v6M21 13v8M6 7h12M8 5 6 7l2 2M16 5l2 2-2 2"/></svg></span><span>Passos</span>';
const snapSeparator=document.createElement('span');snapSeparator.className='dtf-snap-separator';snapSeparator.setAttribute('aria-hidden','true');
const objectButton=document.createElement('button');objectButton.type='button';objectButton.className='dtf-tool dtf-align-btn';objectButton.title='Alinhar ao objeto mais próximo';objectButton.setAttribute('aria-label','Alinhar ao objeto mais próximo');objectButton.innerHTML='<span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h7v14H3zM14 5h7v14h-7zM10 12h4M12 10l2 2-2 2"/></svg></span><span>Objeto</span>';
const guidesButton=document.createElement('button');guidesButton.type='button';guidesButton.className='dtf-tool dtf-align-btn';guidesButton.title='Mostrar margens de segurança, centro e linhas de corte';guidesButton.setAttribute('aria-label','Mostrar guias e margens de segurança');guidesButton.innerHTML='<span class="dtf-icon">⌗</span><span>Guias</span>';
snapTools.append(gridButton,stepButton,snapSeparator,objectButton);
const alignPanel=Q('[data-panel="alinhamentos"]');if(alignPanel)alignPanel.appendChild(snapGroup);
const visualPanel=Q('[data-panel="visualizar"]');
const guidesGroup=document.createElement('div');guidesGroup.className='dtf-group dtf-guides-group';guidesGroup.innerHTML='<div class="dtf-caption">GUIAS</div><div class="dtf-tools dtf-guides-tools"></div>';
const guidesTools=guidesGroup.querySelector('.dtf-guides-tools');if(guidesTools)guidesTools.appendChild(guidesButton);
if(visualPanel)visualPanel.appendChild(guidesGroup);
function normalizeGridStepMm(value){const n=Number(String(value).replace(',','.'));return Number.isFinite(n)?Math.max(.1,Math.min(100,n)):1}
function updateGridSpacing(){if(gridEnabled)inner.style.setProperty('--dtf-grid-size',gridStep()+'px');else inner.style.removeProperty('--dtf-grid-size')}
function syncGridStepUi(){
 stepButton.disabled=!gridEnabled;
 stepButton.classList.toggle('active',gridEnabled&&gridStepEnabled);
 stepButton.setAttribute('aria-pressed',String(gridEnabled&&gridStepEnabled));
 gridStepRow.hidden=!(gridEnabled&&gridStepEnabled);
 const mm=activeGridStepMm();
 gridButton.title='Alinhar à grade de '+mm.toLocaleString('pt-BR',{maximumFractionDigits:2})+' mm';
 gridButton.setAttribute('aria-label',gridButton.title);
}
gridButton.addEventListener('click',()=>{
 gridEnabled=!gridEnabled;
 if(!gridEnabled)gridStepEnabled=false;
 inner.classList.toggle('dtf-grid',gridEnabled);
 gridButton.classList.toggle('active',gridEnabled);
 gridButton.setAttribute('aria-pressed',String(gridEnabled));
 syncGridStepUi();updateGridSpacing();
 setStatus(gridEnabled?'Grade ativa — encaixe a cada '+activeGridStepMm().toLocaleString('pt-BR',{maximumFractionDigits:2})+' mm':'Grade desativada');
});
stepButton.addEventListener('click',()=>{
 if(!gridEnabled)return;
 gridStepEnabled=!gridStepEnabled;
 syncGridStepUi();updateGridSpacing();
 setStatus(gridStepEnabled?'Passos ativos — movimento a cada '+gridStepMm.toLocaleString('pt-BR',{maximumFractionDigits:2})+' mm':'Passos desativados — grade em 1 mm');
 if(gridStepEnabled){requestAnimationFrame(()=>{try{gridStepInput.focus();gridStepInput.select()}catch(_){}})}
});
function applyGridStepInput(){
 gridStepMm=normalizeGridStepMm(gridStepInput.value);
 gridStepInput.value=String(gridStepMm);
 try{localStorage.setItem(gridStepStorageKey,String(gridStepMm))}catch(_){}
 if(gridEnabled&&gridStepEnabled){updateGridSpacing();setStatus('Passo da grade: '+gridStepMm.toLocaleString('pt-BR',{maximumFractionDigits:2})+' mm')}
}
gridStepInput.addEventListener('change',applyGridStepInput);
gridStepInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();applyGridStepInput();gridStepInput.blur()}});
syncGridStepUi();
objectButton.addEventListener('click',()=>{objectSnapEnabled=!objectSnapEnabled;objectButton.classList.toggle('active',objectSnapEnabled);objectButton.setAttribute('aria-pressed',String(objectSnapEnabled));setStatus(objectSnapEnabled?'Alinhamento por objeto ativo':'Alinhamento por objeto desativado')});
let guidesEnabled=false;let guideMargins={top:5,right:5,bottom:5,left:5};const GUIDE_MARGIN_EDGES=['top','right','bottom','left'];const GUIDE_MARGIN_LABELS={top:'Superior',right:'Direita',bottom:'Inferior',left:'Esquerda'};const guidesOverlay=$id('dtfGuidesOverlay');const guideMarginLines={};
if(guidesOverlay){const lines=document.createElement('div');lines.className='dtf-guide-cut-lines';GUIDE_MARGIN_EDGES.forEach(edge=>{const line=document.createElement('button');line.type='button';line.className='dtf-guide-cut-line dtf-guide-cut-'+edge;line.dataset.edge=edge;line.setAttribute('aria-label','Linha de corte '+GUIDE_MARGIN_LABELS[edge]);line.title='Duplo clique para ajustar a margem '+GUIDE_MARGIN_LABELS[edge].toLowerCase();line.addEventListener('pointerdown',event=>{event.preventDefault();event.stopPropagation()});line.addEventListener('dblclick',event=>{event.preventDefault();event.stopPropagation();openGuideMarginsModal(edge)});guideMarginLines[edge]=line;lines.appendChild(line)});guidesOverlay.appendChild(lines)}
function sanitizeGuideMargins(raw){const source=raw&&typeof raw==='object'?raw:{};const value=edge=>{const n=Number(String(source[edge]??guideMargins[edge]).replace(',','.'));return Number.isFinite(n)?clamp(n,0,9999):guideMargins[edge]};return {top:value('top'),right:value('right'),bottom:value('bottom'),left:value('left')}}
function updateGuidesOverlay(){if(!guidesOverlay)return;guidesOverlay.classList.toggle('show',guidesEnabled);if(!guidesEnabled)return;guideMargins=sanitizeGuideMargins(guideMargins);const top=physicalMmToPx(guideMargins.top),right=physicalMmToPx(guideMargins.right),bottom=physicalMmToPx(guideMargins.bottom),left=physicalMmToPx(guideMargins.left),safe=guidesOverlay.querySelector('.dtf-guide-safe');if(safe)safe.style.inset=top+'px '+right+'px '+bottom+'px '+left+'px';if(guideMarginLines.top)guideMarginLines.top.style.top=top+'px';if(guideMarginLines.right)guideMarginLines.right.style.right=right+'px';if(guideMarginLines.bottom)guideMarginLines.bottom.style.bottom=bottom+'px';if(guideMarginLines.left)guideMarginLines.left.style.left=left+'px'}guidesButton.addEventListener('click',()=>{guidesEnabled=!guidesEnabled;guidesButton.classList.toggle('active',guidesEnabled);guidesButton.setAttribute('aria-pressed',String(guidesEnabled));updateGuidesOverlay();setStatus(guidesEnabled?'Guias ativas — margens de segurança, centro e linhas de corte visíveis':'Guias desativadas')});updateGuidesOverlay()

let textDirectEditSession=null;
const state={
 workWmm:280,workHmm:100,dpi:300,bgColor:'#ffffff',transparent:true,
 objects:[],selectedId:null,selectedIds:[],zoom:1,panX:0,panY:0,space:false,drag:null,marquee:null,
 customGuides:[],selectedGuideId:null,
 tool:'select',brushSize:32,compare:false,comparePct:50,
 undo:[],redo:[],historyLock:false,historyLimit:22,
 pdf:null,pdfFileName:'documento',pdfPage:1,pdfDpi:300,
 logs:[],clipboard:[],intelligentVariantSets:{},displayMode:'auto'
};
const DISPLAY_MODE_KEY='printway_dtf_display_mode';
const DISPLAY_MODES={auto:{label:'Automático (recomendado)'},original:{label:'Qualidade original'},balanced:{label:'Equilibrado'},fast:{label:'Rápido'},draft:{label:'Muito rápido'}};
try{const savedDisplayMode=localStorage.getItem(DISPLAY_MODE_KEY);if(DISPLAY_MODES[savedDisplayMode])state.displayMode=savedDisplayMode}catch(_){ }
const ERASER_MIN=4,ERASER_MAX=300,MAX_PX=18000000;
const colorAreaRemoval={color:null,stroke:null};
const globalColorRemoval={stroke:null};
function colorAreaRgbLabel(color){return color?'RGB '+[color.r,color.g,color.b].join(', '):'Nenhuma cor capturada'}
function syncColorAreaBrushPreview(){const button=ui.colorAreaBrushPreview;if(!button)return;const color=colorAreaRemoval.color,label=colorAreaRgbLabel(color);button.classList.toggle('has-color',!!color);button.style.setProperty('--dtf-picked-color',color?'rgb('+color.r+' '+color.g+' '+color.b+')':'transparent');button.title=color?label+' — clique para trocar a cor':'Nenhuma cor capturada — clique na imagem';button.setAttribute('aria-label',color?label+'. Clique para capturar outra cor':'Nenhuma cor capturada. Clique na imagem para capturar uma cor')}
function resetColorAreaBrushColor(message='Clique na imagem para capturar outra cor'){colorAreaRemoval.color=null;colorAreaRemoval.stroke=null;hideColorCursorPreview();syncColorAreaBrushPreview();if(message)setStatus(message)}
let localWorkspace=null;
try{localWorkspace=JSON.parse(localStorage.getItem('printway_dtf_workspace')||'null')}catch(_){localWorkspace=null}
const serverWorkspace=userSettings&&userSettings.workspace&&typeof userSettings.workspace==='object'?userSettings.workspace:null;
const workspaceSource=serverWorkspace||localWorkspace;
if(workspaceSource){state.workWmm=Number(workspaceSource.w)||state.workWmm;state.workHmm=Number(workspaceSource.h)||state.workHmm;state.dpi=Number(workspaceSource.dpi)||state.dpi}
if(!serverWorkspace&&localWorkspace)persistUserSettings({workspace:{w:state.workWmm,h:state.workHmm,dpi:state.dpi}});
syncColorAreaBrushPreview();
function setBrushSizeValue(value,notify){
 state.brushSize=clamp(parseInt(value,10)||32,ERASER_MIN,ERASER_MAX);
 if(ui.brush)ui.brush.value=String(state.brushSize);
 if(ui.brushValue)ui.brushValue.textContent=String(state.brushSize);
 if(ui.colorAreaBrush)ui.colorAreaBrush.value=String(state.brushSize);
 if(ui.colorAreaBrushValue)ui.colorAreaBrushValue.textContent=String(state.brushSize);
 updateEraserCursor();
 if(notify){setStatus('Medida '+state.brushSize);showToolHint('Medida '+state.brushSize)}
}

const scrollAdjustKeys={t:false,p:false,m:false,b:false};
function scrollAdjustEditableTarget(target){return !!(target&&(/INPUT|TEXTAREA|SELECT/.test(target.tagName)||target.isContentEditable))}
function scrollAdjustKey(e){return String(e.key||'').toLowerCase()}
function isScrollAdjustReservedKey(key){return key==='t'||key==='p'||key==='m'||key==='b'}
document.addEventListener('keydown',e=>{if(activeEditorRoot!==editorRoot||scrollAdjustEditableTarget(e.target))return;const key=scrollAdjustKey(e);if(isScrollAdjustReservedKey(key))scrollAdjustKeys[key]=true},{capture:true});
document.addEventListener('keyup',e=>{const key=scrollAdjustKey(e);if(isScrollAdjustReservedKey(key))scrollAdjustKeys[key]=false},{capture:true});
window.addEventListener('blur',()=>{scrollAdjustKeys.t=false;scrollAdjustKeys.p=false;scrollAdjustKeys.m=false;scrollAdjustKeys.b=false});
function changeRangeByScroll(input,deltaY,step){if(!input)return false;const min=Number(input.min||0),max=Number(input.max||100),current=Number(input.value||0),next=clamp(current+(deltaY<0?step:-step),min,max);if(next===current)return true;input.value=String(next);input.dispatchEvent(new Event('input',{bubbles:true}));return true}

const colorCursorPreview=document.createElement('div');colorCursorPreview.className='dtf-color-cursor-preview';colorCursorPreview.setAttribute('aria-hidden','true');document.body.appendChild(colorCursorPreview);
function hideColorCursorPreview(){colorCursorPreview.classList.remove('show','dtf-replace-preview');colorCursorPreview.style.removeProperty('background');colorCursorPreview.style.removeProperty('background-image')}
function updateColorCursorPreview(e){
 const canPreview=(state.tool==='globalColorRemove')||(state.tool==='colorAreaRemove'&&!colorAreaRemoval.color)||state.tool==='eyedropper'||state.tool==='replaceColor';
 if(!e||!canPreview){hideColorCursorPreview();return}
 const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);
 if(!hit){hideColorCursorPreview();return}
 const lx=Math.floor((p.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((p.y-hit.y)/hit.h*hit.canvas.height);
 if(lx<0||ly<0||lx>=hit.canvas.width||ly>=hit.canvas.height){hideColorCursorPreview();return}
 const pixel=hit.canvas.getContext('2d',{willReadFrequently:true}).getImageData(lx,ly,1,1).data;
 if(pixel[3]<8){hideColorCursorPreview();return}
 const hoverColor='rgb('+pixel[0]+','+pixel[1]+','+pixel[2]+')';
 if(state.tool==='replaceColor'){
  const newHex=normalizeHexColor(fillColor&&fillColor.value?fillColor.value:'#8c3f00');
  const gradient='linear-gradient(90deg,'+hoverColor+' 0 50%,'+newHex+' 50% 100%)';
  colorCursorPreview.classList.add('dtf-replace-preview');
  colorCursorPreview.style.setProperty('--dtf-preview-left',hoverColor);
  colorCursorPreview.style.setProperty('--dtf-preview-right',newHex);
  colorCursorPreview.style.setProperty('background',gradient,'important');
  colorCursorPreview.style.setProperty('background-image',gradient,'important');
  colorCursorPreview.title='Trocar RGB '+pixel[0]+', '+pixel[1]+', '+pixel[2]+' por '+rgbLabel(newHex);
 }else{
  colorCursorPreview.classList.remove('dtf-replace-preview');
  colorCursorPreview.style.removeProperty('--dtf-preview-left');
  colorCursorPreview.style.removeProperty('--dtf-preview-right');
  colorCursorPreview.style.setProperty('background',hoverColor,'important');
  colorCursorPreview.style.setProperty('background-image','none','important');
  colorCursorPreview.title='RGB '+pixel[0]+', '+pixel[1]+', '+pixel[2];
 }
 colorCursorPreview.style.left=(e.clientX+16)+'px';
 colorCursorPreview.style.top=(e.clientY+16)+'px';
 colorCursorPreview.classList.add('show')
}
viewport.addEventListener('pointermove',updateColorCursorPreview,{capture:true});
viewport.addEventListener('pointerleave',hideColorCursorPreview,{capture:true});

function uid(){return 'obj_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,8)}
function clamp(v,a,b){return Math.max(a,Math.min(b,v))}
function mmToPx(mm){const u=unitSelect?unitSelect.value:'mm';const mmValue=u==='cm'?mm*10:u==='m'?mm*1000:u==='px'?mm*25.4/state.dpi:mm;return Math.max(1,Math.round((mmValue/25.4)*state.dpi))}
function physicalMmToPx(mm){return Math.max(1,Math.round((mm/25.4)*state.dpi))}
function pxToMm(px){return px*25.4/state.dpi}
function mmToUnit(mm){const u=unitSelect?unitSelect.value:'mm';return u==='cm'?mm/10:u==='m'?mm/1000:u==='px'?mm*state.dpi/25.4:mm}
function unitToMm(v){const u=unitSelect?unitSelect.value:'mm';return u==='cm'?v*10:u==='m'?v*1000:u==='px'?v*25.4/state.dpi:v}
let statusFadeTimer=0,statusSwapTimer=0,statusPending='';
function setStatus(t){
 if(!statusEl)return;
 const message=String(t==null?'':t),current=String(statusEl.textContent||'');
 if(statusSwapTimer){statusPending=message;return}
 if(current&&current!==message&&getComputedStyle(statusEl).opacity!=='0'){
  statusPending=message;statusEl.classList.remove('dtf-status-fade-in');statusEl.classList.add('dtf-status-fade-out');clearTimeout(statusFadeTimer);
  statusSwapTimer=window.setTimeout(()=>{const next=statusPending||message;statusPending='';statusSwapTimer=0;setStatus(next)},280);return;
 }
 statusEl.classList.remove('dtf-status-fade-in','dtf-status-fade-out');void statusEl.offsetWidth;statusEl.textContent=message;statusEl.classList.add('dtf-status-fade-in');clearTimeout(statusFadeTimer);
 if(message&&message!=='Pronto')statusFadeTimer=window.setTimeout(()=>{statusEl.classList.remove('dtf-status-fade-in');statusEl.classList.add('dtf-status-fade-out')},4700);
}

/* Guias criadas pelas réguas. A posição é guardada em milímetros para não
 * mudar quando o DPI, o zoom ou o tamanho visual da página forem alterados. */
const customGuidesLayer=document.createElement('div');
customGuidesLayer.className='dtf-custom-guides';
customGuidesLayer.setAttribute('aria-label','Guias do painel de trabalho');
viewport.appendChild(customGuidesLayer);
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
 const viewportRect=viewport.getBoundingClientRect(),canvasRect=canvas.getBoundingClientRect(),scaleX=canvas.width?canvasRect.width/canvas.width:1,scaleY=canvas.height?canvasRect.height/canvas.height:1;
 customGuidesLayer.style.left=viewport.scrollLeft+'px';customGuidesLayer.style.top=viewport.scrollTop+'px';customGuidesLayer.style.width=viewport.clientWidth+'px';customGuidesLayer.style.height=viewport.clientHeight+'px';
 const zoom=Math.max(.01,Number(state.zoom)||1),hit=10/zoom,stroke=1/zoom;
 customGuidesLayer.style.setProperty('--dtf-guide-hit',hit+'px');
 customGuidesLayer.style.setProperty('--dtf-guide-stroke',stroke+'px');
 state.customGuides.forEach(guide=>{
  const line=document.createElement('button'),axis=guide.orientation==='v'?'vertical':'horizontal',value=Number(guide.valueMm.toFixed(2));
  line.type='button';line.className='dtf-user-guide dtf-user-guide-'+guide.orientation;
  const selected=guide.id===state.selectedGuideId;
  line.classList.toggle('selected',selected);
  line.dataset.guideId=guide.id;
  line.setAttribute('aria-pressed',String(selected));
  line.setAttribute('aria-label','Guia '+axis+' em '+value+' milímetros');
  line.title='Guia '+axis+': '+value+' mm — arraste para mover; arraste para fora ou dê duplo clique para excluir';
  if(guide.orientation==='v')line.style.left=(canvasRect.left-viewportRect.left+guideCanvasPosition(guide)*scaleX)+'px';else line.style.top=(canvasRect.top-viewportRect.top+guideCanvasPosition(guide)*scaleY)+'px';
  line.addEventListener('pointerdown',event=>beginGuideDrag(guide.orientation,event,guide.id));
  line.addEventListener('dblclick',event=>{event.preventDefault();event.stopPropagation();removeCustomGuide(guide.id)});
  customGuidesLayer.appendChild(line);
 });
}
function updateSelectedGuideStyle(){customGuidesLayer.querySelectorAll('.dtf-user-guide').forEach(line=>{const selected=line.dataset.guideId===state.selectedGuideId;line.classList.toggle('selected',selected);line.setAttribute('aria-pressed',String(selected))})}
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

let dtfProgressTimer=null;
function progress(v,t){const p=clamp(Number(v)||0,0,100);if(dtfProgressTimer){clearTimeout(dtfProgressTimer);dtfProgressTimer=null}progressEl.classList.add('show');progressText.classList.add('show');progressEl.setAttribute('role','progressbar');progressEl.setAttribute('aria-valuemin','0');progressEl.setAttribute('aria-valuemax','100');progressEl.setAttribute('aria-valuenow',String(Math.round(p)));progressBar.style.width=p+'%';progressText.textContent=t||'Processando...'}
function finish(t){if(dtfProgressTimer){clearTimeout(dtfProgressTimer);dtfProgressTimer=null}progressBar.style.width='100%';progressEl.setAttribute('aria-valuenow','100');progressText.textContent=t||'Concluído';dtfProgressTimer=setTimeout(()=>{progressEl.classList.remove('show');progressText.classList.remove('show');progressBar.style.width='0%'},850)}
function hideProgress(){if(dtfProgressTimer){clearTimeout(dtfProgressTimer);dtfProgressTimer=null}progressEl.classList.remove('show');progressText.classList.remove('show');progressBar.style.width='0%';progressText.textContent='';progressEl.removeAttribute('aria-valuenow')}
function nextPaint(){return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))}
function log(msg,data){const entry='['+new Date().toLocaleTimeString()+'] '+msg+(data!==undefined?'\n'+JSON.stringify(data,null,2):'');state.logs.push(entry);if(state.logs.length>80)state.logs.shift();console.debug('[DTF]',msg,data)}
function showError(title,err){const detail=err&&err.stack?err.stack:(err&&err.message?err.message:String(err));errorTitle.textContent=title;errorLog.textContent=state.logs.concat(['ERROR: '+detail]).join('\n\n');errorBox.classList.add('show');log(title,detail);recordEditorError(err,title);setStatus(title);errorBox.scrollIntoView({block:'nearest'})}
function clearError(){errorBox.classList.remove('show');errorLog.textContent=''}
ui.copyError.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(errorLog.textContent);setStatus('Log copiado')}catch(e){setStatus('Selecione e copie o log manualmente')}})

function canvasClone(src){const c=document.createElement('canvas');c.width=src.width;c.height=src.height;c.getContext('2d').putImageData(src.getContext('2d').getImageData(0,0,src.width,src.height),0,0);return c}
function dataClone(src){return src?new Uint8ClampedArray(src):null}
function cloneCanvasData(c){return {w:c.width,h:c.height,data:dataClone(c.getContext('2d').getImageData(0,0,c.width,c.height).data)}}
function canvasFromData(s){const c=document.createElement('canvas');c.width=s.w;c.height=s.h;c.getContext('2d').putImageData(new ImageData(dataClone(s.data),s.w,s.h),0,0);return c}
function refreshRestoreCanvas(o){if(o&&o.canvas)o.restoreCanvas=canvasFromData(cloneCanvasData(o.canvas));return o}
function ensureRestoreReferences(){state.objects.forEach(o=>{if(!o.restoreCanvas)o.restoreCanvas=canvasFromData(cloneCanvasData(o.baseCanvas||o.canvas))})}
function objectSnapshot(o){return {id:o.id,name:o.name,x:o.x,y:o.y,w:o.w,h:o.h,visible:o.visible,opacity:o.opacity,locked:o.locked===true,sourceType:o.sourceType,groupId:o.groupId,canvas:cloneCanvasData(o.canvas),base:cloneCanvasData(o.baseCanvas),restore:o.restoreCanvas?cloneCanvasData(o.restoreCanvas):cloneCanvasData(o.baseCanvas||o.canvas),aiOriginal:o.aiOriginalCanvas?cloneCanvasData(o.aiOriginalCanvas):null,aiOriginalW:o.aiOriginalW||null,aiOriginalH:o.aiOriginalH||null,originalW:o.originalW,originalH:o.originalH,intelligentVariantSetId:o.intelligentVariantSetId||null,intelligentVariantId:o.intelligentVariantId||null,intelligentVariantRotated:o.intelligentVariantRotated===true,shapeFillColor:o.shapeFillColor||null,shapeStrokeColor:o.shapeStrokeColor||null,shapeStrokeWidth:o.shapeStrokeWidth!=null&&o.shapeStrokeWidth!==''&&Number.isFinite(Number(o.shapeStrokeWidth))?Number(o.shapeStrokeWidth):null,lineNodes:Array.isArray(o.lineNodes)?o.lineNodes.map(point=>({x:Number(point.x)||0,y:Number(point.y)||0})):null,lineClosed:o.lineClosed===true,shapeCornerRadii:{...objectShapeCornerRadii(o)},rotation:Number(o.rotation)||0,skewX:Number(o.skewX)||0,skewY:Number(o.skewY)||0,skewAnchorX:(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),skewAnchorY:(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),textContent:o.textContent||'',textFontFamily:o.textFontFamily||'Roboto',textFontSize:Number(o.textFontSize)||32,textColor:o.textColor||'#000000',textAlign:o.textAlign||'center',textBold:o.textBold===true,textItalic:o.textItalic===true,textPadding:Number(o.textPadding)||8}}
const objectSnapshotOriginal=objectSnapshot;objectSnapshot=function(o){const snapshot=objectSnapshotOriginal(o);snapshot.blendMode=o.blendMode||'source-over';snapshot.imageFilter=o.imageFilter||'none';snapshot.imageEffect=o.imageEffect||'none';snapshot.imageMask=o.imageMask||'none';snapshot.flipX=o.flipX===true;snapshot.flipY=o.flipY===true;return snapshot}
function snapshotIntelligentVariantSets(){return Object.fromEntries(Object.entries(state.intelligentVariantSets||{}).map(([id,set])=>[id,{id,options:(set.options||[]).map(option=>({...option,canvas:cloneCanvasData(option.canvas)}))}]))}
function restoreIntelligentVariantSets(sets){return Object.fromEntries(Object.entries(sets||{}).map(([id,set])=>[id,{id,options:(set.options||[]).map(option=>({...option,canvas:canvasFromData(option.canvas)}))}]))}
function projectSnapshot(){return {workWmm:state.workWmm,workHmm:state.workHmm,dpi:state.dpi,bgColor:state.bgColor,transparent:state.transparent,guideMargins:{...guideMargins},customGuides:state.customGuides.map(guide=>({...guide})),selectedGuideId:state.selectedGuideId,selectedId:state.selectedId,selectedIds:[...(state.selectedIds||[])],objects:state.objects.map(objectSnapshot),intelligentVariantSets:snapshotIntelligentVariantSets()}}
function restoreProject(s){
 const view={zoom:state.zoom,panX:state.panX,panY:state.panY};
 state.historyLock=true;state.workWmm=s.workWmm;state.workHmm=s.workHmm;state.dpi=s.dpi;state.bgColor=s.bgColor;state.transparent=s.transparent;guideMargins=sanitizeGuideMargins(s.guideMargins||guideMargins);state.customGuides=sanitizeCustomGuides(s.customGuides);state.selectedGuideId=state.customGuides.some(guide=>guide.id===s.selectedGuideId)?s.selectedGuideId:null;state.selectedId=s.selectedId;state.selectedIds=s.selectedIds||[s.selectedId].filter(Boolean);state.intelligentVariantSets=restoreIntelligentVariantSets(s.intelligentVariantSets||{});
 state.objects=s.objects.map(o=>({...o,canvas:canvasFromData(o.canvas),baseCanvas:canvasFromData(o.base),restoreCanvas:canvasFromData(o.restore||o.base||o.canvas),aiOriginalCanvas:o.aiOriginal?canvasFromData(o.aiOriginal):null}));normalizeObjectNames();
 state.historyLock=false;syncWorkspaceUI();state.zoom=view.zoom;state.panX=view.panX;state.panY=view.panY;applyZoom();ensureRestoreReferences();updateGuidesOverlay();render();updateSelection();updateHistoryUI();
}
const autoToleranceSuggestedIds=new Set();
let projectDirty=false,currentProjectName='',currentProjectHandle=null;
const autosaveUserKey=String(editorRoot.dataset.dtfUserKey||editorRoot.dataset.dtfUserId||'usuario').replace(/[^a-z0-9_-]/gi,'_')||'usuario';
const AUTOSAVE_GLOBAL_KEY='printway_dtf_autosave_v1';
const AUTOSAVE_KEY=AUTOSAVE_GLOBAL_KEY+'_'+autosaveUserKey;
const AUTOSAVE_DB_NAME='printway_dtf_editor_autosave',AUTOSAVE_DB_STORE='projects';
try{localStorage.removeItem(AUTOSAVE_GLOBAL_KEY)}catch(_){}
let autosaveTimer=0,autosaveBusy=false,autosaveRecoveryShown=false,autosaveLastSignature='',autosaveLastWrite=0,autosaveDbPromise=null,autosaveDbQueue=Promise.resolve();
const AUTOSAVE_DELAY_FAST=180,AUTOSAVE_DELAY_NORMAL=380,AUTOSAVE_WATCHDOG_MS=900;
function autosaveDb(){if(!window.indexedDB)return Promise.reject(new Error('IndexedDB indisponível'));if(autosaveDbPromise)return autosaveDbPromise;autosaveDbPromise=new Promise((resolve,reject)=>{const request=indexedDB.open(AUTOSAVE_DB_NAME,1);request.onupgradeneeded=()=>{const db=request.result;if(!db.objectStoreNames.contains(AUTOSAVE_DB_STORE))db.createObjectStore(AUTOSAVE_DB_STORE)};request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error||new Error('Banco de proteção indisponível'))});return autosaveDbPromise}
function autosaveDbWrite(saved){autosaveDbQueue=autosaveDbQueue.catch(()=>undefined).then(()=>autosaveDb().then(db=>new Promise((resolve,reject)=>{const tx=db.transaction(AUTOSAVE_DB_STORE,'readwrite');tx.objectStore(AUTOSAVE_DB_STORE).put(saved,AUTOSAVE_KEY);tx.oncomplete=()=>resolve();tx.onerror=()=>reject(tx.error||new Error('Não foi possível gravar a proteção'))}))).catch(error=>log('Salvamento de proteção no navegador indisponível',error&&error.message?error.message:error));return autosaveDbQueue}
function autosaveDbDelete(){autosaveDbQueue=autosaveDbQueue.catch(()=>undefined).then(()=>autosaveDb().then(db=>new Promise((resolve,reject)=>{const tx=db.transaction(AUTOSAVE_DB_STORE,'readwrite');tx.objectStore(AUTOSAVE_DB_STORE).delete(AUTOSAVE_KEY);tx.oncomplete=()=>resolve();tx.onerror=()=>reject(tx.error||new Error('Não foi possível limpar a proteção'))}))).catch(()=>undefined);return autosaveDbQueue}
function autosaveDbRead(){return autosaveDb().then(db=>new Promise((resolve,reject)=>{const tx=db.transaction(AUTOSAVE_DB_STORE,'readonly'),request=tx.objectStore(AUTOSAVE_DB_STORE).get(AUTOSAVE_KEY);request.onsuccess=()=>resolve(request.result||null);request.onerror=()=>reject(request.error||new Error('Não foi possível ler a proteção'))})).catch(()=>null)}
function autosaveSignature(){
 try{return JSON.stringify({v:VERSION,w:state.workWmm,h:state.workHmm,dpi:state.dpi,bg:state.bgColor,t:state.transparent,margins:GUIDE_MARGIN_EDGES.map(edge=>Number(guideMargins[edge].toFixed(3))),sel:state.selectedId,selIds:state.selectedIds||[],guides:(state.customGuides||[]).map(g=>[g.id,g.orientation,g.valueMm]),objects:(state.objects||[]).map(o=>[o.id,o.name,Math.round(o.x*100)/100,Math.round(o.y*100)/100,Math.round(o.w*100)/100,Math.round(o.h*100)/100,o.visible!==false,Math.round((o.opacity==null?1:o.opacity)*1000)/1000,o.locked===true,o.sourceType||'image',o.groupId||'',o.shapeFillColor||'',o.shapeStrokeColor||'',o.shapeStrokeWidth!=null&&o.shapeStrokeWidth!==''&&Number.isFinite(Number(o.shapeStrokeWidth))?Number(o.shapeStrokeWidth):'',JSON.stringify(o.lineNodes||[]),o.lineClosed===true,...Object.values(objectShapeCornerRadii(o)).map(value=>Math.round(value*100)/100),Math.round((Number(o.rotation)||0)*100)/100,Math.round((Number(o.skewX)||0)*100)/100,Math.round((Number(o.skewY)||0)*100)/100,o.skewAnchorX||'center',o.skewAnchorY||'center',o.textContent||'',o.textFontFamily||'',Number(o.textFontSize)||0,o.textColor||'',o.textAlign||'',o.textBold===true,o.textItalic===true,Number(o.textPadding)||0,o.canvas&&o.canvas.width,o.canvas&&o.canvas.height,o.baseCanvas&&o.baseCanvas.width,o.baseCanvas&&o.baseCanvas.height,o.originalW||'',o.originalH||''])})}catch(_){return String(Date.now())}
}
const autosaveSignatureOriginal=autosaveSignature;autosaveSignature=function(){return autosaveSignatureOriginal()+'|'+JSON.stringify((state.objects||[]).map(o=>[o.blendMode||'source-over',o.imageFilter||'none',o.imageEffect||'none',o.imageMask||'none',o.flipX===true,o.flipY===true]))}
function clearAutosave(){if(autosaveTimer){clearTimeout(autosaveTimer);autosaveTimer=0}try{localStorage.removeItem(AUTOSAVE_KEY)}catch(_){}autosaveDbDelete();autosaveLastSignature=autosaveSignature();autosaveLastWrite=Date.now()}
function writeAutosave(force){
 if(autosaveBusy)return;
 const sig=autosaveSignature();
 if(!force&&!projectDirty&&sig===autosaveLastSignature)return;
 autosaveBusy=true;
 try{
  const project=projectData(),saved={version:4,savedAt:Date.now(),appVersion:VERSION,userKey:autosaveUserKey,projectName:currentProjectName||'',dirty:projectDirty!==false,signature:sig,project};
  try{localStorage.setItem(AUTOSAVE_KEY,JSON.stringify(saved))}catch(error){log('Memória rápida de proteção cheia; usando a proteção ampliada.',error&&error.message?error.message:error)}
  autosaveDbWrite(saved);
  autosaveLastSignature=sig;autosaveLastWrite=Date.now();
 }catch(error){log('Salvamento automático indisponível',error&&error.message?error.message:error)}
 finally{autosaveBusy=false}
}
function scheduleAutosave(delay){
 if(autosaveBusy)return;
 if(autosaveTimer)clearTimeout(autosaveTimer);
 autosaveTimer=window.setTimeout(()=>{autosaveTimer=0;writeAutosave(false)},Math.max(150,delay||AUTOSAVE_DELAY_NORMAL));
}
function validAutosave(saved){if(!saved||saved.userKey!==autosaveUserKey||!saved.project||saved.project.format!=='PrintWay Editor Project'||!Array.isArray(saved.project.objects))return null;const age=Date.now()-Number(saved.savedAt||0);return Number.isFinite(age)&&age>=0&&age<30*24*60*60*1000?saved:null}
async function readAutosave(){let local=null;try{local=validAutosave(JSON.parse(localStorage.getItem(AUTOSAVE_KEY)||'null'))}catch(_){}const database=validAutosave(await autosaveDbRead());return database&&(!local||Number(database.savedAt)>=Number(local.savedAt))?database:local}
function setProjectDirty(dirty){
 projectDirty=Boolean(dirty);
 if(ui.saveProject){const action=currentProjectHandle?'Salvar no arquivo .pwedit aberto':'Salvar projeto .pwedit';ui.saveProject.classList.toggle('dtf-project-dirty',projectDirty);ui.saveProject.title=projectDirty?action+' — há alterações não salvas':action}
 if(projectDirty)scheduleAutosave(AUTOSAVE_DELAY_FAST);else autosaveLastSignature=autosaveSignature();
}
setInterval(()=>{if(state.historyLock||autosaveBusy||!state.objects.length)return;const sig=autosaveSignature();if(sig!==autosaveLastSignature){if(!projectDirty)setProjectDirty(true);scheduleAutosave(AUTOSAVE_DELAY_FAST)}else if(projectDirty&&Date.now()-autosaveLastWrite>AUTOSAVE_WATCHDOG_MS){scheduleAutosave(AUTOSAVE_DELAY_NORMAL)}},AUTOSAVE_WATCHDOG_MS);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')writeAutosave(true)});
window.addEventListener('pagehide',()=>writeAutosave(true));
function pushHistory(label){if(state.historyLock)return;const snap=projectSnapshot();snap.label=label||'Edição';state.undo.push(snap);if(state.undo.length>state.historyLimit)state.undo.shift();state.redo=[];setProjectDirty(true);updateHistoryUI()}
let historyBusy=false;
const historyProgressWindow=document.createElement('div');historyProgressWindow.className='dtf-history-progress-window';historyProgressWindow.setAttribute('role','status');historyProgressWindow.setAttribute('aria-live','polite');historyProgressWindow.innerHTML='<div class="dtf-history-progress-spinner" aria-hidden="true"></div><div class="dtf-history-progress-message">Processando histórico...</div>';document.body.appendChild(historyProgressWindow);
function showHistoryProgress(message){historyProgressWindow.querySelector('.dtf-history-progress-message').textContent=message||'Processando histórico...';historyProgressWindow.classList.add('show')}
function hideHistoryProgress(){historyProgressWindow.classList.remove('show')}
function historyStep(kind){if(kind==='undo'){if(!state.undo.length)return false;const target=state.undo[state.undo.length-1];if(target&&target.label==='Antes do arraste de remoção por área'&&pendingSmartAreaFeedback&&pendingSmartAreaFeedback.historyDepth===state.undo.length)settleSmartAreaFeedback(false);const cur=projectSnapshot();cur.label='Estado atual';state.redo.push(cur);restoreProject(state.undo.pop());return true}if(!state.redo.length)return false;const cur=projectSnapshot();cur.label='Estado atual';state.undo.push(cur);restoreProject(state.redo.pop());return true}
async function runHistoryAction(kind,count=1){if(historyBusy)return false;const available=kind==='undo'?state.undo.length:state.redo.length;if(!available)return false;historyBusy=true;closeHistoryMenus();updateHistoryUI();const total=Math.min(Math.max(1,count),available),verb=kind==='undo'?'Voltando':'Avançando';showHistoryProgress(verb+'...');progress(5,verb+' histórico...');await nextPaint();let done=0;try{for(let i=0;i<total;i++){showHistoryProgress(verb+' '+(i+1)+' de '+total+'...');progress(10+Math.round(i/total*75),verb+' '+(i+1)+' de '+total+'...');await nextPaint();if(!historyStep(kind))break;done++;await nextPaint()}if(done){setProjectDirty(true);setStatus(kind==='undo'?(done>1?done+' etapas desfeitas':'Desfeito'):(done>1?done+' etapas refeitas':'Refeito'));finish(kind==='undo'?'Histórico desfeito':'Histórico refeito')}return done>0}finally{historyBusy=false;hideHistoryProgress();updateHistoryUI()}}
function undo(){return runHistoryAction('undo',1)}
function redo(){return runHistoryAction('redo',1)}
function updateHistoryUI(){if(ui.undo)ui.undo.disabled=historyBusy||!state.undo.length;if(ui.redo)ui.redo.disabled=historyBusy||!state.redo.length;renderHistoryMenus()}
function historyLabel(s,index,total){return (index+1)+'. '+(s.label||'Etapa '+(index+1))}
function renderHistoryMenus(){
 const fill=(menu,items,kind)=>{if(!menu)return;menu.innerHTML='';items.forEach((s,i)=>{const b=document.createElement('button');b.type='button';b.textContent=historyLabel(s,i,items.length);b.dataset.index=String(i);b.disabled=historyBusy;b.addEventListener('click',async()=>{const count=kind==='undo'?items.length-i:i+1;closeHistoryMenus();await runHistoryAction(kind,count)});menu.appendChild(b)});menu.classList.toggle('open',false)};
 fill(ui.undoMenu,[...state.undo].reverse(),'undo');fill(ui.redoMenu,[...state.redo].reverse(),'redo');
 if(ui.undoMenuBtn)ui.undoMenuBtn.disabled=historyBusy||!state.undo.length;if(ui.redoMenuBtn)ui.redoMenuBtn.disabled=historyBusy||!state.redo.length;
}
function closeHistoryMenus(){if(ui.undoMenu)ui.undoMenu.classList.remove('open');if(ui.redoMenu)ui.redoMenu.classList.remove('open');if(ui.undoMenuBtn)ui.undoMenuBtn.setAttribute('aria-expanded','false');if(ui.redoMenuBtn)ui.redoMenuBtn.setAttribute('aria-expanded','false')}
function toggleHistoryMenu(kind){const menu=kind==='undo'?ui.undoMenu:ui.redoMenu,other=kind==='undo'?ui.redoMenu:ui.undoMenu,btn=kind==='undo'?ui.undoMenuBtn:ui.redoMenuBtn;if(!menu||!other||!btn)return;other.classList.remove('open');menu.classList.toggle('open');btn.setAttribute('aria-expanded',String(menu.classList.contains('open')))}

function selected(){return state.objects.find(o=>o.id===state.selectedId)||null}
function selectedObjects(){const ids=state.selectedIds&&state.selectedIds.length?state.selectedIds:[state.selectedId];return state.objects.filter(o=>ids.includes(o.id))}
/* Identificação curta e consistente para facilitar a seleção e a leitura do projeto. */
function objectNamePrefix(object){const source=String(object&&object.sourceType||'');if(source==='shape-text'||source==='text')return 'Txt';if(source.indexOf('shape-')===0)return 'Frm';return 'Img'}
function normalizeObjectNames(objects=state.objects){const counters={Img:0,Frm:0,Txt:0};(Array.isArray(objects)?objects:[]).forEach(object=>{if(!object)return;const prefix=objectNamePrefix(object);counters[prefix]=(counters[prefix]||0)+1;object.name=prefix+String(counters[prefix]).padStart(2,'0')});return objects}
function setContextAvailability(control,enabled,reason=''){
 if(!control||typeof control.disabled==='undefined')return;
 if(!control.dataset.contextTitle)control.dataset.contextTitle=control.getAttribute('title')||control.getAttribute('aria-label')||'';
 control.disabled=!enabled;control.setAttribute('aria-disabled',String(!enabled));
 const title=enabled?control.dataset.contextTitle:reason;if(title)control.setAttribute('title',title);
}
function updateContextTools(){
 const items=selectedObjects(),count=items.length,hasSelection=count>0,hasMultiple=count>1,hasObjects=state.objects.length>0;
 const isShapeObject=item=>String(item&&item.sourceType||'image').indexOf('shape-')===0;
 const canUseBackgroundRemoval=item=>item&&item.visible!==false&&item.locked!==true&&!isShapeObject(item);
 const canUseFill=item=>item&&item.visible!==false&&item.locked!==true&&!!item.canvas;
 const hasImageSelection=items.some(canUseBackgroundRemoval),hasFillSelection=items.some(canUseFill);
 const needsSelection='Selecione um objeto para usar esta ferramenta.',needsImage='Selecione uma imagem para usar esta ferramenta.',needsFill='Selecione um objeto que aceite preenchimento.',needsMultiple='Selecione pelo menos duas imagens para usar esta função.';
 [ui.duplicate,ui.del,ui.center].forEach(control=>setContextAvailability(control,hasSelection,needsSelection));
 [ui.fillTool].forEach(control=>setContextAvailability(control,hasFillSelection,needsFill));
 [ui.original,ui.reset,ui.pickColor,ui.dehalo,ui.clickTol,ui.tol,ui.tolMinus,ui.tolPlus,replaceColorButton,colorAreaRemoveButton].forEach(control=>setContextAvailability(control,hasImageSelection,needsImage));
 [ui.eraser,ui.restoreBrush,ui.brush].forEach(control=>setContextAvailability(control,hasObjects,'Insira uma imagem na área de trabalho primeiro.'));
 [clickRemove,areaRemoveButton,outerAreaRemoveButton,globalColorRemoveButton].forEach(control=>setContextAvailability(control,hasImageSelection,needsImage));
 if(fillColor)setContextAvailability(fillColor,hasFillSelection,needsFill);
 [ui.alignLeft,ui.alignCenter,ui.alignRight,ui.alignTop,ui.alignMiddle,ui.alignBottom].forEach(control=>setContextAvailability(control,hasSelection,needsSelection));
 [ui.distributeH,ui.distributeV,ui.organize].forEach(control=>setContextAvailability(control,hasMultiple,needsMultiple));
 setContextAvailability(fitHeightButton,hasObjects,'Insira uma imagem para ajustar a área de trabalho.');
 setContextAvailability(ui.magicFill,!hasObjects||hasImageSelection,hasObjects?'Selecione uma imagem para a montagem inteligente.':'Envie uma imagem para iniciar a montagem inteligente.');
 setContextAvailability(ui.exportOpen,hasObjects,'Insira uma imagem antes de exportar.');
 const organizeApply=$id('dtfOrganizeApply');if(organizeApply)setContextAvailability(organizeApply,hasMultiple,needsMultiple);
 const anyGrouped=items.some(item=>item.groupId),menuNeedsSelection='Selecione um objeto primeiro.';
 [['dtfCopyObjects',hasSelection,menuNeedsSelection],['dtfDuplicateObjects',hasSelection,menuNeedsSelection],['dtfDeleteObjects',hasSelection,menuNeedsSelection],['dtfFlipH',hasSelection,menuNeedsSelection],['dtfFlipV',hasSelection,menuNeedsSelection],['dtfGroupObjects',hasMultiple&&!anyGrouped,anyGrouped?'Desagrupe os objetos antes de criar um novo grupo.':needsMultiple],['dtfUngroupObjects',anyGrouped,'Selecione um grupo para desagrupar.'],['dtfPasteObjects',state.clipboard&&state.clipboard.length,'Copie uma imagem antes de colar.'],['dtfLayerForward',hasSelection&&canMoveSelectedLayer('front'),'Este objeto já está na frente.'],['dtfLayerBack',hasSelection&&canMoveSelectedLayer('back'),'Este objeto já está atrás.']].forEach(([id,enabled,reason])=>setContextAvailability($id(id),enabled,reason));if(typeof updateOrderMenu==='function')updateOrderMenu();
}
function updateObjectUI(){
 const o=selected();const disabled=!o;
 ui.duplicate.disabled=disabled;ui.del.disabled=disabled;ui.center.disabled=disabled;ui.compare.disabled=disabled;ui.objX.disabled=disabled;ui.objY.disabled=disabled;ui.objW.disabled=disabled;ui.objH.disabled=disabled;ui.compareRange.disabled=disabled||!state.compare;
 ui.selectedName.textContent=o?o.name:'Nenhum';
 if(ui.objectTotal)ui.objectTotal.textContent=String(state.objects.length);if(ui.objectSelected)ui.objectSelected.textContent=String(state.selectedIds.length);
 if(o){ui.objX.value=mmToUnit(pxToMm(o.x)).toFixed(1);ui.objY.value=mmToUnit(pxToMm(o.y)).toFixed(1);ui.objW.value=mmToUnit(pxToMm(o.w)).toFixed(1);ui.objH.value=mmToUnit(pxToMm(o.h)).toFixed(1)} else {ui.objX.value=ui.objY.value=ui.objW.value=ui.objH.value=''};syncLineWidthButton();syncCornerRadiusButton();syncInlineTextControls();updateContextTools()
}

function syncWorkspaceUI(){
 ui.workW.value=Number(mmToUnit(state.workWmm).toFixed(2));ui.workH.value=Number(mmToUnit(state.workHmm).toFixed(2));ui.dpi.value=state.dpi;ui.bgColor.value=state.bgColor;ui.transparent.checked=state.transparent;document.querySelectorAll('#dtfEditor .dtf-mm').forEach(el=>el.textContent=unitSelect?unitSelect.value:'mm');
 const w=physicalMmToPx(state.workWmm),h=physicalMmToPx(state.workHmm);canvas.width=w;canvas.height=h;compareCanvas.width=w;compareCanvas.height=h;updateRulers();
 inner.style.width=w+'px';inner.style.height=h+'px';inner.classList.toggle('dtf-checker',state.transparent);inner.classList.toggle('dtf-solid-bg',!state.transparent);inner.style.background=state.transparent?'':' '+state.bgColor;updateGuidesOverlay();
 ui.outputInfo.textContent='Área: '+state.workWmm+' × '+state.workHmm+' mm • '+state.dpi+' DPI • '+w+' × '+h+' px';fitZoom(false);requestAnimationFrame(updateRulers)
}

function minimumZoom(){const vw=Math.max(1,viewport.clientWidth-44),vh=Math.max(1,viewport.clientHeight-40);return clamp(Math.min(vw/canvas.width,vh/canvas.height),.08,1)}
function fitZoom(showStatus=true){const z=minimumZoom();state.zoom=z;state.panX=0;state.panY=0;applyZoom();if(showStatus)setStatus('Área inteira ajustada à tela — zoom '+Math.round(z*100)+'%')}
function fitWidthZoom(showStatus=true){const vw=Math.max(1,viewport.clientWidth-44),z=clamp(vw/canvas.width,.08,4);state.zoom=z;state.panX=0;state.panY=0;applyZoom();if(showStatus)setStatus('Área ajustada à largura — zoom '+Math.round(z*100)+'%')}
function fitHeightZoom(showStatus=true){const vh=Math.max(1,viewport.clientHeight-40),z=clamp(vh/canvas.height,.08,4);state.zoom=z;state.panX=0;state.panY=0;applyZoom();if(showStatus)setStatus('Área ajustada à altura — zoom '+Math.round(z*100)+'%')}
function updateScrollSpace(){if(!innerScroll)return;const z=Math.max(.01,state.zoom||1),x=Math.max(0,state.panX||0),y=Math.max(0,state.panY||0),availableW=Math.max(1,viewport.clientWidth-44),availableH=Math.max(1,viewport.clientHeight-40);innerScroll.style.width=Math.max(availableW,canvas.width*z+x+8)+'px';innerScroll.style.height=Math.max(availableH,canvas.height*z+y+8)+'px'}
function applyZoom(){state.panX=Math.max(0,state.panX);state.panY=Math.max(0,state.panY);inner.style.transform='translate('+state.panX+'px,'+state.panY+'px) scale('+state.zoom+')';updateScrollSpace();if(selectionLabel)selectionLabel.style.transform='scale('+(1/state.zoom)+')';updateRulers();updateSelection();updateEraserCursor()}
function setZoom(z){state.zoom=clamp(z,minimumZoom(),4);applyZoom();setStatus('Zoom '+Math.round(state.zoom*100)+'%')}

function clearCanvas(){ctx.clearRect(0,0,canvas.width,canvas.height);ctx.fillStyle=state.transparent?'#eeeeee':state.bgColor;ctx.fillRect(0,0,canvas.width,canvas.height)}
function drawObjectChecker(o){if(!state.transparent||!o.visible||String(o.sourceType||'').indexOf('shape-')===0)return;const x=o.x,y=o.y,w=o.w,h=o.h,rot=(Number(o.rotation)||0)*Math.PI/180,skx=Math.tan((Number(o.skewX)||0)*Math.PI/180),sky=Math.tan((Number(o.skewY)||0)*Math.PI/180),anchorX=(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),anchorY=(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),ax=(anchorX==='left'?x:anchorX==='right'?x+w:x+w/2),ay=(anchorY==='top'?y:anchorY==='bottom'?y+h:y+h/2),ox=(anchorX==='left'?0:anchorX==='right'?-w:-w/2),oy=(anchorY==='top'?0:anchorY==='bottom'?-h:-h/2);ctx.save();ctx.translate(ax,ay);if(rot)ctx.rotate(rot);if(skx||sky)ctx.transform(1,sky,skx,1,0,0);ctx.beginPath();ctx.rect(ox,oy,w,h);ctx.clip();ctx.fillStyle='#fff';ctx.fillRect(ox,oy,w,h);ctx.fillStyle='#dedede';const size=16,startX=Math.floor(ox/size)*size,startY=Math.floor(oy/size)*size,endX=Math.ceil((ox+w)/size)*size,endY=Math.ceil((oy+h)/size)*size;for(let yy=startY;yy<endY;yy+=size)for(let xx=startX;xx<endX;xx+=size){if((Math.floor(xx/size)+Math.floor(yy/size))%2===0)ctx.fillRect(xx,yy,size,size)}ctx.restore()}
function drawObject(o,targetCtx,scaleX=1,scaleY=1,enhance=false){if(!o.visible)return;if(textDirectEditSession&&textDirectEditSession.objectId===o.id&&isTextShape(o))return;const x=o.x*scaleX,y=o.y*scaleY,w=o.w*scaleX,h=o.h*scaleY,rot=(Number(o.rotation)||0)*Math.PI/180,skx=Math.tan((Number(o.skewX)||0)*Math.PI/180),sky=Math.tan((Number(o.skewY)||0)*Math.PI/180),natural=Math.round(w)===o.canvas.width&&Math.round(h)===o.canvas.height,anchorX=(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),anchorY=(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),ax=(anchorX==='left'?x:anchorX==='right'?x+w:x+w/2),ay=(anchorY==='top'?y:anchorY==='bottom'?y+h:y+h/2),ox=(anchorX==='left'?0:anchorX==='right'?-w:-w/2),oy=(anchorY==='top'?0:anchorY==='bottom'?-h:-h/2);targetCtx.save();targetCtx.globalAlpha=o.opacity||1;targetCtx.imageSmoothingEnabled=!natural;targetCtx.imageSmoothingQuality='high';if(enhance)targetCtx.filter='contrast(1.035) saturate(1.025)';targetCtx.translate(ax,ay);if(rot)targetCtx.rotate(rot);if(skx||sky)targetCtx.transform(1,sky,skx,1,0,0);targetCtx.drawImage(o.canvas,0,0,o.canvas.width,o.canvas.height,ox,oy,w,h);targetCtx.restore()}function shapeTransformedCornerPoints(o){const w=o.w,h=o.h,rot=(Number(o.rotation)||0)*Math.PI/180,cos=Math.cos(rot),sin=Math.sin(rot),skx=Math.tan((Number(o.skewX)||0)*Math.PI/180),sky=Math.tan((Number(o.skewY)||0)*Math.PI/180),anchorX=(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),anchorY=(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),ax=(anchorX==='left'?o.x:anchorX==='right'?o.x+w:o.x+w/2),ay=(anchorY==='top'?o.y:anchorY==='bottom'?o.y+h:o.y+h/2),ox=(anchorX==='left'?0:anchorX==='right'?-w:-w/2),oy=(anchorY==='top'?0:anchorY==='bottom'?-h:-h/2),pts=[[ox,oy],[ox+w,oy],[ox+w,oy+h],[ox,oy+h]];return pts.map(point=>{const px=point[0],py=point[1],sx=px+skx*py,sy=py+sky*px;return{x:ax+sx*cos-sy*sin,y:ay+sx*sin+sy*cos}})}
function shapeEdgeMidpoint(points,edge){const map={top:[0,1],right:[1,2],bottom:[2,3],left:[3,0]},pair=map[edge]||map.top,a=points[pair[0]],b=points[pair[1]];return{x:(a.x+b.x)/2,y:(a.y+b.y)/2}}
function shapeTransformAnchors(o){const pts=shapeTransformedCornerPoints(o);return{top:shapeEdgeMidpoint(pts,'top'),right:shapeEdgeMidpoint(pts,'right'),bottom:shapeEdgeMidpoint(pts,'bottom'),left:shapeEdgeMidpoint(pts,'left')}}
function shapeTransformLocalDelta(transformState,p){const angle=-(Number(transformState.startRotation)||0)*Math.PI/180,dx=p.x-transformState.startX,dy=p.y-transformState.startY,cos=Math.cos(angle),sin=Math.sin(angle);return{x:dx*cos-dy*sin,y:dx*sin+dy*cos}}
function anchorShapeTransformEdge(o,edge,anchors){if(!anchors||!anchors[edge])return;const current=shapeEdgeMidpoint(shapeTransformedCornerPoints(o),edge),target=anchors[edge];o.x+=target.x-current.x;o.y+=target.y-current.y}

function render(){clearCanvas();for(const o of state.objects)drawObjectChecker(o);for(const o of state.objects)drawObject(o,ctx);updateCompareCanvas();updateSelection();updateEraserCursor()}
function imageFilterCss(o){const filters={none:'none',grayscale:'grayscale(1)',sepia:'sepia(.86)',vivid:'saturate(1.8) contrast(1.14)',cool:'saturate(1.12) hue-rotate(165deg) contrast(1.05)',warm:'saturate(1.25) sepia(.2) hue-rotate(-12deg)',cinema:'contrast(1.2) saturate(.82) brightness(.92)',faded:'saturate(.62) contrast(.92) brightness(1.08)',invert:'invert(1)'};return filters[o&&o.imageFilter]||'none'}
function imageMaskPath(targetCtx,o,ox,oy,w,h){const mask=o&&o.imageMask;if(!mask||mask==='none')return false;targetCtx.beginPath();if(mask==='circle'){targetCtx.ellipse(ox+w/2,oy+h/2,Math.abs(w/2),Math.abs(h/2),0,0,Math.PI*2)}else if(mask==='rounded'){const r=Math.min(Math.abs(w),Math.abs(h))*.16;if(typeof targetCtx.roundRect==='function')targetCtx.roundRect(ox,oy,w,h,r);else{const rr=Math.min(r,Math.abs(w)/2,Math.abs(h)/2),x1=Math.min(ox,ox+w),x2=Math.max(ox,ox+w),y1=Math.min(oy,oy+h),y2=Math.max(oy,oy+h);targetCtx.moveTo(x1+rr,y1);targetCtx.lineTo(x2-rr,y1);targetCtx.quadraticCurveTo(x2,y1,x2,y1+rr);targetCtx.lineTo(x2,y2-rr);targetCtx.quadraticCurveTo(x2,y2,x2-rr,y2);targetCtx.lineTo(x1+rr,y2);targetCtx.quadraticCurveTo(x1,y2,x1,y2-rr);targetCtx.lineTo(x1,y1+rr);targetCtx.quadraticCurveTo(x1,y1,x1+rr,y1)}}else if(mask==='star'){const cx=ox+w/2,cy=oy+h/2,outer=Math.min(Math.abs(w),Math.abs(h))*.5,inner=outer*.44;for(let i=0;i<10;i++){const angle=-Math.PI/2+i*Math.PI/5,r=i%2?inner:outer,x=cx+Math.cos(angle)*r,y=cy+Math.sin(angle)*r;if(i===0)targetCtx.moveTo(x,y);else targetCtx.lineTo(x,y)}targetCtx.closePath()}else return false;targetCtx.clip();return true}
function autoDisplayModeForObject(o,file=null){const bytes=Number(file&&file.size)||0,pixels=o&&o.canvas?o.canvas.width*o.canvas.height:0,side=o&&o.canvas?Math.max(o.canvas.width,o.canvas.height):0;if(bytes>=18*1024*1024||pixels>25000000||side>7000)return'draft';if(bytes>=7*1024*1024||pixels>12000000||side>5000)return'fast';if(bytes>=2*1024*1024||pixels>5000000||side>3500)return'balanced';return'original'}
function assignAutoDisplayMode(o,file=null){if(o&&o.canvas&&!o.autoDisplayMode)o.autoDisplayMode=autoDisplayModeForObject(o,file);return o}
function displayScaleForObject(o){if(!o||!o.canvas||isTextShape(o)||String(o.sourceType||'').startsWith('shape-'))return 1;const requested=state.displayMode||'auto',mode=requested==='auto'?(o.autoDisplayMode||autoDisplayModeForObject(o)):requested,pixels=Math.max(1,o.canvas.width*o.canvas.height),side=Math.max(o.canvas.width,o.canvas.height);if(mode==='original')return 1;const settings={balanced:{pixels:9000000,side:3072,factor:.78},fast:{pixels:4000000,side:2048,factor:.54},draft:{pixels:1800000,side:1280,factor:.32}}[mode];if(!settings)return 1;return clamp(Math.min(settings.factor,Math.sqrt(settings.pixels/pixels),settings.side/side),.08,1)}
function displayCanvasFor(o){if(!o||!o.canvas)return o&&o.canvas;const requested=state.displayMode||'auto',mode=requested==='auto'?(o.autoDisplayMode||autoDisplayModeForObject(o)):requested;if(mode==='original')return o.canvas;const scale=displayScaleForObject(o);if(scale>=.999)return o.canvas;const key=[mode,o.canvas.width,o.canvas.height,Math.round(scale*1000)].join('|');if(o._displayCanvas&&o._displaySource===o.canvas&&o._displayKey===key)return o._displayCanvas;const preview=document.createElement('canvas');preview.width=Math.max(1,Math.round(o.canvas.width*scale));preview.height=Math.max(1,Math.round(o.canvas.height*scale));const pc=preview.getContext('2d',{alpha:true});pc.imageSmoothingEnabled=true;pc.imageSmoothingQuality='high';pc.drawImage(o.canvas,0,0,preview.width,preview.height);o._displayCanvas=preview;o._displaySource=o.canvas;o._displayKey=key;return preview}
function clearDisplayPreview(o){if(!o)return;o._displayCanvas=null;o._displaySource=null;o._displayKey=''}
function clearAllDisplayPreviews(){state.objects.forEach(clearDisplayPreview)}
function drawImageWithEffect(targetCtx,o,ox,oy,w,h){const source=targetCtx===ctx?displayCanvasFor(o):o.canvas,draw=()=>targetCtx.drawImage(source,0,0,source.width,source.height,ox,oy,w,h),effect=o&&o.imageEffect||'none';if(effect==='shadow'){targetCtx.shadowColor='rgba(0,0,0,.42)';targetCtx.shadowBlur=Math.max(4,Math.min(w,h)*.045);targetCtx.shadowOffsetX=Math.max(2,w*.018);targetCtx.shadowOffsetY=Math.max(2,h*.018);draw()}else if(effect==='glow'){targetCtx.shadowColor='rgba(0,132,184,.78)';targetCtx.shadowBlur=Math.max(5,Math.min(w,h)*.08);draw()}else if(effect==='outline'){const alpha=targetCtx.globalAlpha;targetCtx.shadowColor='rgba(8,126,174,.95)';targetCtx.shadowBlur=0;targetCtx.shadowOffsetX=0;targetCtx.shadowOffsetY=0;targetCtx.globalAlpha=alpha*.55;const pad=Math.max(1,Math.min(w,h)*.012);for(let dx=-pad;dx<=pad;dx+=pad)for(let dy=-pad;dy<=pad;dy+=pad)if(dx||dy)targetCtx.drawImage(source,0,0,source.width,source.height,ox+dx,oy+dy,w,h);targetCtx.globalAlpha=alpha;draw()}else draw();targetCtx.shadowColor='transparent';targetCtx.shadowBlur=0;targetCtx.shadowOffsetX=0;targetCtx.shadowOffsetY=0}
const drawObjectOriginal=drawObject;
drawObject=function(o,targetCtx,scaleX=1,scaleY=1,enhance=false){if(!o.visible)return;if(textDirectEditSession&&textDirectEditSession.objectId===o.id&&isTextShape(o))return;const x=o.x*scaleX,y=o.y*scaleY,w=o.w*scaleX,h=o.h*scaleY,rot=(Number(o.rotation)||0)*Math.PI/180,skx=Math.tan((Number(o.skewX)||0)*Math.PI/180),sky=Math.tan((Number(o.skewY)||0)*Math.PI/180),natural=Math.round(w)===o.canvas.width&&Math.round(h)===o.canvas.height,anchorX=(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),anchorY=(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),ax=(anchorX==='left'?x:anchorX==='right'?x+w:x+w/2),ay=(anchorY==='top'?y:anchorY==='bottom'?y+h:y+h/2),ox=(anchorX==='left'?0:anchorX==='right'?-w:-w/2),oy=(anchorY==='top'?0:anchorY==='bottom'?-h:-h/2);targetCtx.save();targetCtx.globalAlpha=clamp(Number(o.opacity==null?1:o.opacity),0,1);targetCtx.globalCompositeOperation=o.blendMode||'source-over';targetCtx.imageSmoothingEnabled=!natural;targetCtx.imageSmoothingQuality='high';targetCtx.filter=enhance?'contrast(1.035) saturate(1.025)':imageFilterCss(o);targetCtx.translate(ax,ay);if(rot)targetCtx.rotate(rot);if(skx||sky)targetCtx.transform(1,sky,skx,1,0,0);targetCtx.scale(o.flipX===true?-1:1,o.flipY===true?-1:1);imageMaskPath(targetCtx,o,ox,oy,w,h);drawImageWithEffect(targetCtx,o,ox,oy,w,h);targetCtx.restore()}
function updateCompareCanvas(){if(!state.compare||!selected()){compareOverlay.classList.remove('show');return}const o=selected();compareCtx.clearRect(0,0,compareCanvas.width,compareCanvas.height);compareCtx.save();compareCtx.globalAlpha=o.opacity||1;compareCtx.drawImage(o.baseCanvas,0,0,o.baseCanvas.width,o.baseCanvas.height,o.x,o.y,o.w,o.h);compareCtx.restore();compareOverlay.style.clipPath='inset(0 '+(100-state.comparePct)+'% 0 0)';compareOverlay.classList.add('show')}

let selectionTransformMode=false;
function updateSelection(){inner.querySelectorAll('.dtf-multi-selection,.dtf-lock-badge').forEach(el=>el.remove());const o=selected();if(!o){selection.classList.remove('show','dtf-transform-mode','dtf-selection-follow-transform');selection.style.transform='';selection.style.transformOrigin='';badge.classList.remove('show');updateObjectUI();return}if(o.locked){const lb=document.createElement('div');lb.className='dtf-lock-badge';lb.textContent='🔒';lb.style.left=o.x+'px';lb.style.top=o.y+'px';inner.appendChild(lb)}
 state.selectedIds.filter(id=>id!==o.id).forEach(id=>{const item=state.objects.find(x=>x.id===id);if(!item)return;const box=document.createElement('div');box.className='dtf-multi-selection';box.dataset.objectId=item.id;box.setAttribute('aria-hidden','true');box.style.left=item.x+'px';box.style.top=item.y+'px';box.style.width=item.w+'px';box.style.height=item.h+'px';['nw','n','ne','e','se','s','sw','w'].forEach(handle=>{const knob=document.createElement('span');knob.className='dtf-multi-handle dtf-multi-h-'+handle;box.appendChild(knob)});inner.appendChild(box)});
 selection.classList.add('show');const shapeMode=!!(selectionTransformMode&&state.selectedIds.length===1&&isTransformTarget(o));const hasSelectionTransform=!!(state.selectedIds.length===1&&(isTransformTarget(o)||Number(o.rotation)||Number(o.skewX)||Number(o.skewY)));selection.classList.toggle('dtf-transform-mode',shapeMode);selection.classList.toggle('dtf-selection-follow-transform',hasSelectionTransform);selection.style.left=o.x+'px';selection.style.top=o.y+'px';selection.style.width=o.w+'px';selection.style.height=o.h+'px';if(hasSelectionTransform){const sx=(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),sy=(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),originX=sx==='left'?'0%':sx==='right'?'100%':'50%',originY=sy==='top'?'0%':sy==='bottom'?'100%':'50%';selection.style.transform='rotate('+(Number(o.rotation)||0)+'deg) skew('+(Number(o.skewX)||0)+'deg,'+(Number(o.skewY)||0)+'deg)';selection.style.transformOrigin=originX+' '+originY}else{selection.style.transform='';selection.style.transformOrigin=''}selectionLabel.style.top=o.y<32?(o.h+6)+'px':'-34px';selectionLabel.textContent=o.name+' • '+pxToMm(o.w).toFixed(1)+' × '+pxToMm(o.h).toFixed(1)+' mm';badge.textContent=state.objects.length+' objeto'+(state.objects.length===1?'':'s');badge.classList.add('show');updateObjectUI()}
function hitTest(wx,wy){for(let i=state.objects.length-1;i>=0;i--){const o=state.objects[i];if(!o.visible||wx<o.x||wy<o.y||wx>o.x+o.w||wy>o.y+o.h)continue;return o}return null}
function hitStackObjects(wx,wy){const stack=[];for(let i=state.objects.length-1;i>=0;i--){const o=state.objects[i];if(!o.visible||wx<o.x||wy<o.y||wx>o.x+o.w||wy>o.y+o.h)continue;stack.push(o)}return stack}
function selectAltStackObject(wx,wy){const stack=hitStackObjects(wx,wy);if(!stack.length)return null;if(stack.length===1){selectObject(stack[0],false);setStatus('Selecionado: '+stack[0].name);return stack[0]}let currentIndex=stack.findIndex(o=>o.id===state.selectedId||state.selectedIds.includes(o.id));let next;if(currentIndex>=0)next=stack[(currentIndex+1)%stack.length];else next=stack[1]||stack[0];selectObject(next,false);setStatus('Camada selecionada: '+next.name+' ('+(stack.indexOf(next)+1)+' de '+stack.length+' sob o ponteiro)');showToolHint('ALT alternou para: '+next.name);return next}
function clientToWorkspace(e){const r=canvas.getBoundingClientRect();if(!r.width||!r.height)return null;return {x:(e.clientX-r.left)*(canvas.width/r.width),y:(e.clientY-r.top)*(canvas.height/r.height)}}

function selectObject(o,multi=false){if(textDirectEditSession&&(!o||o.id!==textDirectEditSession.objectId||multi))finishDirectTextEdit(true);if(!o||multi||!state.selectedIds.includes(o.id)){selectionTransformMode=false;resetInlineTextHistory()}if(!o){state.selectedId=null;state.selectedIds=[]}else if(multi){if(state.selectedIds.includes(o.id)){state.selectedIds=state.selectedIds.filter(id=>id!==o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null}else{state.selectedIds.push(o.id);state.selectedId=o.id}}else if(o.groupId){state.selectedId=o.id;state.selectedIds=state.objects.filter(item=>item.groupId===o.groupId).map(item=>item.id)}else{state.selectedId=o.id;state.selectedIds=[o.id]}const selectedNow=state.selectedIds.map(id=>state.objects.find(item=>item.id===id)).filter(Boolean),allDrawn=selectedNow.length>0&&selectedNow.every(item=>String(item.sourceType||'').indexOf('shape-')===0);if(allDrawn&&typeof showEditorTab==='function')showEditorTab('desenhar');updateObjectUI();updateSelection();updateCompareCanvas();if(o)scheduleSmartAreaGuidePreparation(o);const suggested=o&&state.selectedIds.length===1&&setRecommendedBackgroundTolerance(o);const selectedStatus=o&&state.selectedIds.length?(state.selectedIds.length>1?state.selectedIds.length+' objetos selecionados':'Selecionado: '+o.name):'Nenhum objeto selecionado';setStatus(suggested?selectedStatus+' · tolerância sugerida: '+ui.tol.value:selectedStatus)}

function imgToCanvas(img){const c=document.createElement('canvas');c.width=img.naturalWidth||img.width;c.height=img.naturalHeight||img.height;if(!c.width||!c.height)throw new Error('A imagem não possui tamanho válido.');c.getContext('2d').drawImage(img,0,0);return c}
function readU32(data,offset,little=false){return little?(data[offset]|data[offset+1]<<8|data[offset+2]<<16|data[offset+3]<<24)>>>0:((data[offset]<<24|data[offset+1]<<16|data[offset+2]<<8|data[offset+3])>>>0)}
function embeddedImageResolution(bytes){
 const has=(offset,...values)=>values.every((value,index)=>bytes[offset+index]===value);
 if(bytes.length>29&&has(0,137,80,78,71,13,10,26,10)){let offset=8;while(offset+12<=bytes.length){const length=readU32(bytes,offset),type=String.fromCharCode(bytes[offset+4],bytes[offset+5],bytes[offset+6],bytes[offset+7]);if(type==='pHYs'&&length>=9){const x=readU32(bytes,offset+8),y=readU32(bytes,offset+12),unit=bytes[offset+16];if(unit===1&&x&&y)return {x:x*.0254,y:y*.0254,embedded:true}}if(type==='IEND'||offset+12+length>bytes.length)break;offset+=12+length}}
 if(bytes.length>12&&bytes[0]===255&&bytes[1]===216){let offset=2;while(offset+4<bytes.length){if(bytes[offset]!==255){offset++;continue}const marker=bytes[offset+1];if(marker===217||marker===218)break;const length=(bytes[offset+2]<<8)|bytes[offset+3],start=offset+4;if(length<2||start+length-2>bytes.length)break;if(marker===224&&length>=14&&has(start,74,70,73,70,0)){const unit=bytes[start+7],x=(bytes[start+8]<<8)|bytes[start+9],y=(bytes[start+10]<<8)|bytes[start+11];if(x&&y&&unit)return {x:unit===2?x*2.54:x,y:unit===2?y*2.54:y,embedded:true}}offset=start+length-2}}
 return null;
}
async function getImageResolution(file){try{const bytes=new Uint8Array(await file.slice(0,262144).arrayBuffer());return embeddedImageResolution(bytes)||{x:state.dpi,y:state.dpi,embedded:false}}catch(_){return {x:state.dpi,y:state.dpi,embedded:false}}}
function imageToObject(img,name,sourceType,resolution,options={}){const c=document.createElement('canvas');c.width=img.naturalWidth||img.width;c.height=img.naturalHeight||img.height;c.getContext('2d').drawImage(img,0,0);const dpiX=Math.max(1,Number(resolution&&resolution.x)||state.dpi),dpiY=Math.max(1,Number(resolution&&resolution.y)||state.dpi),naturalW=Math.max(1,c.width*state.dpi/dpiX),naturalH=Math.max(1,c.height*state.dpi/dpiY),maxW=options.allowWorkspaceExpand?Infinity:canvas.width,maxH=options.allowWorkspaceExpand?Infinity:canvas.height,w=Math.min(maxW,naturalW),h=Math.min(maxH,naturalH);return {id:uid(),name:name||'Objeto '+(state.objects.length+1),x:0,y:0,w,h,visible:true,opacity:1,blendMode:'source-over',imageFilter:'none',imageEffect:'none',imageMask:'none',flipX:false,flipY:false,canvas:c,baseCanvas:canvasFromData(cloneCanvasData(c)),restoreCanvas:canvasFromData(cloneCanvasData(c)),originalW:c.width,originalH:c.height,sourceDpiX:dpiX,sourceDpiY:dpiY,sourceType:sourceType||'image'}}
function ensureWorkspaceFitsImported(objects){if(!objects.length)return false;const right=Math.max(...objects.map(o=>o.x+o.w)),bottom=Math.max(...objects.map(o=>o.y+o.h)),neededW=Math.max(10,Number(pxToMm(right).toFixed(2))),neededH=Math.max(10,Number(pxToMm(bottom).toFixed(2)));let changed=false;if(neededW>state.workWmm+.001){state.workWmm=Math.min(1000,neededW);changed=true}if(neededH>state.workHmm+.001){state.workHmm=Math.min(10000,neededH);changed=true}if(changed){try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:state.workWmm,h:state.workHmm,dpi:state.dpi}))}catch(_){}persistUserSettings({workspace:{w:state.workWmm,h:state.workHmm,dpi:state.dpi}})}return changed}
function addObject(o,sourceFile=null){assignAutoDisplayMode(o,sourceFile);pushHistory('Antes de adicionar objeto');if(ensureWorkspaceFitsImported([o]))syncWorkspaceUI();state.objects.push(o);normalizeObjectNames();pendingRemovalGuideIds.add(o.id);selectObject(o);render();setStatus('Objeto adicionado: '+o.name)}

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
const LOCAL_AI_SETTINGS_KEY='dtf-local-ai-model-v2:'+String(editorRoot.dataset.dtfUserId||'guest');
const LOCAL_AI_AUTO_KEY='dtf-local-ai-auto-v1:'+String(editorRoot.dataset.dtfUserId||'guest');
const LOCAL_AI_INSTALLED_KEY='dtf-local-ai-installed-v1:'+String(editorRoot.dataset.dtfUserId||'guest');
const LOCAL_AI_MODELS={
 quality:{id:'quality',engineModel:'isnet_fp16',name:'ISNet FP16 — maior precisão',description:'Preserva detalhes finos e contornos complexos, com acabamento mais limpo.',best:'Logos, cabelos, pelos e bordas detalhadas.',speed:'Mais lento · maior uso de memória',example:'precision'},
 balanced:{id:'balanced',engineModel:'isnet',name:'ISNet padrão — equilibrado',description:'Equilibra qualidade, velocidade e compatibilidade para o uso diário.',best:'Uso geral e montagem inteligente.',speed:'Velocidade média · tamanho intermediário',example:'balanced'},
 fast:{id:'fast',engineModel:'isnet_quint8',name:'ISNet quantizado — mais rápido',description:'Versão mais leve para computadores modestos e imagens simples.',best:'Fundos uniformes e prévias rápidas.',speed:'Mais rápido · menor uso de memória',example:'fast'}
};
function readLocalAiModel(){try{const saved=localStorage.getItem(LOCAL_AI_SETTINGS_KEY);return LOCAL_AI_MODELS[saved]?saved:'quality'}catch(_){return'quality'}}
function readLocalAiAuto(){try{const saved=localStorage.getItem(LOCAL_AI_AUTO_KEY);return saved===null||saved==='1'}catch(_){return true}}
function readInstalledLocalAiModels(){try{const saved=JSON.parse(localStorage.getItem(LOCAL_AI_INSTALLED_KEY)||'{}');return saved&&typeof saved==='object'?saved:{}}catch(_){return{}}}
function writeInstalledLocalAiModels(){try{localStorage.setItem(LOCAL_AI_INSTALLED_KEY,JSON.stringify(localAiInstalled))}catch(_){} }
let localAiInstalled=readInstalledLocalAiModels(),localAiInstallJob=null,localAiModelId=readLocalAiModel(),localAiAuto=readLocalAiAuto(),browserBgRemovalPromise=null,browserBgModelPromise=null,browserBgModelPromiseModelId='',browserBgModelReady=false,browserBgModelReadyId='',browserBgModelRequest=0;
function autoLocalAiModel(source){
 const canvas=source&&source.canvas?source.canvas:source;if(!canvas||!canvas.width||!canvas.height)return LOCAL_AI_MODELS.balanced;
 try{const w=Math.min(96,canvas.width),h=Math.min(96,canvas.height),tmp=document.createElement('canvas');tmp.width=w;tmp.height=h;const ctx=tmp.getContext('2d',{willReadFrequently:true});ctx.drawImage(canvas,0,0,w,h);const pixels=ctx.getImageData(0,0,w,h).data;let variation=0,edges=0,count=0,previous=null;for(let i=0;i<pixels.length;i+=16){const lum=(pixels[i]*.299+pixels[i+1]*.587+pixels[i+2]*.114);if(previous!==null){variation+=Math.abs(lum-previous);if(Math.abs(lum-previous)>32)edges++}previous=lum;count++}const complexity=(variation/Math.max(1,count))/255,edgeRate=edges/Math.max(1,count);if(complexity<.12&&edgeRate<.16)return LOCAL_AI_MODELS.fast;if(complexity>.28||edgeRate>.34||canvas.width*canvas.height>2500000)return LOCAL_AI_MODELS.quality;return LOCAL_AI_MODELS.balanced}catch(_){return LOCAL_AI_MODELS.balanced}
}
function selectedLocalAiModel(source){return localAiAuto?autoLocalAiModel(source):(LOCAL_AI_MODELS[localAiModelId]||LOCAL_AI_MODELS.quality)}
function setLocalAiModel(id){if(!LOCAL_AI_MODELS[id])return false;localAiModelId=id;localAiAuto=false;try{localStorage.setItem(LOCAL_AI_SETTINGS_KEY,id);localStorage.setItem(LOCAL_AI_AUTO_KEY,'0')}catch(_){}browserBgModelPromise=null;browserBgModelReady=false;browserBgModelReadyId='';browserBgModelRequest++;return true}
function setLocalAiAuto(value){localAiAuto=!!value;try{localStorage.setItem(LOCAL_AI_AUTO_KEY,localAiAuto?'1':'0')}catch(_){}browserBgModelPromise=null;browserBgModelReady=false;browserBgModelReadyId='';browserBgModelRequest++;}
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
function preloadBrowserBgModel(modelOverride){
 const model=modelOverride||selectedLocalAiModel(),request=browserBgModelRequest,requestedModelId=model.id;
 if(browserBgModelReady&&browserBgModelReadyId===model.id)return loadBrowserBgRemoval();
 if(browserBgModelPromise&&browserBgModelPromiseModelId!==model.id)browserBgModelPromise=null;
 if(!browserBgModelPromise){browserBgModelPromise=loadBrowserBgRemoval().then(async api=>{
  if(api.preload)await api.preload({device:navigator.gpu?'gpu':'cpu',model:model.engineModel});
  if(request===browserBgModelRequest){browserBgModelReady=true;browserBgModelReadyId=model.id}return api;
  });browserBgModelPromiseModelId=model.id;browserBgModelPromise=browserBgModelPromise.catch(error=>{browserBgModelPromise=null;browserBgModelPromiseModelId='';browserBgModelReady=false;throw error});}
 return browserBgModelPromise;
}
function warmBrowserBgModel(){
 const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
 if(connection&&connection.saveData)return;
 const warm=()=>preloadBrowserBgModel(LOCAL_AI_MODELS.balanced).catch(()=>{});
 window.setTimeout(warm,350);
}
function warmAllLocalAiModels(){
 const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;if(connection&&connection.saveData)return;
 window.setTimeout(async()=>{for(const model of Object.values(LOCAL_AI_MODELS)){if(localAiInstalled[model.id])continue;try{await preloadBrowserBgModel(model);localAiInstalled[model.id]=true;writeInstalledLocalAiModels();if(localAiSettingsModal)renderLocalAiSettings()}catch(_){break}}},900);
}
const MAGIC_GAP_MM=4,MAGIC_OBJECT_LIMIT=600;
let magicSession={token:0,mode:'removal',sources:[],prepared:[],variants:[],rawCuts:[],packing:null,stage:'idle',preparing:false,applying:false,complete:false,improved:false,variantsReady:false,autoHeight:true,cropEach:true};
const MAGIC_STEP_RANGES={source:[0,5],model:[5,28],remove:[28,70],crop:[70,78],layout:[78,88],apply:[87,100]};
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
 const visibleSteps=Array.from(ui.magicSteps.children).filter(step=>getComputedStyle(step).display!=='none'),mark=item.querySelector('.dtf-magic-step-mark'),number=Math.max(1,visibleSteps.indexOf(item)+1);
 if(mark)mark.textContent=status==='done'?'✓':status==='active'?'…':status==='error'?'!':String(number);
}
function resetMagicSteps(){['source','model','remove','crop','layout','apply'].forEach(name=>magicStep(name,''))}
function setMagicBusy(busy){
 if(!ui.magicModal||ui.magicModal.hidden)return;const locked=!!busy;
 ui.magicModal.classList.toggle('dtf-magic-busy',locked);if(ui.magicProgress){ui.magicProgress.style.left='';ui.magicProgress.style.top='';}
 ui.magicModal.querySelectorAll('.dtf-magic-upload button,.dtf-magic-sources input,.dtf-magic-sources button,.dtf-magic-variants button,.dtf-magic-auto-height input,.dtf-magic-crop-each input').forEach(control=>control.disabled=locked||control.dataset.magicBaseDisabled==='true');
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
 const removal=magicSession.mode==='removal',mounting=magicSession.mode==='montage';
 ui.magicModal.classList.toggle('dtf-magic-review-stage',stage==='review');
 ui.magicModal.classList.toggle('dtf-magic-layout-stage',stage==='layout');
 ui.magicModal.classList.toggle('dtf-magic-complete-stage',stage==='complete');
 if(ui.magicLayoutPreview&&stage!=='layout')ui.magicLayoutPreview.hidden=true;
 if(ui.magicReview)ui.magicReview.hidden=stage!=='review';
 if(ui.magicCutoutOnly){ui.magicCutoutOnly.hidden=!removal||stage!=='review';ui.magicCutoutOnly.disabled=!(removal&&stage==='review'&&magicSession.variantsReady&&!magicSession.preparing&&!magicSession.applying);ui.magicCutoutOnly.title='Aplica somente a miniatura de recorte escolhida e fecha';ui.magicCutoutOnly.setAttribute('aria-label','Aplicar somente o recorte selecionado')}
 if(ui.magicConfirm){const layoutStage=stage==='layout',reviewReady=stage==='review'&&magicSession.variantsReady&&!magicSession.preparing;ui.magicConfirm.hidden=stage==='complete'||removal;ui.magicConfirm.textContent=layoutStage?'Montar':'Avançar';ui.magicConfirm.title=layoutStage?'Cria a montagem com as quantidades e o espaçamento definidos':'Continua para definir e criar a montagem das imagens';ui.magicConfirm.setAttribute('aria-label',ui.magicConfirm.title);ui.magicConfirm.disabled=!(reviewReady||layoutStage)||magicSession.applying||!mounting}
 if(ui.magicCancel){ui.magicCancel.textContent=stage==='complete'?'Fechar':'Cancelar';ui.magicCancel.title=stage==='complete'?'Fecha esta janela':'Fecha esta janela sem alterar a área de trabalho'}
 if(ui.magicCropEachWrap)ui.magicCropEachWrap.hidden=!(mounting&&(stage==='review'||stage==='layout'));
 if(stage==='review')ui.magicMessage.textContent=removal?'Recorte pronto. Escolha a melhor versão e aplique somente a remoção de fundo.':'Recortes preparados. Escolha se cada imagem deve ser ajustada aos próprios limites antes da montagem.';
 if(stage==='layout')ui.magicMessage.textContent=magicSession.sources.length===1?'Confira a quantidade automática e monte.':'Defina as quantidades e monte.';
 if(stage==='complete')ui.magicMessage.textContent='Concluído. O resultado já está na área de trabalho.';
 setMagicBusy(magicSession.preparing||magicSession.applying);
}
function closeMagicDialog(){
 if(!ui.magicModal)return;
 const wasProcessing=magicSession.preparing||magicSession.applying;magicSession.token++;magicSession.preparing=false;magicSession.applying=false;ui.magicModal.hidden=true;ui.magicModal.classList.remove('dtf-magic-mode-removal','dtf-magic-mode-montage');if(ui.magicZoom)ui.magicZoom.hidden=true;if(ui.magicLayoutPreview)ui.magicLayoutPreview.hidden=true;if(wasProcessing){finish('Processamento cancelado');setStatus('Montagem inteligente cancelada. Nenhuma alteração foi aplicada.');}
 if(magicSession.mode==='montage'&&ui.magicLayout)ui.magicLayout.focus();else if(ui.magicFill)ui.magicFill.focus();
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
 const availableW=Math.max(170,Math.min(540,window.innerWidth-34)),availableH=Math.max(140,Math.min(430,window.innerHeight-58)),ratio=Math.max(.02,Number(frameW)||1)/Math.max(.02,Number(frameH)||1);let imageW=availableW,imageH=imageW/ratio;if(imageH>availableH){imageH=availableH;imageW=imageH*ratio}imageW=Math.round(imageW);imageH=Math.round(imageH);
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
  group.options.forEach(variant=>{const card=document.createElement('button'),thumbSize=magicThumbDimensions(group.source.w,group.source.h,70);card.type='button';card.className='dtf-magic-variant';card.style.setProperty('--dtf-magic-variant-w',thumbSize.w+'px');card.style.setProperty('--dtf-magic-variant-h',thumbSize.h+'px');const selected=group.selectedId===variant.id;card.classList.toggle('selected',selected);card.classList.toggle('recommended',!!variant.recommended);card.setAttribute('aria-pressed',selected?'true':'false');card.setAttribute('aria-label','Selecionar '+variant.label+(variant.recommended?' (padrão recomendado)':''));card.title=variant.label+(variant.recommended?' — padrão recomendado':'')+' — passe o mouse para ampliar';const image=document.createElement('img');image.src=magicPreviewData(variant.canvas,128,group.source.w,group.source.h);image.alt='';const check=document.createElement('i');check.className='dtf-magic-variant-check';check.textContent='V';const caption=document.createElement('span');caption.className='dtf-magic-variant-caption';caption.textContent=variant.label+(variant.recommended?' · Padrão':'');card.append(image,check,caption);card.addEventListener('click',()=>selectMagicVariant(sourceIndex,variant.id));card.addEventListener('pointerenter',()=>showMagicZoom(card,variant,group.source.w,group.source.h));card.addEventListener('pointerleave',hideMagicZoom);card.addEventListener('focus',()=>showMagicZoom(card,variant,group.source.w,group.source.h));card.addEventListener('blur',hideMagicZoom);list.appendChild(card)});
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
 if(magicSession.stage==='review'){if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;if(!magicSession.variantsReady||magicSession.preparing){ui.magicCapacity.className='dtf-magic-capacity analyzing';ui.magicCapacity.textContent='Finalizando as miniaturas de recorte…';ui.magicConfirm.disabled=true;if(ui.magicCutoutOnly)ui.magicCutoutOnly.disabled=true;return false}ui.magicCapacity.className='dtf-magic-capacity fits';ui.magicCapacity.textContent=magicSession.mode==='montage'?'Escolha o recorte opcional e avance para a montagem.':'Recorte pronto para revisão.';ui.magicConfirm.disabled=false;if(ui.magicCutoutOnly)ui.magicCutoutOnly.disabled=false;return true}
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
 const frameW=Math.max(1,source.canvas.width),frameH=Math.max(1,source.canvas.height),crop=magicSession.mode!=='montage'||magicSession.cropEach!==false,trim=crop?magicVariantTrim(variant):{x:0,y:0,w:frameW,h:frameH,canvas:canvasClone(variant.canvas||source.canvas)},scale=source.magicScale||1;
 return {...variant,canvas:canvasClone(trim.canvas),trim,w:source.w*trim.w/frameW*scale,h:source.h*trim.h/frameH*scale,baseW:source.w*trim.w/frameW,baseH:source.h*trim.h/frameH,source};
}
function syncMagicCropChoice(){
 if(magicSession.mode!=='montage'||!magicSession.variants.length)return;
 magicSession.prepared=magicSession.variants.map(group=>{const selected=group.options.find(option=>option.id===group.selectedId)||group.options[0];return selected?magicPreparedFromVariant(group.source,selected):null}).filter(Boolean);
 renderMagicSources();updateMagicCapacity();renderMagicLayoutPreview();
}
function magicVisualSample(source){const sample=document.createElement('canvas'),size=48;sample.width=sample.height=size;const context=sample.getContext('2d',{alpha:true,willReadFrequently:true});context.clearRect(0,0,size,size);context.drawImage(source,0,0,size,size);return context.getImageData(0,0,size,size).data}
function magicVariantDifference(first,second){const a=first.visualSample||magicVisualSample(first.canvas),b=second.visualSample||magicVisualSample(second.canvas);let sum=0;for(let index=0;index<a.length;index+=4){const alpha=Math.abs(a[index+3]-b[index+3]),color=(Math.abs(a[index]-b[index])+Math.abs(a[index+1]-b[index+1])+Math.abs(a[index+2]-b[index+2]))/3;sum+=alpha*.72+color*.28}return sum/(a.length/4)}
function magicVariantIsAlmostOriginal(source,candidate){
 const original=magicVisualSample(source.canvas),trial=candidate.visualSample||magicVisualSample(candidate.canvas);let visible=0,unchanged=0;
 for(let index=0;index<original.length;index+=4){if(original[index+3]<9)continue;visible++;const alpha=Math.abs(original[index+3]-trial[index+3]),color=(Math.abs(original[index]-trial[index])+Math.abs(original[index+1]-trial[index+1])+Math.abs(original[index+2]-trial[index+2]))/3;if(alpha<16&&color<16)unchanged++}
 return visible>0&&unchanged/visible>=.95;
}
function magicIntensityCanvas(original,removed,level){
 const out=canvasClone(original),originalData=original.getContext('2d',{willReadFrequently:true}).getImageData(0,0,original.width,original.height).data,mask=removed.getContext('2d',{willReadFrequently:true}).getImageData(0,0,removed.width,removed.height).data,context=out.getContext('2d',{willReadFrequently:true}),data=context.getImageData(0,0,out.width,out.height).data;
 for(let index=3;index<data.length;index+=4){const originalAlpha=originalData[index],maskAlpha=mask[index];if(!originalAlpha){data[index]=0;continue}let alpha=maskAlpha;
  if(level==='light')alpha=Math.max(maskAlpha,Math.round(originalAlpha*.28));
  else if(level==='invasive')alpha=maskAlpha<205?0:Math.min(originalAlpha,Math.round(maskAlpha*.92));
  data[index]=clamp(alpha,0,originalAlpha);
 }
 context.putImageData(new ImageData(data,out.width,out.height),0,0);return out;
}
function magicVariantQuality(source,candidate){
 try{const regions=analyzeMagicRegions(source.canvas,candidate.canvas),total=Math.max(1,regions.total);return (regions.exteriorRemoved/total)*1.35-(regions.interiorRemoved/total)*2.2}catch(_){return -Infinity}
}
function automaticMagicVariants(source,removed){
 const options=[],regions=analyzeMagicRegions(source.canvas,removed),mask=analyzeMagicMask(removed),paperSafe=preserveMagicLettersAndBlocks(source.canvas),photoSafe=preserveMagicPhotoFrames(source.canvas,paperSafe.canvas),meaningful=Math.max(24,regions.total*.00025),hasOutside=regions.exteriorRemoved>meaningful,hasInside=regions.interiorRemoved>meaningful;
 // As três primeiras opções têm uma ordem fixa e uma intenção clara: pouca
 // invasão, equilíbrio inteligente e remoção mais agressiva. Elas nunca são
 // descartadas por serem parecidas, pois a IA deve sempre oferecer as três.
 const normalCut=photoSafe.restored>Math.max(8,meaningful*.1)?photoSafe.canvas:(hasInside?regions.outsideCanvas:(paperSafe.removed>meaningful?paperSafe.canvas:removed));
 const required=[
  {cutout:magicIntensityCanvas(source.canvas,removed,'light'),label:'Leve — preserva mais',mode:'light'},
  {cutout:normalCut,label:'Normal — foco no fundo',mode:'normal'},
  {cutout:magicIntensityCanvas(source.canvas,removed,'invasive'),label:'Invasiva — remove mais',mode:'invasive'}
 ];
 required.forEach(item=>{let candidate;try{candidate=buildMagicVariant(source,item.cutout,item.label,item.mode)}catch(_){candidate=buildMagicVariant(source,removed,item.label,item.mode)}candidate.recommended=false;options.push(candidate)});
 // A versão normal é o padrão seguro; só trocamos a recomendação quando uma
 // outra opção realmente remove mais área externa sem invadir o interior.
 const quality=options.map(option=>magicVariantQuality(source,option)),bestIndex=quality.reduce((best,value,index)=>value>quality[best]+.0005?index:best,1);
 options.forEach((option,index)=>{option.recommended=index===bestIndex});
 const add=(cutout,label,mode,threshold=8)=>{if(options.length>=5)return null;let candidate;try{candidate=buildMagicVariant(source,cutout,label,mode)}catch(_){return null}if(magicVariantIsAlmostOriginal(source,candidate)||options.some(option=>magicVariantDifference(option,candidate)<threshold))return null;candidate.recommended=false;options.push(candidate);return candidate};
 // Opções extras continuam disponíveis no menu de variações do objeto.
 if(photoSafe.restored>Math.max(8,meaningful*.1))add(photoSafe.canvas,'Preservar fotos, letras e blocos','photo-safe',3);
 if(paperSafe.removed>meaningful)add(paperSafe.canvas,'Preservar letras e blocos','outside-paper',4);
 if(hasOutside&&hasInside)add(regions.outsideCanvas,'Somente fundo externo','outside');
 if(hasInside)add(regions.insideCanvas,'Somente fundo interno','inside');
 const complex=mask.softRatio>.0015||mask.transparentRatio>.08||hasInside;
 if(complex)add(refineMagicAlpha(source.canvas,removed,'detail'),'Mais detalhes','detail',11);
 if(mask.softRatio>.003)add(refineMagicAlpha(source.canvas,removed,'clean'),'Borda limpa','clean',12);
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
  magicProgress(5,browserBgModelReady?'IA local pronta.':'Preparando a IA local…');if(token!==magicSession.token)return false;
  modelReady=true;magicStep('model','done');magicStep('remove','active');
  const prepared=[],variants=[],rawCuts=[];
  for(let index=0;index<sources.length;index++){
   const source=sources[index],model=selectedLocalAiModel(source),api=await preloadBrowserBgModel(model),input=await canvasToBlob(source.canvas,'image/png',1),start=28+(index/sources.length)*42,span=42/sources.length;let highest=0;
   magicProgress(start,'Removendo fundo da imagem '+(index+1)+' de '+sources.length+'…');
   const output=await api.removeBackground(input,{device,model:model.engineModel,output:{format:'image/png',type:'foreground'},progress:(key,current,total)=>{if(!total||token!==magicSession.token)return;highest=Math.max(highest,current/total);magicProgress(start+highest*span,'Processando imagem '+(index+1)+' de '+sources.length+' no navegador…')}});
   if(token!==magicSession.token)return false;if(!/^image\//i.test(output.type||''))throw new Error('A IA não devolveu uma imagem válida.');
   const image=await loadBlobImage(output),removed=imgToCanvas(image);magicProgress(start+span*.9,'Criando três opções inteligentes…');const allOptions=automaticMagicVariants(source,removed),variant=allOptions.find(option=>option.recommended)||allOptions[1]||allOptions[0],options=allOptions.slice(0,3);source.magicScale=1;prepared.push(magicPreparedFromVariant(source,variant));variants.push({source,selectedId:variant.id,options,availableOptions:allOptions.slice(options.length),allOptions});rawCuts.push({source,canvas:removed});
  }
  if(token!==magicSession.token)return false;magicSession.prepared=prepared;magicSession.variants=variants;magicSession.rawCuts=rawCuts;magicSession.preparing=false;magicStep('remove','done');
  magicProgress(70,'Finalizando as miniaturas de recorte…');await nextPaint();if(token!==magicSession.token)return false;magicSession.variantsReady=true;
  if(magicSession.mode==='montage'){
   magicStep('crop','active');
   setMagicStage('review');
   updateMagicCapacity();
   magicProgress(74,'Recortes prontos. Escolha o ajuste aos limites…');
   finish('Recortes prontos');
   return true;
  }
  setMagicStage('review');renderMagicSources();renderMagicVariants();updateMagicCapacity();magicProgress(72,'Três opções de recorte prontas.');finish('Recortes prontos');return true;
 }catch(error){
  if(token!==magicSession.token)return false;magicSession.preparing=false;magicSession.prepared=[];magicStep('model',modelReady?'done':'error');magicStep('remove',modelReady?'error':'');ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='Não foi possível analisar as imagens: '+(error&&error.message?error.message:String(error));ui.magicConfirm.disabled=true;magicProgress(0,'Falha na análise. Tente novamente.');finish('Erro');log('Falha no preenchimento mágico',error&&error.message?error.message:error);return false;
 }
}
async function openMagicDialog(mode='removal'){
 if(!ui.magicModal)return;
 const workflow=mode==='montage'?'montage':'removal',removal=workflow==='removal';
 let chosen=selectedObjects();const hasObjects=state.objects.length>0;if(!chosen.length&&state.objects.length===1){selectObject(state.objects[0],false);chosen=selectedObjects()}const missing=hasObjects&&!chosen.length,token=magicSession.token+1;
 magicSession={token,mode:workflow,sources:chosen.map(object=>magicSourceFromObject(object)),prepared:[],variants:[],rawCuts:[],packing:null,stage:'idle',preparing:false,applying:false,complete:false,improved:false,cleanEdges:true,enhanceColors:false,variantsReady:false,autoHeight:true,autoHeightNeeded:false,cropEach:true};if(chosen.length>1)magicSession.sources.forEach(source=>source.quantity=1);
 ui.magicModal.classList.remove('dtf-magic-busy','dtf-magic-mode-removal','dtf-magic-mode-montage');ui.magicModal.classList.add('dtf-magic-mode-'+workflow);resetMagicSteps();ui.magicModal.classList.toggle('dtf-magic-no-selection',missing);ui.magicModal.hidden=false;
 if(ui.magicTitle){ui.magicTitle.textContent=removal?'Remoção de fundo com IA':'Montagem inteligente com IA';ui.magicTitle.setAttribute('aria-label',ui.magicTitle.textContent)}
 if(ui.magicSourceTitle)ui.magicSourceTitle.textContent=removal?'Imagem para remoção':'Imagens da montagem';
 if(ui.magicAutoHeight)ui.magicAutoHeight.checked=true;if(ui.magicCropEach)ui.magicCropEach.checked=true;if(ui.magicCropEachWrap)ui.magicCropEachWrap.hidden=true;if(ui.magicAutoHeightWrap)ui.magicAutoHeightWrap.hidden=true;setMagicStage('idle');ui.magicConfirm.hidden=removal;ui.magicConfirm.textContent='Avançar';ui.magicConfirm.disabled=true;ui.magicCancel.disabled=false;if(ui.magicProgressBar)ui.magicProgressBar.style.width='0%';if(ui.magicVariants)ui.magicVariants.innerHTML='';
 ui.magicUploadWrap.hidden=hasObjects;renderMagicSources();
 if(!hasObjects){ui.magicMessage.textContent=removal?'Envie uma imagem para remover o fundo.':'Envie uma imagem para iniciar a montagem.';ui.magicProgressText.textContent='Aguardando imagem.';ui.magicCapacity.textContent=removal?'Envie uma imagem para remover o fundo.':'Envie uma imagem para calcular as cópias.';ui.magicUploadButton.focus();return}
 if(missing){ui.magicMessage.textContent='Selecione uma ou mais imagens e abra esta função novamente.';ui.magicProgressText.textContent='Aguardando seleção.';updateMagicCapacity();ui.magicCancel.textContent='Entendi';ui.magicCancel.focus();return}
 ui.magicMessage.textContent=removal?(chosen.length===1?'A imagem será preparada para remover o fundo.':chosen.length+' imagens serão preparadas para remover o fundo.'):(chosen.length===1?'A imagem original será preparada para a montagem.':chosen.length+' imagens originais serão preparadas para a montagem.');
 prepareMagicSources();
}
async function loadMagicUpload(file){
 if(!file)return;if(!/^image\//i.test(file.type||'')&&!/\.(avif|bmp|gif|heic|heif|ico|jpe?g|png|svg|tiff?|webp)$/i.test(file.name||'')){ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='Escolha um arquivo de imagem compatível.';return}
 magicSession.preparing=true;magicStep('source','active');setMagicBusy(true);
 try{magicProgress(2,'Lendo a imagem…');let image,resolution;if(isTiffFile(file)){const pages=await decodeTiffFile(file);image=pages[0].canvas;resolution=pages[0].resolution}else [image,resolution]=await Promise.all([loadBlobImage(file),getImageResolution(file)]);if(ui.magicModal.hidden)return;const object=imageToObject(image,(file.name||'imagem').replace(/\.[^.]+$/,''),'magic-upload',resolution);object.id=uid();magicSession.sources=[magicSourceFromObject(object,true)];magicSession.prepared=[];magicSession.variants=[];magicSession.rawCuts=[];magicSession.variantsReady=false;magicSession.packing=null;ui.magicMessage.textContent=magicSession.mode==='removal'?'Imagem pronta para remoção do fundo.':'Imagem pronta para a montagem.';magicStep('source','done');magicSession.preparing=false;setMagicBusy(false);renderMagicSources();await prepareMagicSources()}catch(error){magicSession.preparing=false;setMagicBusy(false);ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='Não foi possível abrir a imagem: '+(error&&error.message?error.message:String(error));magicProgress(0,'Falha ao abrir a imagem.');finish('Erro')}
}
function advanceMagicLayout(){
 if(magicSession.stage!=='review'||magicSession.prepared.length!==magicSession.sources.length)return;hideMagicZoom();magicStep('crop','done');setMagicStage('layout');renderMagicSources();magicStep('layout','active');magicProgress(82,'Calculando a montagem…');updateMagicCapacity();renderMagicLayoutPreview();
}
function finishMagicResult(message,status){
 magicSession.complete=true;magicSession.stage='complete';magicProgress(100,message);finish(message);setStatus(status);hideMagicZoom();if(ui.magicLayoutPreview)ui.magicLayoutPreview.hidden=true;ui.magicModal.hidden=true;ui.magicModal.classList.remove('dtf-magic-mode-removal','dtf-magic-mode-montage');if(magicSession.mode==='montage'&&ui.magicLayout)ui.magicLayout.focus();else if(ui.magicFill)ui.magicFill.focus();
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
 if(magicSession.mode!=='removal'||magicSession.stage!=='review'||!magicSession.variantsReady||magicSession.prepared.length!==magicSession.sources.length||magicSession.applying)return;clearError();magicSession.applying=true;ui.magicConfirm.disabled=true;ui.magicCutoutOnly.disabled=true;setMagicBusy(true);
 try{
  magicStep('apply','active');magicProgress(88,'Aplicando o recorte escolhido…');const variants=magicVariantRegistry(),groupId=magicSession.prepared.length>1?uid():null,objects=magicSession.prepared.map((item,index)=>{const source=item.source,artwork=applyMagicFinishing(item.canvas),x=clamp(source.object.x,0,Math.max(0,canvas.width-item.w)),y=clamp(source.object.y,0,Math.max(0,canvas.height-item.h));return {id:uid(),name:source.name+' — recorte',x,y,w:item.w,h:item.h,visible:true,opacity:source.object.opacity==null?1:source.object.opacity,locked:false,groupId,canvas:artwork,baseCanvas:canvasClone(artwork),aiOriginalCanvas:canvasClone(source.canvas),aiOriginalW:source.w,aiOriginalH:source.h,aiFrameW:source.canvas.width,aiFrameH:source.canvas.height,aiTrimW:item.trim.w,aiTrimH:item.trim.h,originalW:artwork.width,originalH:artwork.height,sourceType:'ai-cutout',intelligentVariantSetId:variants.ids[index],intelligentVariantId:item.id,intelligentVariantRotated:false}});normalizeObjectNames(objects);pushHistory('Antes de aplicar o recorte da IA');state.intelligentVariantSets=variants.registry;state.objects=objects;state.selectedIds=objects.map(object=>object.id);state.selectedId=objects[0]?.id||null;state.transparent=true;if(ui.transparent)ui.transparent.checked=true;render();updateSelection();updateObjectUI();magicStep('apply','done');finishMagicResult('Recorte aplicado.',objects.length+' recorte'+(objects.length===1?' aplicado':'s aplicados'));
 }catch(error){magicStep('apply','error');magicProgress(72,'Falha ao aplicar. O projeto foi preservado.');showError('Falha ao aplicar recorte',error)}finally{magicSession.applying=false;setMagicBusy(false);ui.magicCancel.disabled=false}
}
async function runMagicFill(){
 if(magicSession.mode!=='montage')return;
 if(magicSession.complete){closeMagicDialog();return}
 if(magicSession.stage==='review'){if(!magicSession.variantsReady||magicSession.preparing)return;advanceMagicLayout();return}
 if(magicSession.stage!=='layout')return;
 if(magicSession.preparing||!updateMagicCapacity())return;
 const packing=currentMagicPacking();if(!packing||!packing.fits)return;const token=++magicSession.token;clearError();magicSession.applying=true;magicSession.stage='applying';ui.magicConfirm.disabled=true;if(ui.magicLayout){ui.magicLayout.disabled=true;ui.magicLayout.setAttribute('aria-busy','true')}magicStep('layout','active');setMagicBusy(true);
 try{
  magicProgress(84,'Montagem calculada. Preparando as cópias…');magicStep('layout','done');magicStep('apply','active');const variants=magicVariantRegistry(),objects=[],rotatedCache=new Map(),sourceCounts=new Map();
  for(let index=0;index<packing.placements.length;index++){
   if(token!==magicSession.token)return;
   const place=packing.placements[index],prepared=magicSession.prepared[place.sourceIndex];if(!prepared)throw new Error('A montagem encontrou uma imagem sem recorte preparado.');let sourceCanvas=applyMagicFinishing(prepared.canvas);if(place.rotated){if(!rotatedCache.has(place.sourceIndex))rotatedCache.set(place.sourceIndex,rotateCanvas90(sourceCanvas));sourceCanvas=rotatedCache.get(place.sourceIndex)}const artwork=canvasClone(sourceCanvas),base=canvasClone(sourceCanvas),count=(sourceCounts.get(place.sourceIndex)||0)+1;sourceCounts.set(place.sourceIndex,count);objects.push({id:uid(),name:prepared.source.name+' '+count+(place.rotated?' — 90°':''),x:place.x,y:place.y,w:place.w,h:place.h,visible:true,opacity:prepared.source.object.opacity==null?1:prepared.source.object.opacity,locked:false,groupId:null,canvas:artwork,baseCanvas:base,aiOriginalCanvas:canvasClone(prepared.source.canvas),aiOriginalW:prepared.source.w,aiOriginalH:prepared.source.h,aiFrameW:prepared.source.canvas.width,aiFrameH:prepared.source.canvas.height,aiTrimW:prepared.trim.w,aiTrimH:prepared.trim.h,originalW:artwork.width,originalH:artwork.height,sourceType:place.rotated?'ai-cutout-rotated':'ai-cutout',intelligentVariantSetId:variants.ids[place.sourceIndex],intelligentVariantId:prepared.id,intelligentVariantRotated:place.rotated});if(index&&index%25===0){magicProgress(86+Math.round(index/packing.placements.length*10),'Criando cópia '+(index+1)+' de '+packing.placements.length+'…');await new Promise(resolve=>requestAnimationFrame(resolve))}
  }
  if(token!==magicSession.token)return;normalizeObjectNames(objects);pushHistory('Antes da montagem inteligente');if(packing.autoHeight){state.workHmm=Math.max(10,packing.requiredHeight*25.4/state.dpi);syncWorkspaceUI()}state.intelligentVariantSets=variants.registry;state.objects=objects;state.selectedId=objects.length?objects[0].id:null;state.selectedIds=state.selectedId?[state.selectedId]:[];state.transparent=true;if(ui.transparent)ui.transparent.checked=true;render();updateSelection();updateObjectUI();magicStep('apply','done');finishMagicResult('Montagem concluída.',packing.total+' objeto'+(packing.total===1?' criado':'s criados')+' — separados com 4 mm'+(packing.autoHeight?' · altura ajustada para '+state.workHmm.toFixed(1)+' mm':''));
 }catch(error){if(token!==magicSession.token)return;magicSession.stage='layout';magicStep('apply','error');ui.magicCapacity.className='dtf-magic-capacity no-fit';ui.magicCapacity.textContent='A montagem não foi aplicada: '+(error&&error.message?error.message:String(error));magicProgress(82,'Falha ao aplicar. O projeto foi preservado.');ui.magicConfirm.disabled=false;finish('Erro');log('Falha ao aplicar montagem inteligente',error&&error.message?error.message:error)}finally{magicSession.applying=false;setMagicBusy(false);if(ui.magicLayout){ui.magicLayout.disabled=false;ui.magicLayout.removeAttribute('aria-busy')}}
}
if(ui.magicFill)ui.magicFill.addEventListener('click',()=>openMagicDialog('removal'));
if(ui.magicLayout)ui.magicLayout.addEventListener('click',()=>openMagicDialog('montage'));
if(ui.magicCropEach)ui.magicCropEach.addEventListener('change',()=>{magicSession.cropEach=ui.magicCropEach.checked;syncMagicCropChoice();setStatus('Recorte '+(magicSession.cropEach?'ativado':'desativado')+' para todas as imagens da montagem.')});
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
function addImportedObjects(objects,label){if(!objects.length)return;objects.forEach(assignAutoDisplayMode);pushHistory('Antes de importar '+label);if(ensureWorkspaceFitsImported(objects))syncWorkspaceUI();state.objects.push(...objects);normalizeObjectNames();objects.forEach(o=>{pendingRemovalGuideIds.add(o.id);scheduleSmartAreaGuidePreparation(o)});state.selectedIds=objects.map(o=>o.id);state.selectedId=objects[objects.length-1].id;render();updateSelection();updateObjectUI();fitWidthZoom(false)}
async function loadFile(f){clearError();if(!f)return;try{progress(5,'Lendo arquivo...');setStatus('Carregando '+(f.name||'imagem')+'...');if(/\.pdf$/i.test(f.name)||f.type==='application/pdf'){state.pdfFileName=f.name.replace(/\.pdf$/i,'');const bytes=await f.arrayBuffer();state.pdf=await openPdf(bytes);state.pdfDpi=parseInt(ui.pdfDpi.value,10)||300;hideProgress();setStatus('PDF aberto — escolha as páginas para importar');const pages=await choosePdfPages(state.pdf.numPages);if(!pages||!pages.length){state.pdf=null;hideProgress();setStatus('Importação do PDF cancelada');return}progress(18,'Preparando importação do PDF...');state.pdfPage=pages[0];const imported=[],gap=physicalMmToPx(4);let y=0;for(let index=0;index<pages.length;index++){const pageNo=pages[index];progress(20+Math.round(index/pages.length*65),'Preparando página '+pageNo+' de '+pages.length+'...');const blob=await convertPdfPage(state.pdf,pageNo,state.pdfDpi),img=await loadBlobImage(blob),obj=imageToObject(img,state.pdfFileName+'_pagina_'+pageNo,'pdf-png',{x:state.pdfDpi,y:state.pdfDpi},{allowWorkspaceExpand:true});obj.pdfPage=pageNo;obj.pdfPages=state.pdf.numPages;obj.pdfDpi=state.pdfDpi;obj.x=0;obj.y=y;imported.push(obj);y+=obj.h+gap;await nextPaint()}if(imported.length)y-=gap;addImportedObjects(imported,'PDF');ui.pdfPage.innerHTML=Array.from({length:state.pdf.numPages},(_,index)=>'<option value="'+(index+1)+'">Página '+(index+1)+'</option>').join('');ui.pdfPage.value=String(state.pdfPage);ui.pdfPage.disabled=false;ui.pdfConvert.disabled=false;finish('PDF importado');setStatus(pages.length+' página'+(pages.length===1?'':'s')+' do PDF importada'+(pages.length===1?'':'s')+' em sequência vertical; área ajustada quando necessário');return}
 if(isTiffFile(f)){const pages=await decodeTiffFile(f),name=(f.name||'imagem').replace(/\.[^.]+$/,''),imported=[],gap=physicalMmToPx(4);let y=0;for(let index=0;index<pages.length;index++){const page=pages[index],pageName=pages.length>1?name+'_pagina_'+(index+1):name,obj=imageToObject(page.canvas,pageName,'tiff',page.resolution,{allowWorkspaceExpand:true});obj.x=0;obj.y=y;imported.push(obj);y+=obj.h+gap}addImportedObjects(imported,'TIFF');ui.pdfPage.innerHTML='<option value="1">1</option>';ui.pdfPage.disabled=true;ui.pdfConvert.disabled=true;state.pdf=null;finish('TIFF importado');setStatus(pages.length+' página'+(pages.length===1?'':'s')+' do TIFF importada'+(pages.length===1?'':'s')+' com transparência, orientação e DPI preservados');return}
 const [img,resolution]=await Promise.all([loadBlobImage(f),getImageResolution(f)]);const obj=imageToObject(img,(f.name||'imagem').replace(/\.[^.]+$/,''),'image',resolution,{allowWorkspaceExpand:true});if(!obj.canvas.width||!obj.canvas.height)throw new Error('A imagem foi lida, mas não tem dimensões válidas.');addObject(obj,f);render();fitWidthZoom(false);ui.pdfPage.innerHTML='<option value="1">1</option>';ui.pdfPage.disabled=true;ui.pdfConvert.disabled=true;state.pdf=null;finish('Imagem carregada');setStatus('Imagem carregada — prévia '+(obj.autoDisplayMode==='original'?'em qualidade original':obj.autoDisplayMode==='balanced'?'equilibrada':obj.autoDisplayMode==='fast'?'rápida':'muito rápida')+'; o arquivo original será preservado ao salvar/exportar')}catch(e){finish('Erro');const detail=new Error((e&&e.message?e.message:String(e))+' Formatos aceitos: PNG, JPEG, WebP, TIFF e PDF.');showError('Falha ao carregar arquivo',detail)}}

fileInput.addEventListener('change',async e=>{const files=Array.from(e.target.files||[]);if(!files.length){setStatus('Nenhum arquivo selecionado');return}for(let i=0;i<files.length;i++){setStatus('Lendo '+files[i].name+' ('+(i+1)+'/'+files.length+')…');await loadFile(files[i])}fileInput.value=''});
function chooseEditorFile(){fileInput.value='';fileInput.click();}
if(ui.load)ui.load.addEventListener('click',chooseEditorFile);
if(ui.loadEdit)ui.loadEdit.addEventListener('click',chooseEditorFile);
fileInput.addEventListener('change',event=>{const files=Array.from(event.target.files||[]);if(files.length)rememberImportedFormat(files[files.length-1])});
function canvasToProjectData(c){if(!c||!c.width||!c.height)throw new Error('Objeto sem imagem válida para salvar.');return c.toDataURL('image/png')}
function projectData(){return {format:'PrintWay Editor Project',formatVersion:2,appVersion:VERSION,savedAt:new Date().toISOString(),workspace:{widthMm:state.workWmm,heightMm:state.workHmm,dpi:state.dpi,bgColor:state.bgColor,transparent:state.transparent,unit:unitSelect?unitSelect.value:'mm'},guideMargins:{...guideMargins},guides:state.customGuides.map(guide=>({id:guide.id,orientation:guide.orientation,valueMm:guide.valueMm})),selectedId:state.selectedId,selectedIds:[...(state.selectedIds||[])],intelligentVariantSets:Object.fromEntries(Object.entries(state.intelligentVariantSets||{}).map(([id,set])=>[id,{id,options:(set.options||[]).map(option=>({id:option.id,label:option.label,mode:option.mode,w:option.w,h:option.h,trim:option.trim?{...option.trim}:null,canvas:canvasToProjectData(option.canvas)}))}])),objects:state.objects.map(o=>({id:o.id,name:o.name,x:o.x,y:o.y,w:o.w,h:o.h,visible:o.visible!==false,opacity:o.opacity==null?1:o.opacity,locked:o.locked===true,sourceType:o.sourceType||'image',groupId:o.groupId||null,originalW:o.originalW||o.canvas.width,originalH:o.originalH||o.canvas.height,aiOriginalW:o.aiOriginalW||null,aiOriginalH:o.aiOriginalH||null,aiFrameW:o.aiFrameW||null,aiFrameH:o.aiFrameH||null,aiTrimW:o.aiTrimW||null,aiTrimH:o.aiTrimH||null,pdfPage:o.pdfPage||null,pdfPages:o.pdfPages||null,pdfDpi:o.pdfDpi||null,intelligentVariantSetId:o.intelligentVariantSetId||null,intelligentVariantId:o.intelligentVariantId||null,intelligentVariantRotated:o.intelligentVariantRotated===true,shapeFillColor:o.shapeFillColor||null,shapeStrokeColor:o.shapeStrokeColor||null,shapeStrokeWidth:o.shapeStrokeWidth!=null&&o.shapeStrokeWidth!==''&&Number.isFinite(Number(o.shapeStrokeWidth))?Number(o.shapeStrokeWidth):null,shapeCornerRadii:{...objectShapeCornerRadii(o)},rotation:Number(o.rotation)||0,skewX:Number(o.skewX)||0,skewY:Number(o.skewY)||0,skewAnchorX:(o.skewAnchorX==='left'||o.skewAnchorX==='right'?o.skewAnchorX:'center'),skewAnchorY:(o.skewAnchorY==='top'||o.skewAnchorY==='bottom'?o.skewAnchorY:'center'),textContent:o.textContent||'',textFontFamily:o.textFontFamily||'Roboto',textFontSize:Number(o.textFontSize)||32,textColor:o.textColor||'#000000',textAlign:o.textAlign||'center',textBold:o.textBold===true,textItalic:o.textItalic===true,textPadding:Number(o.textPadding)||8,canvas:canvasToProjectData(o.canvas),baseCanvas:canvasToProjectData(o.baseCanvas||o.canvas),restoreCanvas:canvasToProjectData(o.restoreCanvas||o.baseCanvas||o.canvas),aiOriginalCanvas:o.aiOriginalCanvas?canvasToProjectData(o.aiOriginalCanvas):null}))}}
const projectDataOriginal=projectData;projectData=function(){const data=projectDataOriginal();if(Array.isArray(data.objects))data.objects.forEach((item,index)=>{const source=state.objects[index];if(!source)return;item.blendMode=source.blendMode||'source-over';item.imageFilter=source.imageFilter||'none';item.imageEffect=source.imageEffect||'none';item.imageMask=source.imageMask||'none';item.flipX=source.flipX===true;item.flipY=source.flipY===true});return data};
const projectPickerTypes=[{description:'Projeto do Editor PrintWay',accept:{'application/json':['.pwedit']}}];
async function writeProjectFile(handle,blob){const writable=await handle.createWritable();try{await writable.write(blob);await writable.close()}catch(e){try{await writable.abort()}catch(_){}throw e}}
async function saveEditorProject(){try{progress(8,'Preparando projeto...');const payload=JSON.stringify(projectData());const blob=new Blob([payload],{type:'application/x-printway-editor-project'});const stamp=new Date().toISOString().replace(/[:.]/g,'-').replace('T','_').slice(0,19),base=(currentProjectName||('projeto_printway_'+stamp)).replace(/[^a-z0-9_-]+/gi,'_');let savedInPlace=false;if(currentProjectHandle&&typeof currentProjectHandle.createWritable==='function'){await writeProjectFile(currentProjectHandle,blob);savedInPlace=true}else if(window.isSecureContext&&typeof window.showSaveFilePicker==='function'){const handle=await window.showSaveFilePicker({suggestedName:base+'.pwedit',types:projectPickerTypes,excludeAcceptAllOption:false});await writeProjectFile(handle,blob);currentProjectHandle=handle;currentProjectName=String(handle.name||base).replace(/\.pwedit$/i,'');savedInPlace=true}else{saveBlob(blob,base+'.pwedit');currentProjectName=base}syncMainColorScope(currentProjectName||'novo');clearAutosave();setProjectDirty(false);finish('Projeto salvo');setStatus(savedInPlace?'Projeto salvo no mesmo arquivo local':'Projeto .pwedit baixado localmente');return true}catch(e){if(e&&e.name==='AbortError'){finish('Cancelado');setStatus('Salvamento cancelado');return false}finish('Erro');showError('Falha ao salvar projeto',e);return false}}
function canvasFromProjectData(data){return new Promise((resolve,reject)=>{if(typeof data!=='string'||!data.startsWith('data:image/')){reject(new Error('Imagem do projeto inválida.'));return}const image=new Image();image.onload=()=>{try{const c=document.createElement('canvas');c.width=image.naturalWidth||image.width;c.height=image.naturalHeight||image.height;if(!c.width||!c.height)throw new Error('Imagem do projeto sem dimensões.');c.getContext('2d').drawImage(image,0,0);resolve(c)}catch(e){reject(e)}};image.onerror=()=>reject(new Error('Não foi possível ler uma imagem do projeto.'));image.src=data})}
function confirmUnsavedChanges(action){if(!projectDirty)return Promise.resolve('discard');return new Promise(resolve=>{const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-unsaved-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','dtfUnsavedTitle');modal.innerHTML='<div class="dtf-properties-box dtf-unsaved-box"><h3 id="dtfUnsavedTitle">Alterações não salvas</h3><p>O projeto atual foi alterado. Deseja salvá-lo antes de '+action+'?</p><div class="dtf-properties-actions"><button type="button" data-cancel>Cancelar</button><button type="button" data-discard>Não salvar</button><button type="button" data-save>Salvar</button></div></div>';document.body.appendChild(modal);let closed=false;const done=value=>{if(closed)return;closed=true;modal.remove();resolve(value)};modal.querySelector('[data-cancel]').onclick=()=>done('cancel');modal.querySelector('[data-discard]').onclick=()=>done('discard');modal.querySelector('[data-save]').onclick=async()=>{const button=modal.querySelector('[data-save]');button.disabled=true;button.textContent='Salvando…';if(await saveEditorProject())done('saved');else{button.disabled=false;button.textContent='Salvar'}};modal.addEventListener('pointerdown',e=>{if(e.target===modal)done('cancel')});modal.addEventListener('keydown',e=>{if(e.key==='Escape')done('cancel')});modal.querySelector('[data-save]').focus()})}
async function openEditorProject(file,handle=null){if(!file)return false;const decision=await confirmUnsavedChanges('abrir outro projeto');if(decision==='cancel')return false;try{progress(8,'Abrindo projeto...');const raw=await file.text(),data=JSON.parse(raw);if(!data||data.format!=='PrintWay Editor Project'||!Array.isArray(data.objects))throw new Error('Arquivo não é um projeto .pwedit válido.');const workspace=data.workspace||{},savedWidth=Number(workspace.widthMm??data.workWmm),savedHeight=Number(workspace.heightMm??data.workHmm),width=clamp(Number.isFinite(savedWidth)&&savedWidth>0?savedWidth:280,10,1000),height=clamp(Number.isFinite(savedHeight)&&savedHeight>0?savedHeight:100,10,10000),dpi=Math.max(1,Number(workspace.dpi)||300),intelligentVariantSets={};for(const [setId,set] of Object.entries(data.intelligentVariantSets||{})){const options=[];for(const option of set.options||[])options.push({id:String(option.id||uid()),label:String(option.label||'Recorte inteligente'),mode:String(option.mode||'standard'),w:Number(option.w)||1,h:Number(option.h)||1,trim:option.trim&&Number(option.trim.w)>0&&Number(option.trim.h)>0?{x:Number(option.trim.x)||0,y:Number(option.trim.y)||0,w:Number(option.trim.w),h:Number(option.trim.h)}:null,canvas:await canvasFromProjectData(option.canvas)});if(options.length)intelligentVariantSets[setId]={id:setId,options}}const objects=[];for(let i=0;i<data.objects.length;i++){const item=data.objects[i];progress(15+Math.round(i/Math.max(1,data.objects.length)*55),'Carregando objeto '+(i+1)+' de '+data.objects.length+'...');const c=await canvasFromProjectData(item.canvas),base=await canvasFromProjectData(item.baseCanvas||item.canvas),aiOriginal=item.aiOriginalCanvas?await canvasFromProjectData(item.aiOriginalCanvas):null;objects.push({id:String(item.id||uid()),name:String(item.name||'Objeto '+(i+1)),x:Number(item.x)||0,y:Number(item.y)||0,w:Number(item.w)||c.width,h:Number(item.h)||c.height,visible:item.visible!==false,opacity:clamp(Number(item.opacity==null?1:item.opacity),0,1),locked:item.locked===true,sourceType:item.sourceType||'image',groupId:item.groupId||null,originalW:Number(item.originalW)||c.width,originalH:Number(item.originalH)||c.height,aiOriginalW:Number(item.aiOriginalW)||null,aiOriginalH:Number(item.aiOriginalH)||null,aiFrameW:Number(item.aiFrameW)||null,aiFrameH:Number(item.aiFrameH)||null,aiTrimW:Number(item.aiTrimW)||null,aiTrimH:Number(item.aiTrimH)||null,pdfPage:item.pdfPage||null,pdfPages:item.pdfPages||null,pdfDpi:item.pdfDpi||null,intelligentVariantSetId:item.intelligentVariantSetId&&intelligentVariantSets[item.intelligentVariantSetId]?item.intelligentVariantSetId:null,intelligentVariantId:item.intelligentVariantId||null,intelligentVariantRotated:item.intelligentVariantRotated===true,shapeFillColor:item.shapeFillColor||null,shapeStrokeColor:item.shapeStrokeColor||null,shapeStrokeWidth:item.shapeStrokeWidth!=null&&item.shapeStrokeWidth!==''&&Number.isFinite(Number(item.shapeStrokeWidth))?Number(item.shapeStrokeWidth):null,shapeCornerRadii:normalizeShapeCornerRadii(item.shapeCornerRadii,c.width,c.height),rotation:Number(item.rotation)||0,skewX:Number(item.skewX)||0,skewY:Number(item.skewY)||0,skewAnchorX:(item.skewAnchorX==='left'||item.skewAnchorX==='right'?item.skewAnchorX:'center'),skewAnchorY:(item.skewAnchorY==='top'||item.skewAnchorY==='bottom'?item.skewAnchorY:'center'),textContent:String(item.textContent||''),textFontFamily:String(item.textFontFamily||'Roboto'),textFontSize:Number(item.textFontSize)||32,textColor:String(item.textColor||'#000000'),textAlign:['left','center','right'].includes(String(item.textAlign||''))?String(item.textAlign):'center',textBold:item.textBold===true,textItalic:item.textItalic===true,textPadding:Number(item.textPadding)||8,canvas:c,baseCanvas:base,aiOriginalCanvas:aiOriginal})}state.historyLock=true;state.workWmm=width;state.workHmm=height;state.dpi=dpi;state.bgColor=typeof workspace.bgColor==='string'?workspace.bgColor:'#ffffff';state.transparent=workspace.transparent!==false;state.intelligentVariantSets=intelligentVariantSets;state.objects=objects;state.selectedIds=(Array.isArray(data.selectedIds)?data.selectedIds:[]).filter(id=>state.objects.some(o=>o.id===id));state.selectedId=state.objects.some(o=>o.id===data.selectedId)?data.selectedId:(state.selectedIds[state.selectedIds.length-1]||null);state.undo=[];state.redo=[];state.pdf=null;if(unitSelect&&['mm','cm','m','px'].includes(workspace.unit))unitSelect.value=workspace.unit;state.historyLock=false;syncWorkspaceUI();state.objects.forEach(o=>{o.w=clamp(o.w,1,canvas.width);o.h=clamp(o.h,1,canvas.height);o.x=clamp(o.x,0,Math.max(0,canvas.width-o.w));o.y=clamp(o.y,0,Math.max(0,canvas.height-o.h));if(isTextShape(o))syncTextBoxCanvasSize(o,o.w,o.h)});currentProjectName=String(file.name||'projeto').replace(/\.pwedit$/i,'');currentProjectHandle=handle;fitWidthZoom(false);render();updateSelection();updateObjectUI();updateHistoryUI();setProjectDirty(false);finish('Projeto aberto');setStatus('Projeto .pwedit aberto com '+state.objects.length+' objeto(s)');return true}catch(e){state.historyLock=false;finish('Erro');showError('Falha ao abrir projeto',e);return false}}
// Mantém as margens personalizadas ao abrir projetos antigos ou novos.
const openEditorProjectBase=openEditorProject;
openEditorProject=async function(file,handle=null){const result=await openEditorProjectBase(file,handle);if(result){try{const data=JSON.parse(await file.text());guideMargins=sanitizeGuideMargins(data&&data.guideMargins||guideMargins);updateGuidesOverlay()}catch(_){}}return result};
const openEditorProjectWithFinalColorScope=openEditorProject;
openEditorProject=async function(file,handle=null){const result=await openEditorProjectWithFinalColorScope(file,handle);if(result)syncMainColorScope(currentProjectName||'novo');return result};
const newEditorProjectBase=newEditorProject;
newEditorProject=async function(){const hadObjects=state.objects.length;const result=await newEditorProjectBase();if(hadObjects&&state.objects.length===0){guideMargins={top:5,right:5,bottom:5,left:5};updateGuidesOverlay()}return result};
async function offerAutosaveRecovery(){
 if(autosaveRecoveryShown)return false;autosaveRecoveryShown=true;
 const saved=await readAutosave();if(!saved)return false;
 try{
  const file=new File([JSON.stringify(saved.project)],'recuperacao-automatica.pwedit',{type:'application/json'});
  const opened=await openEditorProject(file);
  if(opened){
   setProjectDirty(true);
   showEditorTab('arquivo');
   setStatus('Projeto recuperado pelo salvamento de proteção.');
   if(typeof showToolHint==='function')showToolHint('Arquivo de proteção recuperado e aberto automaticamente.');
   window.setTimeout(()=>{if(statusEl&&statusEl.textContent==='Projeto recuperado pelo salvamento de proteção.')setStatus('Pronto')},2600);
   return true
  }
 }catch(error){
  log('Falha ao recuperar salvamento automático',error&&error.message?error.message:error);
  if(typeof showToolHint==='function')showToolHint('Não foi possível recuperar o arquivo de proteção.');
 }
 return false
}
async function newEditorProject(){const decision=await confirmUnsavedChanges('criar um novo projeto');if(decision==='cancel')return;state.historyLock=true;state.workWmm=280;state.workHmm=100;state.dpi=300;state.bgColor='#ffffff';state.transparent=true;state.objects=[];state.intelligentVariantSets={};state.selectedId=null;state.selectedIds=[];state.zoom=1;state.panX=0;state.panY=0;state.drag=null;state.marquee=null;state.tool='select';state.undo=[];state.redo=[];state.clipboard=[];state.pdf=null;state.pdfFileName='documento';state.pdfPage=1;state.pdfDpi=300;state.historyLock=false;autoToleranceSuggestedIds.clear();currentProjectName='';currentProjectHandle=null;propertyClipboard=null;guidesEnabled=false;if(inheritPaste)inheritPaste.disabled=true;if(pastePropMenu)pastePropMenu.style.display='none';if(unitSelect)unitSelect.value='mm';if(ui.eraser)ui.eraser.classList.remove('active');if(ui.restoreBrush)ui.restoreBrush.classList.remove('active');[clickRemove,areaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton,fillTool].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});guidesButton.classList.remove('active');guidesButton.setAttribute('aria-pressed','false');updateGuidesOverlay();editorRoot.classList.remove('dtf-color-remove-cursor');try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:280,h:100,dpi:300}));localStorage.setItem('printway_dtf_unit','mm')}catch(_){}clearAutosave();syncWorkspaceUI();fitWidthZoom(false);render();updateSelection();updateObjectUI();updateHistoryUI();setProjectDirty(false);setStatus('Novo projeto criado')}
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
 [clickRemove,areaRemoveButton,outerAreaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton,fillTool].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});
 guidesButton.classList.remove('active');guidesButton.setAttribute('aria-pressed','false');updateGuidesOverlay();renderCustomGuides();editorRoot.classList.remove('dtf-color-remove-cursor');
 try{localStorage.setItem('printway_dtf_workspace',JSON.stringify({w:280,h:100,dpi:300}));localStorage.setItem('printway_dtf_unit','mm')}catch(_){}
 persistUserSettings({workspace:{w:280,h:100,dpi:300}});
 clearAutosave();syncWorkspaceUI();fitWidthZoom(false);render();updateSelection();updateObjectUI();updateHistoryUI();setProjectDirty(false);setStatus('Novo projeto criado');
};
const projectInput=$id('dtfProjectInput');
async function chooseProjectFile(){if(window.isSecureContext&&typeof window.showOpenFilePicker==='function'){try{const handles=await window.showOpenFilePicker({multiple:false,types:projectPickerTypes,excludeAcceptAllOption:false}),handle=handles&&handles[0];if(handle){const file=await handle.getFile();await openEditorProject(file,handle)}return}catch(e){if(e&&e.name==='AbortError')return}}if(projectInput){projectInput.value='';projectInput.click()}}
if(projectInput)projectInput.addEventListener('change',e=>{const file=e.target.files&&e.target.files[0];if(file)openEditorProject(file);projectInput.value=''})
if(ui.newProject)ui.newProject.addEventListener('click',newEditorProject);
if(ui.openProject)ui.openProject.addEventListener('click',chooseProjectFile);
if(ui.saveProject)ui.saveProject.addEventListener('click',saveEditorProject);
window.addEventListener('beforeunload',e=>{if(editorRoot.closest('#dtf-uv-editor-admin'))return;writeAutosave(true);if(suppressUnsavedBeforeUnload||!projectDirty)return;e.preventDefault();e.returnValue=''})

async function replaceSelectedWithPdfPage(){if(!state.pdf)return;if(!selected()){setStatus('Nenhum objeto selecionado');return}try{pushHistory('Antes de reconverter página PDF');progress(8,'Convertendo página PDF...');const blob=await convertPdfPage(state.pdf,state.pdfPage,state.pdfDpi);const img=await loadBlobImage(blob);const o=selected();const fresh=imageToObject(img,o.name,'pdf-png',{x:state.pdfDpi,y:state.pdfDpi});o.canvas=fresh.canvas;o.baseCanvas=fresh.baseCanvas;o.restoreCanvas=canvasFromData(cloneCanvasData(o.baseCanvas||o.canvas));o.w=fresh.w;o.h=fresh.h;o.x=(canvas.width-o.w)/2;o.y=(canvas.height-o.h)/2;render();finish('Página PDF atualizada');setStatus('Página '+state.pdfPage+' atualizada em '+state.pdfDpi+' DPI')}catch(e){finish('Erro');showError('Falha ao reconverter página PDF',e)}}

ui.applyWork.addEventListener('click',()=>{
  const nw=clamp(unitToMm(parseFloat(ui.workW.value)||280),10,1000);
  const nh=clamp(unitToMm(parseFloat(ui.workH.value)||100),10,10000);
  const nd=parseInt(ui.dpi.value,10)||300;
  if(nw===state.workWmm&&nh===state.workHmm&&nd===state.dpi){
    fitWidthZoom(false);render();
    syncCustomWorkspaceFields(false);
    setStatus('Área de trabalho ajustada à largura');
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
  persistUserSettings({workspace:{w:nw,h:nh,dpi:nd}});

  syncWorkspaceUI();
  fitWidthZoom(false);
  render();
  syncCustomWorkspaceFields(false);
  setStatus('Área de trabalho alterada e ajustada à largura: objetos mantidos no mesmo tamanho e posição');
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
      o.canvas=constFresh.canvas;o.baseCanvas=constFresh.baseCanvas;o.restoreCanvas=canvasFromData(cloneCanvasData(o.baseCanvas||o.canvas));o.w=constFresh.w;o.h=constFresh.h;o.x=(canvas.width-o.w)/2;o.y=(canvas.height-o.h)/2;
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
      o.canvas=imgToCanvas(img);o.baseCanvas=canvasFromData(cloneCanvasData(o.canvas));o.restoreCanvas=canvasFromData(cloneCanvasData(o.baseCanvas||o.canvas));
      render();finish('PNG PDF atualizado');setStatus('PDF atualizado em '+state.pdfDpi+' DPI');
    }catch(err){finish('Erro');showError('Falha ao reconverter PDF',err)}
  });

function whiteRemove(o,t){const c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data,th=t/100*Math.sqrt(3*255*255),soft=Math.max(1,th*(0.02+parseInt(ui.edge.value,10)/100*.16));const mask=new Uint8Array(w*h);for(let i=0;i<w*h;i++){const j=i*4;if(a[j+3]&&Math.hypot(255-a[j],255-a[j+1],255-a[j+2])<=th)mask[i]=1}const bg=new Uint8Array(w*h),q=[];const push=i=>{if(i>=0&&i<w*h&&!bg[i]&&mask[i]){bg[i]=1;q.push(i)}};for(let x=0;x<w;x++){push(x);push((h-1)*w+x)}for(let y=0;y<h;y++){push(y*w);push(y*w+w-1)}for(let head=0;head<q.length;head++){const i=q[head],x=i%w,y=Math.floor(i/w);if(x)push(i-1);if(x<w-1)push(i+1);if(y)push(i-w);if(y<h-1)push(i+w)}let changed=0;for(let i=0;i<w*h;i++){if(!bg[i])continue;const j=i*4;const dist=Math.hypot(255-a[j],255-a[j+1],255-a[j+2]);if(dist<=Math.max(0,th-soft)){a[j+3]=0;changed++}else if(dist<=th){a[j+3]=Math.round(a[j+3]*((dist-(th-soft))/soft))}}cc.putImageData(d,0,0);clearDisplayPreview(o);return changed}
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
async function smartBackgroundRemoveAsync(o,t,onProgress){const context=o.canvas.getContext('2d',{willReadFrequently:true}),image=context.getImageData(0,0,o.canvas.width,o.canvas.height);try{const result=await runPixelWorker(image,'background',{tolerance:t},onProgress);context.putImageData(result.imageData,0,0);clearDisplayPreview(o);return result.changed}catch(error){log('Processamento em segundo plano indisponível; usando processamento local',error&&error.message?error.message:error);await nextPaint();return smartBackgroundRemove(o,t)}}
function selectedColorRemove(o,color,t){const c=o.canvas,cc=c.getContext('2d'),d=cc.getImageData(0,0,c.width,c.height),a=d.data,th=t/100*Math.sqrt(3*255*255);let n=0;for(let i=0;i<a.length;i+=4){if(!a[i+3])continue;if(Math.hypot(a[i]-color.r,a[i+1]-color.g,a[i+2]-color.b)<=th){a[i+3]=0;n++}}cc.putImageData(d,0,0);clearDisplayPreview(o);return n}
async function selectedColorRemoveAsync(o,color,t,onProgress){const context=o.canvas.getContext('2d',{willReadFrequently:true}),image=context.getImageData(0,0,o.canvas.width,o.canvas.height);try{const result=await runPixelWorker(image,'color',{tolerance:t,color},onProgress);context.putImageData(result.imageData,0,0);clearDisplayPreview(o);return result.changed}catch(error){log('Remoção de cor em segundo plano indisponível; usando processamento local',error&&error.message?error.message:error);await nextPaint();return selectedColorRemove(o,color,t)}}
/* Remove somente a região uniforme ligada ao ponto clicado. A conectividade
 * em 8 direções alcança diagonais e cantos, enquanto a limpeza de vizinhança
 * tira o halo de antialias sem apagar o primeiro plano de cor diferente. */
function contiguousRemove(o,wx,wy,t,fixedColor=null){
 const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height,total=w*h,d=cc.getImageData(0,0,w,h),a=d.data;
 if(lx<0||ly<0||lx>=w||ly>=h)return 0;
 const start=ly*w+lx,startOffset=start*4;if(a[startOffset+3]<8)return 0;
 const target=fixedColor?{r:Number(fixedColor.r)||0,g:Number(fixedColor.g)||0,b:Number(fixedColor.b)||0}:{r:a[startOffset],g:a[startOffset+1],b:a[startOffset+2]},threshold=clamp((Number(t)||0)/100*Math.sqrt(3*255*255),8,255),seen=new Uint8Array(total),removed=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0,changed=0;
 const enqueue=index=>{if(index<0||index>=total||seen[index])return;seen[index]=1;queue[tail++]=index};
 enqueue(start);
 while(head<tail){const index=queue[head++],offset=index*4;if(a[offset+3]<8)continue;const distance=Math.hypot(a[offset]-target.r,a[offset+1]-target.g,a[offset+2]-target.b);if(distance>threshold)continue;a[offset+3]=0;removed[index]=1;changed++;const x=index%w,y=Math.floor(index/w);for(const next of [x?index-1:-1,x<w-1?index+1:-1,y?index-w:-1,y<h-1?index+w:-1,x&&y?index-w-1:-1,x<w-1&&y?index-w+1:-1,x&&y<h-1?index+w-1:-1,x<w-1&&y<h-1?index+w+1:-1])enqueue(next)}
 const fringeThreshold=Math.min(255,threshold*2.25+28),fringeRadius=3;
 /* Antialiasing can leave several shades around the component. Two passes
  * follow that connected fringe without crossing a strongly contrasting
  * foreground contour. */
 for(let pass=0;pass<2;pass++)for(let index=0;index<total;index++){
  if(removed[index]||a[index*4+3]<8)continue;
  const x=index%w,y=Math.floor(index/w);let near=false;
  for(let oy=-fringeRadius;oy<=fringeRadius&&!near;oy++)for(let ox=-fringeRadius;ox<=fringeRadius;ox++){
   if(Math.abs(ox)+Math.abs(oy)>fringeRadius)continue;const nx=x+ox,ny=y+oy;if(nx<0||ny<0||nx>=w||ny>=h)continue;if(removed[ny*w+nx]){near=true;break}
  }
  if(!near)continue;const offset=index*4,distance=Math.hypot(a[offset]-target.r,a[offset+1]-target.g,a[offset+2]-target.b);if(distance<=fringeThreshold&&(a[offset+3]<250||distance<=threshold*1.18)){a[offset+3]=0;removed[index]=1;changed++}
 }
 cc.putImageData(d,0,0);clearDisplayPreview(o);return changed;
}
/* Remove apenas o componente da cor que alcança uma borda da imagem. A
 * máscara de primeiro plano local é usada como barreira quando disponível;
 * assim uma abertura na moldura não faz o clique invadir olhos, letras ou
 * outros furos internos do desenho. */
function cleanExternalMatteFringe(image,removedMask,guideAlpha,target,threshold,w,h){
 const data=image.data,total=w*h,fringeThreshold=Math.min(190,Math.max(threshold*1.35,threshold+18)),fringeRadius=2;let changed=0;
 for(let index=0;index<total;index++){
  if(removedMask[index]||data[index*4+3]<8||guideAlpha&&guideAlpha[index]>=64)continue;
  const x=index%w,y=Math.floor(index/w);let near=false;
  for(let oy=-fringeRadius;oy<=fringeRadius&&!near;oy++)for(let ox=-fringeRadius;ox<=fringeRadius;ox++){
   if(Math.abs(ox)+Math.abs(oy)>fringeRadius)continue;
   const nx=x+ox,ny=y+oy;if(nx<0||ny<0||nx>=w||ny>=h)continue;
   if(removedMask[ny*w+nx]){near=true;break}
  }
  if(!near)continue;
  const j=index*4,dist=Math.hypot(data[j]-target.r,data[j+1]-target.g,data[j+2]-target.b),luminance=.299*data[j]+.587*data[j+1]+.114*data[j+2];
  if(dist>fringeThreshold||luminance>Math.min(145,threshold+58))continue;
  const fade=clamp((fringeThreshold-dist)/Math.max(1,fringeThreshold*.72),0,1),oldAlpha=data[j+3];data[j+3]=Math.round(oldAlpha*(1-fade*.94));if(data[j+3]<28)data[j+3]=0;if(data[j+3]!==oldAlpha)changed++;
 }
 return changed;
}
async function removeExternalConnectedArea(o,wx,wy,t,onProgress){
 const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d',{willReadFrequently:true}),w=c.width,h=c.height,total=w*h;
 if(lx<0||ly<0||lx>=w||ly>=h)return {changed:0,usedAi:false};
 const image=cc.getImageData(0,0,w,h),a=image.data,start=ly*w+lx,startOffset=start*4;
 if(a[startOffset+3]<8)return {changed:0,usedAi:false};
 const target={r:a[startOffset],g:a[startOffset+1],b:a[startOffset+2]},tolerance=clamp(Number(t)||35,1,100),threshold=clamp(10+tolerance*2.15,24,148);
 let guide=null,usedAi=false;
 try{guide=await getSmartAreaGuide(o,(amount,message)=>{if(onProgress)onProgress(amount,message)}) ;usedAi=!!guide}catch(error){log('Máscara local indisponível; usando somente conectividade externa.',error&&error.message?error.message:error)}
 const guideAlpha=guide&&guide.width===w&&guide.height===h&&guide.alpha&&guide.alpha.length===total?guide.alpha:null;
 const protectedPixel=index=>guideAlpha&&guideAlpha[index]>=64;
 if(protectedPixel(start))return {changed:0,usedAi};
 const seen=new Uint8Array(total),component=new Int32Array(total),queue=new Int32Array(total);let head=0,tail=0,componentSize=0,touchesEdge=false;
 const colorDistance=index=>{const j=index*4;return Math.hypot(a[j]-target.r,a[j+1]-target.g,a[j+2]-target.b)};
 const canVisit=index=>index>=0&&index<total&&!seen[index]&&a[index*4+3]>=8&&!protectedPixel(index)&&colorDistance(index)<=threshold;
 if(!canVisit(start))return {changed:0,usedAi};seen[start]=1;queue[tail++]=start;
 while(head<tail){const index=queue[head++];component[componentSize++]=index;const x=index%w,y=Math.floor(index/w);if(x===0||y===0||x===w-1||y===h-1)touchesEdge=true;const neighbors=[x?index-1:-1,x<w-1?index+1:-1,y?index-w:-1,y<h-1?index+w:-1];for(const next of neighbors)if(canVisit(next)){seen[next]=1;queue[tail++]=next}}
 if(!touchesEdge||!componentSize)return {changed:0,usedAi};
 let changed=0;const removedMask=new Uint8Array(total);for(let n=0;n<componentSize;n++){const index=component[n],j=index*4,dist=colorDistance(index),fadeStart=threshold*.78;removedMask[index]=1;a[j+3]=dist<=fadeStart?0:Math.round(a[j+3]*((dist-fadeStart)/Math.max(1,threshold-fadeStart)));changed++}
 changed+=cleanExternalMatteFringe(image,removedMask,guideAlpha,target,threshold,w,h);
 cc.putImageData(image,0,0);return {changed,usedAi};
}
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
  const model=selectedLocalAiModel(source),api=await preloadBrowserBgModel(model),input=await canvasToBlob(source,'image/png',1);let last=-1;
  const output=await api.removeBackground(input,{device:navigator.gpu?'gpu':'cpu',model:model.engineModel,output:{format:'image/png',type:'foreground'},progress:(key,current,total)=>{if(!onProgress||!total)return;const amount=18+Math.round(Math.max(0,Math.min(1,current/total))*66);if(amount>last){last=amount;onProgress(amount,'IA local identificando as bordas da área…')}}});
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
function smartAreaCursorRadius(){return Math.max(2,state.brushSize/2)}
function smartAreaStrokeSamples(stroke,point){const center=smartAreaStrokeLocalPoint(stroke,point);if(!center)return[];const radius=stroke.radiusWorkspace||smartAreaCursorRadius(),rx=Math.max(1,radius/stroke.object.w*stroke.width),ry=Math.max(1,radius/stroke.object.h*stroke.height),samples=[],centerProtected=!!(stroke.guideProtection&&stroke.guideProtection[center.index]),minX=Math.max(0,Math.floor(center.x-rx)),maxX=Math.min(stroke.width-1,Math.ceil(center.x+rx)),minY=Math.max(0,Math.floor(center.y-ry)),maxY=Math.min(stroke.height-1,Math.ceil(center.y+ry));for(let ly=minY;ly<=maxY;ly++)for(let lx=minX;lx<=maxX;lx++){const dx=(lx-center.x)/rx,dy=(ly-center.y)/ry;if(dx*dx+dy*dy>1)continue;const index=ly*stroke.width+lx;if(stroke.covered[index]||stroke.guideProtection&&centerProtected!==!!stroke.guideProtection[index])continue;stroke.covered[index]=1;samples.push({x:lx,y:ly,index})}return samples}
function removeSmartAreaStrokeSample(stroke,local){const data=stroke.image.data,startOffset=local.index*4;if(data[startOffset+3]<8||stroke.originalAlpha[local.index]<8)return 0;const region=smartAreaStrokeRegion(stroke,local.index);if(region.removed)return 0;region.removed=true;let removed=0;for(const index of region.pixels){const offset=index*4;if(data[offset+3]){data[offset+3]=0;removed++}}return removed}
function removeSmartAreaStrokePoint(stroke,point){return smartAreaStrokeSamples(stroke,point).reduce((total,sample)=>total+removeSmartAreaStrokeSample(stroke,sample),0)}
function commitSmartAreaStrokeCanvas(stroke){
 if(!stroke||!stroke.changed)return false;
 stroke.context.putImageData(stroke.image,0,0);
 clearDisplayPreview(stroke.object);
 return true;
}
function completeSmartAreaStroke(stroke){
 if(!stroke||stroke!==smartAreaStroke)return;if(stroke.frame){cancelAnimationFrame(stroke.frame);stroke.frame=0}commitSmartAreaStrokeCanvas(stroke);render();finish(stroke.changed?'Arraste concluído':'Nenhuma cor removida');setStatus(stroke.changed?(stroke.usedAi?'Arraste inteligente concluído com proteção de contorno: ':'Arraste por área concluído: ')+stroke.changed+' pixels removidos somente nas regiões próximas e delimitadas.':'Nenhuma área segura foi encontrada no trecho percorrido.');if(stroke.changed)rememberSmartAreaFeedback(stroke);smartAreaStroke=null;smartAreaRemovalBusy=false;
}
function drainSmartAreaStroke(stroke){
 if(!stroke||stroke!==smartAreaStroke||stroke.cancelled||!stroke.ready)return;stroke.frame=0;let processed=0,changed=0;while(stroke.pointHead<stroke.points.length&&processed<3){changed+=removeSmartAreaStrokePoint(stroke,stroke.points[stroke.pointHead++]);processed++}if(changed){stroke.changed+=changed;commitSmartAreaStrokeCanvas(stroke);render()}if(stroke.pointHead<stroke.points.length)scheduleSmartAreaStroke(stroke);else{stroke.points=[];stroke.pointHead=0;if(stroke.ended)completeSmartAreaStroke(stroke)}
}
function scheduleSmartAreaStroke(stroke){if(!stroke||stroke!==smartAreaStroke||stroke.cancelled||stroke.frame)return;stroke.frame=requestAnimationFrame(()=>drainSmartAreaStroke(stroke))}
function endSmartAreaStroke(pointerId){const stroke=smartAreaStroke;if(!stroke||pointerId!=null&&stroke.pointerId!==pointerId)return;stroke.ended=true;if(stroke.ready)scheduleSmartAreaStroke(stroke)}
function cancelSmartAreaStroke(){const stroke=smartAreaStroke;if(!stroke)return;stroke.cancelled=true;if(stroke.frame)cancelAnimationFrame(stroke.frame);smartAreaStroke=null;smartAreaRemovalBusy=false;finish(stroke.changed?'Arraste interrompido':'Processamento cancelado');if(stroke.changed){clearDisplayPreview(stroke.object);render()}}
function beginSmartAreaStroke(object,point,tolerance,pointerId){
if(smartAreaRemovalBusy){setStatus('A IA ainda está preparando o arraste atual.');return false}settleSmartAreaFeedback(true);const token=++smartAreaRemovalToken,stroke={token,object,source:object.canvas,tolerance,pointerId,radiusWorkspace:smartAreaCursorRadius(),points:[],pointHead:0,lastPoint:null,ended:false,cancelled:false,ready:false,usedAi:true,changed:0,frame:0};smartAreaStroke=stroke;smartAreaRemovalBusy=true;queueSmartAreaStrokePoint(stroke,point);progress(6,'IA local identificando e reforçando os contornos…');
 (async()=>{let guide=null;try{guide=await getSmartAreaGuide(object,(amount,message)=>progress(amount,message))}catch(error){stroke.usedAi=false;log('A IA de limites não ficou disponível; usando as bordas locais.',error&&error.message?error.message:error)}if(stroke.cancelled||stroke!==smartAreaStroke||token!==smartAreaRemovalToken||state.tool!=='areaRemove'||!state.objects.includes(object)||object.canvas!==stroke.source){if(stroke===smartAreaStroke)cancelSmartAreaStroke();return}const context=object.canvas.getContext('2d',{willReadFrequently:true}),image=context.getImageData(0,0,object.canvas.width,object.canvas.height),originalAlpha=new Uint8Array(object.canvas.width*object.canvas.height),width=object.canvas.width,height=object.canvas.height,effectiveSensitivity=clamp((Number(tolerance)||35)+smartAreaLearning.boundaryBias,1,100);for(let index=0,pixel=3;index<originalAlpha.length;index++,pixel+=4)originalAlpha[index]=image.data[pixel];const guideProtection=smartAreaProtectionMask(guide,width,height,effectiveSensitivity),initial=smartAreaStrokeLocalPoint(stroke,stroke.points[0]||stroke.lastPoint||point),protectForeground=!!(guideProtection&&initial&&!guideProtection[initial.index]),rawRadiusX=Math.max(1,stroke.radiusWorkspace/object.w*width),rawRadiusY=Math.max(1,stroke.radiusWorkspace/object.h*height),reachFactor=clamp(8-effectiveSensitivity/25,4,7.5),maxRegionRadius=clamp(Math.max(rawRadiusX,rawRadiusY)*reachFactor,18,Math.min(260,Math.max(width,height)*.32));Object.assign(stroke,{guide,guideProtection,protectForeground,context,image,originalAlpha,covered:new Uint8Array(originalAlpha.length),width,height,effectiveSensitivity,edgeThreshold:clamp(150-effectiveSensitivity*1.12,32,132),seedColorThreshold:clamp(255-effectiveSensitivity*1.35,75,235),maxRegionRadius,regionLabels:new Int32Array(width*height),regions:new Map(),nextRegionId:1,ready:true});pushHistory('Antes do arraste de remoção por área');progress(92,stroke.ended?'Contornos protegidos. Aplicando somente a área próxima…':'Contornos protegidos. Continue arrastando dentro do fundo externo…');scheduleSmartAreaStroke(stroke)})();return true;
}
if(ui.removeMode)ui.removeMode.addEventListener('change',()=>setStatus('Remover fundo'));
ui.tol.addEventListener('input',()=>{ui.tolValue.textContent=ui.tol.value;markRemovalOptionsChanged('Tolerância '+ui.tol.value)});ui.tolMinus.addEventListener('click',()=>{ui.tol.value=Math.max(0,parseInt(ui.tol.value)-1);ui.tol.dispatchEvent(new Event('input'))});ui.tolPlus.addEventListener('click',()=>{ui.tol.value=Math.min(100,parseInt(ui.tol.value)+1);ui.tol.dispatchEvent(new Event('input'))});ui.edge.addEventListener('input',()=>ui.edgeValue.textContent=ui.edge.value)
let pickedColor={r:255,g:255,b:255};ui.pickPreview.addEventListener('input',()=>{const h=ui.pickPreview.value;pickedColor={r:parseInt(h.slice(1,3),16),g:parseInt(h.slice(3,5),16),b:parseInt(h.slice(5,7),16)}})
ui.pickColor.addEventListener('click',()=>{if(selected()){state.tool='pick';setStatus('Clique na cor do objeto para selecionar');}else setStatus('Selecione um objeto primeiro')});
ui.dehalo.addEventListener('click',()=>{const o=selected();if(!o){setStatus('Selecione um objeto primeiro');return}pushHistory('Antes de limpar halos');const c=o.canvas,cc=c.getContext('2d'),d=cc.getImageData(0,0,c.width,c.height),a=d.data,w=c.width,h=c.height;let n=0;for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;if(!a[i+3])continue;let near=false;for(let oy=-2;oy<=2&&!near;oy++)for(let ox=-2;ox<=2;ox++){const nx=x+ox,ny=y+oy;if(nx<0||ny<0||nx>=w||ny>=h){near=true;break}if(a[(ny*w+nx)*4+3]===0){near=true;break}}if(near&&Math.max(a[i],a[i+1],a[i+2])>205&&Math.min(a[i],a[i+1],a[i+2])>185&&(Math.max(a[i],a[i+1],a[i+2])-Math.min(a[i],a[i+1],a[i+2]))<45){a[i+3]=Math.round(a[i+3]*.18);n++}}cc.putImageData(d,0,0);render();setStatus('Halos limpos: '+n+' pixels')});
function applyUniformBackgroundRemoval(){
 const seed=uniformRemovalSeed,object=seed&&state.objects.find(item=>item.id===seed.objectId);
 if(!object){setStatus('Clique em uma área do fundo uniforme primeiro');return}
 const t=clamp(Number(ui.tol&&ui.tol.value)||20,0,100);pushHistory('Antes de remover fundo uniforme');
 object.canvas=canvasFromData(cloneCanvasData(object.baseCanvas||object.canvas));
 const changed=contiguousRemove(object,seed.x,seed.y,t);render();removalOptionsApplied=true;updateRemovalOptionControls();
 if(changed){setStatus('Fundo uniforme removido: '+changed+' pixels · tolerância '+t+'.');showToolHint('Ajuste a tolerância e clique em Aplicar novamente se ainda houver halo.')}else{setStatus('Nenhuma região uniforme encontrada nesse ponto. Aumente a tolerância e tente Aplicar novamente.');showToolHint('Clique exatamente no fundo e ajuste a tolerância.')}
}
async function applyAutoBackgroundRemoval(){const items=selectedObjects();if(!items.length){setStatus('Selecione uma imagem primeiro');return}if(removalApplyBusy)return;removalApplyBusy=true;if(ui.removalApply){ui.removalApply.disabled=true;ui.removalApply.textContent='Aplicando'}try{pushHistory('Antes de remover fundo');const t=Number.isFinite(Number(ui.tol.value))?Number(ui.tol.value):20;progress(8,'Restaurando a imagem original para testar a nova tolerância...');await nextPaint();let changed=0,total=0;for(let i=0;i<items.length;i++){const o=items[i];o.canvas=canvasFromData(cloneCanvasData(o.baseCanvas));total+=o.canvas.width*o.canvas.height;progress(14+Math.round(i/items.length*12),'Aplicando a tolerância '+t+' em '+(i+1)+' de '+items.length+'...');await nextPaint();const start=30+Math.round(i/items.length*58),span=58/items.length;changed+=await smartBackgroundRemoveAsync(o,t,value=>progress(start+Math.round(value/100*span),'Processando remoção em segundo plano '+(i+1)+' de '+items.length+'...'));progress(30+Math.round((i+1)/items.length*58),'Removendo fundo '+(i+1)+' de '+items.length+'...');await nextPaint()}render();finish('Remoção de fundo uniforme concluída');removalOptionsApplied=true;if(ui.reset){ui.reset.classList.add('active');ui.reset.setAttribute('aria-pressed','true')}updateRemovalOptionControls();const weak=changed<Math.max(24,total*.001);setStatus(weak?'Concluído: pouco fundo foi identificado. Ajuste a tolerância e clique em Aplicar novamente.':'Concluído: remoção de fundo uniforme finalizada em '+items.length+' objeto'+(items.length===1?'':'s')+' · tolerância '+t+'.');showRemovalGuide(weak&&ui.magicFill?ui.magicFill:ui.reset,weak?'Para imagens muito complexas, use Montagem inteligente (IA).':'Ao mudar a tolerância, clique em Aplicar para refazer a partir do original.',weak?'complex':'simple',6500)}finally{removalApplyBusy=false;if(ui.removalApply){ui.removalApply.disabled=false;ui.removalApply.textContent='Aplicar'}updateRemovalOptionControls();}}
ui.reset.addEventListener('click',()=>{if(ui.reset&&ui.reset.classList.contains('active')){deactivateSpecialTool();setStatus('Remoção de fundo uniforme desativada');return}const items=selectedObjects();if(!items.length){setStatus('Selecione uma imagem primeiro');return}deactivateSpecialTool(true);deactivateBrush();state.tool='select';uniformRemovalSeed=null;if(ui.reset){ui.reset.classList.add('active');ui.reset.setAttribute('aria-pressed','true')}editorRoot.classList.remove('dtf-color-remove-cursor');removalOptionsApplied=false;updateRemovalOptionControls();setStatus('Ajuste a tolerância e clique em Aplicar para remover o fundo uniforme');showToolHint('T/P+scroll ajusta a tolerância. Clique apenas em Aplicar para iniciar.')});ui.original.addEventListener('click',()=>{const items=selectedObjects();if(!items.length){setStatus('Selecione um objeto primeiro');return}pushHistory('Antes de restaurar imagem original');items.forEach(o=>{o.canvas=canvasFromData(cloneCanvasData(o.baseCanvas));refreshRestoreCanvas(o)});render();updateRemovalOptionControls();setStatus('Imagem original restaurada')});

// Selection / move / resize / transform.
let resizeState=null,transformState=null;
function isShapeTransformTarget(o){return !!(o&&(String(o.sourceType||'').indexOf('shape-')===0||(o.canvas&&!isTextShape(o)&&!isLineShape(o))))}
function isImageTransformTarget(o){return !!(o&&o.canvas&&!isTextShape(o)&&!isLineShape(o)&&!isShapeTransformTarget(o))}
function isTransformTarget(o){return isShapeTransformTarget(o)||isImageTransformTarget(o)}
function shapeTransformAngle(cx,cy,p){return Math.atan2(p.y-cy,p.x-cx)*180/Math.PI}
function applyExclusiveShapeTransform(o,transformState,p,hs,shiftKey=false){
 if(!o||!transformState||!p)return '';
 o.x=transformState.ox;o.y=transformState.oy;o.w=transformState.ow;o.h=transformState.oh;
 if(['nw','ne','se','sw'].includes(hs)){
  let angle=transformState.startRotation+(shapeTransformAngle(transformState.cx,transformState.cy,p)-transformState.startAngle);
  if(shiftKey)angle=Math.round(angle/15)*15;
  o.rotation=angle;
  return 'rotate';
 }
 o.rotation=transformState.startRotation;
 if(hs==='n'||hs==='s'){
  const deltaTan=(p.x-transformState.startX)/Math.max(1,transformState.oh);
  const nextTan=clamp(transformState.startTanX+(hs==='s'?deltaTan:-deltaTan),-1.35,1.35);
  o.skewX=Math.atan(nextTan)*180/Math.PI;
  o.skewY=transformState.startSkewY;
  o.skewAnchorY=hs==='s'?'top':'bottom';
  o.skewAnchorX='center';
  return 'skew-horizontal';
 }
 if(hs==='e'||hs==='w'){
  const deltaTan=(p.y-transformState.startY)/Math.max(1,transformState.ow);
  const nextTan=clamp(transformState.startTanY+(hs==='e'?deltaTan:-deltaTan),-1.35,1.35);
  o.skewY=Math.atan(nextTan)*180/Math.PI;
  o.skewX=transformState.startSkewX;
  o.skewAnchorX=hs==='e'?'left':'right';
  o.skewAnchorY='center';
  return 'skew-vertical';
 }
 return '';
}
selection.querySelectorAll('.dtf-handle').forEach(h=>h.addEventListener('pointerdown',e=>{if(e.ctrlKey)return;const o=selected();if(!o)return;const handle=h.dataset.handle,transformMode=(selectionTransformMode||selection.classList.contains('dtf-transform-mode'))&&isTransformTarget(o)&&state.selectedIds.length===1;if(!transformMode){transformState=null;resizeState=null;return}e.preventDefault();e.stopImmediatePropagation();const p=clientToWorkspace(e);if(!p)return;resizeState=null;state.drag=null;const cx=o.x+o.w/2,cy=o.y+o.h/2;transformState={handle,startX:p.x,startY:p.y,cx,cy,ox:o.x,oy:o.y,ow:o.w,oh:o.h,startAngle:shapeTransformAngle(cx,cy,p),startRotation:Number(o.rotation)||0,startSkewX:Number(o.skewX)||0,startSkewY:Number(o.skewY)||0,startTanX:Math.tan((Number(o.skewX)||0)*Math.PI/180),startTanY:Math.tan((Number(o.skewY)||0)*Math.PI/180),changed:false,historySaved:false};try{h.setPointerCapture(e.pointerId)}catch(_){} }));
selection.addEventListener('pointermove',e=>{if(transformState){const o=selected(),p=clientToWorkspace(e);if(!o||!p)return;e.preventDefault();resizeState=null;state.drag=null;const hs=transformState.handle;if(!transformState.historySaved){pushHistory(['nw','ne','se','sw'].includes(hs)?'Antes de girar objeto':'Antes de inclinar objeto');transformState.historySaved=true}const mode=applyExclusiveShapeTransform(o,transformState,p,hs,e.shiftKey);if(!mode)return;transformState.changed=true;render();setStatus(mode==='rotate'?'Girando objeto':'Inclinando objeto');return}return;});
selection.addEventListener('pointerup',e=>{if(transformState){if(transformState.changed){render();setStatus('Transformação aplicada')}transformState=null;return}if(resizeState){pushHistory('Redimensionar objeto');resizeState=null}});selection.addEventListener('pointercancel',()=>{resizeState=null;transformState=null})



function cropCanvasByObjectRect(sourceData,fromRect,toRect){
 const sw0=sourceData&&sourceData.w,sh0=sourceData&&sourceData.h;
 if(!sw0||!sh0||!fromRect||!toRect)return null;
 const left=Math.max(0,Math.round((toRect.x-fromRect.x)/fromRect.w*sw0));
 const top=Math.max(0,Math.round((toRect.y-fromRect.y)/fromRect.h*sh0));
 const right=Math.max(0,Math.round(((fromRect.x+fromRect.w)-(toRect.x+toRect.w))/fromRect.w*sw0));
 const bottom=Math.max(0,Math.round(((fromRect.y+fromRect.h)-(toRect.y+toRect.h))/fromRect.h*sh0));
 const width=Math.max(1,sw0-left-right),height=Math.max(1,sh0-top-bottom);
 const src=canvasFromData(sourceData),out=document.createElement('canvas');
 out.width=width;out.height=height;out.getContext('2d').drawImage(src,left,top,width,height,0,0,width,height);
 return out;
}
function applyCtrlCropResize(drag){
 if(!drag||!drag.crop)return false;
 const mainTo=drag.cropRect||{x:drag.ox,y:drag.oy,w:drag.ow,h:drag.oh};
 const ratioX=mainTo.w/drag.ow,ratioY=mainTo.h/drag.oh,deltaX=mainTo.x-drag.ox,deltaY=mainTo.y-drag.oy;
 let changed=false;
 drag.items.forEach(item=>{
  const o=item.o,from={x:item.x,y:item.y,w:item.w,h:item.h};
  const to=item.o===drag.mainObject?{...mainTo}:{x:item.x+deltaX,y:item.y+deltaY,w:Math.max(4,item.w*ratioX),h:Math.max(4,item.h*ratioY)};
  if(to.w>=from.w-0.01&&to.h>=from.h-0.01&&Math.abs(to.x-from.x)<0.01&&Math.abs(to.y-from.y)<0.01)return;
  const cropped=cropCanvasByObjectRect(item.canvasData,from,to);
  if(!cropped)return;
  o.x=to.x;o.y=to.y;o.w=to.w;o.h=to.h;
  o.canvas=cropped;
  const baseCrop=cropCanvasByObjectRect(item.baseData||item.canvasData,from,to);
  if(baseCrop)o.baseCanvas=baseCrop;
  const restoreCrop=cropCanvasByObjectRect(item.restoreData||item.baseData||item.canvasData,from,to);if(restoreCrop)o.restoreCanvas=restoreCrop;
  if(item.aiOriginalData){const aiCrop=cropCanvasByObjectRect(item.aiOriginalData,from,to);if(aiCrop)o.aiOriginalCanvas=aiCrop}
  o.originalW=cropped.width;o.originalH=cropped.height;
  delete o.intelligentVariantSetId;delete o.intelligentVariantId;delete o.intelligentVariantRotated;
  changed=true;
 });
 return changed;
}
function showCtrlCropPreview(rect){
 if(!rect)return;
 selection.classList.add('show','dtf-crop-live');
 selection.style.left=rect.x+'px';selection.style.top=rect.y+'px';selection.style.width=rect.w+'px';selection.style.height=rect.h+'px';
 selectionLabel.style.top=rect.y<32?(rect.h+6)+'px':'-34px';
 selectionLabel.textContent='Recorte • '+pxToMm(rect.w).toFixed(1)+' × '+pxToMm(rect.h).toFixed(1)+' mm';
}
function hideCtrlCropPreview(){selection.classList.remove('dtf-crop-live')}

let ctrlCropKeyDown=false;
function updateCtrlCropCursorMode(force){
 const o=selected(),eligible=!!o&&!isShapeObject(o);
 const on=eligible&&(!!force||(ctrlCropKeyDown&&selection&&selection.classList.contains('show')));
 editorRoot.classList.toggle('dtf-ctrl-crop-ready',on);
}
document.addEventListener('keydown',e=>{
 if(activeEditorRoot!==editorRoot)return;
 if(e.key==='Control'||e.ctrlKey){ctrlCropKeyDown=true;updateCtrlCropCursorMode();}
},{capture:true});
document.addEventListener('keyup',e=>{
 if(e.key==='Control'||!e.ctrlKey){ctrlCropKeyDown=false;updateCtrlCropCursorMode(false);}
},{capture:true});
window.addEventListener('blur',()=>{ctrlCropKeyDown=false;updateCtrlCropCursorMode(false)});
selection.querySelectorAll('.dtf-handle').forEach(handle=>{
 handle.addEventListener('pointerenter',e=>{if(e.ctrlKey){ctrlCropKeyDown=true;updateCtrlCropCursorMode(true)}});
 handle.addEventListener('pointerleave',()=>{if(!ctrlCropKeyDown&&!resizeDrag)updateCtrlCropCursorMode(false)});
});



// Resize com oito alças da caixa de seleção. O lock mantém proporção.
selection.querySelectorAll('.dtf-handle').forEach(handle=>{
  handle.addEventListener('pointerdown',e=>{
    const o=selected();if(!o)return;
    const transformMode=!e.ctrlKey&&!e.shiftKey&&(selectionTransformMode||selection.classList.contains('dtf-transform-mode'))&&isTransformTarget(o)&&state.selectedIds.length===1;
    if(transformMode){resizeDrag=null;e.preventDefault();e.stopImmediatePropagation();return}
    e.preventDefault();e.stopPropagation();
    const p=clientToWorkspace(e);if(!p)return;
    const symmetricShape=!!e.shiftKey&&isShapeObject(o),cropMode=!!e.ctrlKey&&!isShapeObject(o),textResize=isTextShape(o);if(textResize){o.textAutoFit=false;o._textPaintToken=(Number(o._textPaintToken)||0)+1}
    pushHistory(cropMode?'Antes de recortar objeto':'Antes de redimensionar');
    resizeDrag={handle:handle.dataset.handle,sx:p.x,sy:p.y,ox:o.x,oy:o.y,ow:o.w,oh:o.h,ratio:o.w/o.h,lock:!cropMode&&(textResize||['nw','ne','se','sw'].includes(handle.dataset.handle)),symmetric:symmetricShape,crop:cropMode,cropRect:{x:o.x,y:o.y,w:o.w,h:o.h},mainObject:o,pointerId:e.pointerId,items:selectedObjects().map(item=>({o:item,x:item.x,y:item.y,w:item.w,h:item.h,textRuns:isTextShape(item)?cloneTextRuns(normalizeTextRuns(item)):null,textFontSize:Number(item.textFontSize)||32,canvasData:cloneCanvasData(item.canvas),baseData:item.baseCanvas?cloneCanvasData(item.baseCanvas):null,restoreData:item.restoreCanvas?cloneCanvasData(item.restoreCanvas):null,aiOriginalData:item.aiOriginalCanvas?cloneCanvasData(item.aiOriginalCanvas):null}))};
    if(cropMode){updateCtrlCropCursorMode(true);showToolHint('CTRL ativo: solte para recortar.');setStatus('Recorte por borda ativo — não aumenta a imagem.')}
    else if(symmetricShape){showToolHint('SHIFT ativo: redimensionamento simétrico.');setStatus('Redimensionamento simétrico ativo — o lado oposto acompanha.')}
    try{handle.setPointerCapture(e.pointerId)}catch(_){ }
  });
});

document.addEventListener('pointermove',e=>{
  if(transformState){resizeDrag=null;return}
  if(!resizeDrag || e.pointerId!==resizeDrag.pointerId)return;
  const o=selected(),p=clientToWorkspace(e);if(!o||!p)return;
  e.preventDefault();
  const dx=p.x-resizeDrag.sx,dy=p.y-resizeDrag.sy;
  let x=resizeDrag.ox,y=resizeDrag.oy,w=resizeDrag.ow,h=resizeDrag.oh;const hs=resizeDrag.handle;
  if(resizeDrag.crop){
    if(hs.includes('e'))w=clamp(resizeDrag.ow+Math.min(0,dx),4,resizeDrag.ow);
    if(hs.includes('s'))h=clamp(resizeDrag.oh+Math.min(0,dy),4,resizeDrag.oh);
    if(hs.includes('w')){const cut=clamp(Math.max(0,dx),0,resizeDrag.ow-4);x=resizeDrag.ox+cut;w=resizeDrag.ow-cut}
    if(hs.includes('n')){const cut=clamp(Math.max(0,dy),0,resizeDrag.oh-4);y=resizeDrag.oy+cut;h=resizeDrag.oh-cut}
    resizeDrag.cropRect={x,y,w,h};
    showCtrlCropPreview(resizeDrag.cropRect);
    return;
  }
  if(resizeDrag.symmetric){
    const cx=resizeDrag.ox+resizeDrag.ow/2,cy=resizeDrag.oy+resizeDrag.oh/2;
    const maxW=Math.max(4,2*Math.min(cx,canvas.width-cx)),maxH=Math.max(4,2*Math.min(cy,canvas.height-cy));
    if(hs.includes('e'))w=resizeDrag.ow+dx;
    if(hs.includes('w'))w=resizeDrag.ow-dx;
    if(hs.includes('s'))h=resizeDrag.oh+dy;
    if(hs.includes('n'))h=resizeDrag.oh-dy;
    if(resizeDrag.lock&&hs.length===2){
      const candidateW=Math.max(4,w),candidateH=Math.max(4,h),scaleW=candidateW/resizeDrag.ow,scaleH=candidateH/resizeDrag.oh;
      let scale=Math.abs(dx)>=Math.abs(dy)?scaleW:scaleH;
      const minScale=Math.max(4/resizeDrag.ow,4/resizeDrag.oh),maxScale=Math.min(maxW/resizeDrag.ow,maxH/resizeDrag.oh);
      scale=clamp(scale,minScale,maxScale);w=resizeDrag.ow*scale;h=resizeDrag.oh*scale;
    }
    w=clamp(w,4,maxW);h=clamp(h,4,maxH);x=cx-w/2;y=cy-h/2;
  }else{
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
  }
  o.x=x;o.y=y;o.w=w;o.h=h;
  const sx=w/resizeDrag.ow,sy=h/resizeDrag.oh,mainBaseline=resizeDrag.items.find(item=>item.o===o);
  if(isTextShape(o)){scaleTextObjectFromBaseline(o,mainBaseline&&mainBaseline.textRuns,mainBaseline&&mainBaseline.textFontSize,textScaleFactorFromResize(hs,sx,sy),w,h,{preserveRight:hs.includes('w'),preserveBottom:hs.includes('n')});if(textDirectEditSession&&textDirectEditSession.objectId===o.id){const range=textDirectEditSession.selection||{start:0,end:0};renderDirectTextEditor(o,range)}}
  resizeDrag.items.filter(item=>item.o!==o).forEach(item=>{
   item.o.w=Math.max(4,Math.min(canvas.width,item.w*sx));item.o.h=Math.max(4,Math.min(canvas.height,item.h*sy));
   item.o.x=clamp(o.x+(item.x-resizeDrag.ox)*sx,0,canvas.width-item.o.w);item.o.y=clamp(o.y+(item.y-resizeDrag.oy)*sy,0,canvas.height-item.o.h);
   if(isTextShape(item.o))scaleTextObjectFromBaseline(item.o,item.textRuns,item.textFontSize,textScaleFactorFromResize(hs,sx,sy),item.o.w,item.o.h,{preserveRight:hs.includes('w'),preserveBottom:hs.includes('n')})
  });
  render();updateSelection();
});

document.addEventListener('pointerup',e=>{if(transformState){resizeDrag=null;return}if(resizeDrag&&e.pointerId===resizeDrag.pointerId){const wasCrop=!!resizeDrag.crop;const changed=applyCtrlCropResize(resizeDrag);resizeDrag=null;hideCtrlCropPreview();render();updateSelection();setStatus(wasCrop?(changed?'Imagem recortada':'Recorte sem alteração'):'Objeto redimensionado')}});
document.addEventListener('pointercancel',e=>{if(resizeDrag&&e.pointerId===resizeDrag.pointerId){resizeDrag=null;hideCtrlCropPreview();updateSelection()}});

function fillClosedRegion(o,wx,wy,color,tol){const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data;if(lx<0||ly<0||lx>=w||ly>=h)return 0;const start=(ly*w+lx),sj=start*4,target=[a[sj],a[sj+1],a[sj+2]],th=Math.max(2,tol/100*441),seen=new Uint8Array(w*h),q=[start];seen[start]=1;const rgb=color.match(/[0-9a-f]{2}/gi).map(v=>parseInt(v,16));let n=0;while(q.length){const i=q.pop(),j=i*4;if(!a[j+3]||Math.hypot(a[j]-target[0],a[j+1]-target[1],a[j+2]-target[2])>th)continue;a[j]=rgb[0];a[j+1]=rgb[1];a[j+2]=rgb[2];n++;const x=i%w,y=Math.floor(i/w);for(const ni of [x?i-1:-1,x<w-1?i+1:-1,y?i-w:-1,y<h-1?i+w:-1])if(ni>=0&&!seen[ni]){seen[ni]=1;q.push(ni)}}cc.putImageData(d,0,0);return n}
// Flood fill que também reconhece regiões transparentes fechadas.
const legacyFillClosedRegion=fillClosedRegion;
fillClosedRegion=function(o,wx,wy,color,tol){const lx=Math.floor((wx-o.x)/o.w*o.canvas.width),ly=Math.floor((wy-o.y)/o.h*o.canvas.height),c=o.canvas,cc=c.getContext('2d'),w=c.width,h=c.height,d=cc.getImageData(0,0,w,h),a=d.data;if(lx<0||ly<0||lx>=w||ly>=h)return 0;const start=ly*w+lx,sj=start*4,targetA=a[sj+3],target=[a[sj],a[sj+1],a[sj+2]],th=Math.max(2,(Number(tol)||20)/100*441),alphaTh=targetA<16?32:8,seen=new Uint8Array(w*h),q=[start],rgb=(String(color||'#8c3f00').match(/[0-9a-f]{2}/gi)||['8c','3f','00']).map(v=>parseInt(v,16));const similar=i=>{const j=i*4;if(Math.abs(a[j+3]-targetA)>alphaTh)return false;return Math.hypot(a[j]-target[0],a[j+1]-target[1],a[j+2]-target[2])<=th};seen[start]=1;let n=0;while(q.length){const i=q.pop();if(!similar(i))continue;const j=i*4;a[j]=rgb[0];a[j+1]=rgb[1];a[j+2]=rgb[2];a[j+3]=255;n++;const x=i%w,y=Math.floor(i/w);for(const ni of [x?i-1:-1,x<w-1?i+1:-1,y?i-w:-1,y<h-1?i+w:-1])if(ni>=0&&!seen[ni]){seen[ni]=1;q.push(ni)}}cc.putImageData(d,0,0);return n};
let pointerSpace=false,panDrag=null;
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.dtf-user-guide')||state.tool!=='fill')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit)return; e.preventDefault();e.stopImmediatePropagation();pushHistory('Antes de preencher região');const n=fillClosedRegion(hit,p.x,p.y,fillColor?fillColor.value:'#8c3f00',Number(ui.tol.value)||20);if(n>0)refreshRestoreCanvas(hit);render();setStatus('Região preenchida: '+n+' pixels')},{capture:true});
if(fillTool)fillTool.addEventListener('click',()=>{if(state.tool==='fill'){deactivateSpecialTool();setCursorSelectButtonActive(true);return}if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();state.tool='fill';fillTool.classList.add('active');fillTool.setAttribute('aria-pressed','true');editorRoot.classList.add('dtf-color-remove-cursor');setStatus('Clique em uma área fechada para preencher com a cor atual');showToolHint('Clique em uma área fechada.')});
/* 1.0.299: a paleta padrão apenas muda a cor atual; não ativa Preencher nem troca a ferramenta ativa. */
function isRemovalTool(tool=state.tool){return tool==='uniformRemove'||tool==='colorRemove'||tool==='areaRemove'||tool==='outerAreaRemove'||tool==='globalColorRemove'||tool==='colorAreaRemove'}
let removalOptionsApplied=false,removalApplyBusy=false,outerAreaRemovalBusy=false,colorRemoveScope='connected',uniformRemovalSeed=null;
function currentRemovalOptionMode(){if(state.tool==='uniformRemove')return 'tolerance';if(state.tool==='colorAreaRemove')return 'colorAreaBrush';if(state.tool==='globalColorRemove')return 'colorScopeMeasure';if(state.tool==='areaRemove')return 'borderMeasure';if(state.tool==='colorRemove'||state.tool==='outerAreaRemove')return 'border';if(ui.reset&&ui.reset.classList.contains('active'))return 'tolerance';return null}
function updateColorRemoveScopeButtons(){
 if(!ui.colorRemoveConnected||!ui.colorRemoveAll)return;
 const connected=colorRemoveScope!=='all';
 ui.colorRemoveConnected.classList.toggle('active',connected);
 ui.colorRemoveConnected.setAttribute('aria-pressed',String(connected));
 ui.colorRemoveAll.classList.toggle('active',!connected);
 ui.colorRemoveAll.setAttribute('aria-pressed',String(!connected));
}
function setRemovalOptionControls(mode){
 const activePanel=Q('[data-panel="editar"]');
 const visible=!!mode&&(!activePanel||activePanel.classList.contains('active'));
 const showOnlyMeasure=visible&&mode==='colorAreaBrush';
 const showMeasure=visible&&(mode==='colorAreaBrush'||mode==='borderMeasure');
 const showTolerance=visible&&!showOnlyMeasure&&(mode==='tolerance'||mode==='both');
 const showBorder=visible&&!showOnlyMeasure&&(mode==='border'||mode==='both'||mode==='borderMeasure');
 const showColorScope=visible&&!showOnlyMeasure&&(mode==='colorScope'||mode==='colorScopeMeasure');
 const anyVisible=showTolerance||showBorder||showColorScope||showMeasure;
 editorRoot.classList.toggle('dtf-show-tolerance',showTolerance);
 editorRoot.classList.toggle('dtf-show-border',showBorder);
 editorRoot.classList.toggle('dtf-show-color-scope',showColorScope);
 editorRoot.classList.toggle('dtf-show-color-area-brush',showOnlyMeasure);
 editorRoot.classList.toggle('dtf-show-measure',showMeasure);
 editorRoot.classList.toggle('dtf-show-removal-options',anyVisible);
 editorRoot.classList.toggle('dtf-removal-options-applied',removalOptionsApplied&&!!mode);
 updateColorRemoveScopeButtons();

 const settings=Q('.dtf-removal-settings');
 const tolRow=Q('.dtf-removal-tolerance-row');
 const edgeRow=Q('.dtf-removal-edge-row');
 const colorScope=Q('.dtf-color-remove-options');
 const colorAreaRow=Q('.dtf-removal-color-area-row');
 const colorAreaPreview=ui.colorAreaBrushPreview||Q('.dtf-color-area-brush-preview');
 const setDisplay=(el,on,value='flex')=>{if(el)el.style.setProperty('display',on?value:'none','important')};
 if(settings)settings.style.setProperty('display',anyVisible?'flex':'none','important');
 setDisplay(tolRow,showTolerance);
 setDisplay(edgeRow,showBorder);
 setDisplay(colorScope,showColorScope);
 setDisplay(colorAreaRow,showMeasure);
 setDisplay(colorAreaPreview,showOnlyMeasure,'inline-flex');
 if(showOnlyMeasure){
  if(tolRow)tolRow.hidden=true;
  if(edgeRow)edgeRow.hidden=true;
  if(colorScope)colorScope.hidden=true;
 }else{
  if(tolRow)tolRow.hidden=false;
  if(edgeRow)edgeRow.hidden=false;
  if(colorScope)colorScope.hidden=false;
 }
 if(ui.colorAreaBrushValue&&ui.colorAreaBrush)ui.colorAreaBrushValue.textContent=String(ui.colorAreaBrush.value||state.brushSize||32);
 if(ui.removalApply){
  ui.removalApply.hidden=!showTolerance;
  ui.removalApply.disabled=removalApplyBusy;
  ui.removalApply.textContent=removalApplyBusy?'Aplicando':'Aplicar';
  ui.removalApply.title='Aplicar tolerância';
  ui.removalApply.setAttribute('aria-label','Aplicar tolerância');
  ui.removalApply.style.setProperty('display',showTolerance?'inline-flex':'none','important');
 }
}
function updateRemovalOptionControls(){setRemovalOptionControls(currentRemovalOptionMode())}
function markRemovalOptionsChanged(label){
 const mode=currentRemovalOptionMode();
 if(!mode)return;
 if(mode==='tolerance'){
  if(removalOptionsApplied){const items=state.tool==='uniformRemove'&&uniformRemovalSeed?[state.objects.find(o=>o.id===uniformRemovalSeed.objectId)].filter(Boolean):selectedObjects();if(items.length){items.forEach(o=>{if(o.baseCanvas)o.canvas=canvasFromData(cloneCanvasData(o.baseCanvas))});render();}}
  removalOptionsApplied=false;updateRemovalOptionControls();if(label)setStatus(label+' ajustada. Clique em Aplicar.');return;
 }
 if(mode==='colorAreaBrush'){removalOptionsApplied=true;updateRemovalOptionControls();if(label)setStatus(label+' ajustado.');return}
 removalOptionsApplied=true;updateRemovalOptionControls();if(label)setStatus(label+' ajustada. Use a ferramenta na imagem.');
}
function deactivateAutoRemoveOption(){
 if(ui.reset){ui.reset.classList.remove('active');ui.reset.setAttribute('aria-pressed','false')}
 removalOptionsApplied=false;
 updateRemovalOptionControls();
}
function deactivateSpecialTool(quiet=false){
 if(!isRemovalTool()&&state.tool!=='fill'){deactivateAutoRemoveOption();return}
 if(state.tool==='areaRemove'){smartAreaRemovalToken++;cancelSmartAreaStroke()}
 if(state.tool==='uniformRemove')uniformRemovalSeed=null;
 if(state.tool==='colorAreaRemove'){colorAreaRemoval.stroke=null;hideColorCursorPreview()}
 if(state.tool==='globalColorRemove')globalColorRemoval.stroke=null;
 state.tool='select';
 [clickRemove,areaRemoveButton,outerAreaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton,fillTool].forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});
 colorAreaRemoval.color=null;syncColorAreaBrushPreview();
 deactivateAutoRemoveOption();
 editorRoot.classList.remove('dtf-color-remove-cursor','dtf-area-remove-cursor');
 eraserCursor.classList.remove('show','dtf-area-cursor');
 if(!quiet)setStatus('Ferramenta desativada')
}
document.addEventListener('pointerdown',e=>{const autoActive=!!(ui.reset&&ui.reset.classList.contains('active'));if(autoActive&&!isRemovalTool()&&state.tool!=='fill'){if(e.target.closest('#dtfReset,#dtfRemovalApply,.dtf-removal-settings,#dtfViewport'))return;if(e.target.closest('button.dtf-tool,button.dtf-tab,button.dtf-secondary,button.dtf-align-btn'))deactivateAutoRemoveOption();return}if(!isRemovalTool()&&state.tool!=='fill')return;if(e.target.closest('#dtfReset,#dtfClickRemove,#dtfAreaRemove,#dtfOuterAreaRemove,#dtfColorRemove,#dtfColorAreaRemove,#dtfFillTool,#dtfRemovalApply,.dtf-removal-settings,#dtfViewport,#dtfUndo,#dtfRedo,#dtfOriginal,#dtfUndoMenuBtn,#dtfRedoMenuBtn'))return;if(e.target.closest('button.dtf-tool,button.dtf-tab,button.dtf-secondary,button.dtf-align-btn'))deactivateSpecialTool()},{capture:true});
viewport.addEventListener('pointermove',e=>{if(state.tool!=='select')return;const p=clientToWorkspace(e);viewport.classList.toggle('dtf-can-drag',!!(p&&hitTest(p.x,p.y)))},{capture:true});
viewport.addEventListener('pointerdown',e=>{if(state.tool==='select'){const p=clientToWorkspace(e);if(p&&hitTest(p.x,p.y))viewport.classList.add('dtf-dragging')}},{capture:true});
viewport.addEventListener('pointerup',()=>viewport.classList.remove('dtf-dragging'),{capture:true});viewport.addEventListener('pointercancel',()=>viewport.classList.remove('dtf-dragging'),{capture:true});
viewport.addEventListener('pointerdown',e=>{const p=clientToWorkspace(e);if(p&&hitTest(p.x,p.y)&&state.tool==='select')selection.classList.add('dtf-measuring')},{capture:true});
viewport.addEventListener('pointerup',()=>selection.classList.remove('dtf-measuring'),{capture:true});viewport.addEventListener('pointercancel',()=>selection.classList.remove('dtf-measuring'),{capture:true});
function globalColorRemovalTolerance(){
 const value=Number(ui.clickTol&&ui.clickTol.value);
 return clamp(Number.isFinite(value)?value:35,1,100);
}
function removeGlobalColorAt(hit,point,tolerance,fixedColor=null){
 if(!hit||!point)return 0;
 const lx=Math.floor((point.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((point.y-hit.y)/hit.h*hit.canvas.height);
 if(lx<0||ly<0||lx>=hit.canvas.width||ly>=hit.canvas.height)return 0;
 const pixel=hit.canvas.getContext('2d',{willReadFrequently:true}).getImageData(lx,ly,1,1).data;
 if(pixel[3]<8&&!fixedColor)return 0;
 const color=fixedColor||{r:pixel[0],g:pixel[1],b:pixel[2]};
 if(colorRemoveScope==='all'){
  const targets=state.selectedIds.includes(hit.id)?selectedObjects():[hit];
  return targets.reduce((sum,object)=>sum+selectedColorRemove(object,color,tolerance),0);
 }
 return contiguousRemove(hit,point.x,point.y,tolerance,color);
}
function finishGlobalColorStroke(pointerId){
 const stroke=globalColorRemoval.stroke;
 if(!stroke||pointerId!=null&&stroke.pointerId!==pointerId)return;
 globalColorRemoval.stroke=null;
 setStatus(stroke.removed?'Remoção por cor concluída: '+stroke.removed+' pixels removidos.':'Nenhuma nova cor foi removida no trajeto.');
}
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.dtf-user-guide')||!isRemovalTool())return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit){deactivateSpecialTool();return}removalOptionsApplied=true;updateRemovalOptionControls();e.preventDefault();e.stopImmediatePropagation();const tolerance=state.tool==='globalColorRemove'?globalColorRemovalTolerance():(Number(ui.clickTol&&ui.clickTol.value)||35);
 if(state.tool==='uniformRemove'){uniformRemovalSeed={objectId:hit.id,x:p.x,y:p.y};removalOptionsApplied=false;updateRemovalOptionControls();setStatus('Fundo uniforme capturado. Ajuste a tolerância e clique em Aplicar.');showToolHint('A região conectada ao ponto será removida, inclusive diagonais e halo próximo.');return}
 if(state.tool==='colorRemove'){pushHistory('Antes de remover por clique');const n=contiguousRemove(hit,p.x,p.y,tolerance);render();setStatus('Região conectada removida: '+n+' pixels');return}
 if(state.tool==='outerAreaRemove'){if(outerAreaRemovalBusy){setStatus('Ainda estou analisando a área externa…');return}outerAreaRemovalBusy=true;pushHistory('Antes de remover área externa');progress(8,'Analisando somente a região externa…');removeExternalConnectedArea(hit,p.x,p.y,tolerance,(amount,message)=>progress(amount,message)).then(result=>{render();finish(result.changed?'Área externa removida: '+result.changed+' pixels':'Nenhuma área externa da mesma cor foi encontrada');setStatus(result.changed?'Área externa removida. Furos e áreas internas foram preservados.':'Clique em uma cor externa conectada à borda da imagem.');}).catch(error=>{finish('Falha');setStatus(error&&error.message?error.message:'Não foi possível analisar a área externa.');log('Remoção externa indisponível',error&&error.message?error.message:error)}).finally(()=>{outerAreaRemovalBusy=false});return}
 if(state.tool==='areaRemove'){if(beginSmartAreaStroke(hit,p,tolerance,e.pointerId)){try{viewport.setPointerCapture(e.pointerId)}catch(_){}}return}
 if(state.tool==='globalColorRemove'){
  hideColorCursorPreview();
  pushHistory(colorRemoveScope==='all'?'Antes de remover cor em toda área':'Antes de remover cor conectada');
  const lx=Math.floor((p.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((p.y-hit.y)/hit.h*hit.canvas.height),pixel=hit.canvas.getContext('2d',{willReadFrequently:true}).getImageData(lx,ly,1,1).data;
  if(pixel[3]<8){setStatus('Clique em uma cor visível para capturá-la.');return}
  const color={r:pixel[0],g:pixel[1],b:pixel[2]},n=removeGlobalColorAt(hit,p,tolerance,color);globalColorRemoval.stroke={pointerId:e.pointerId,last:{x:p.x,y:p.y},removed:n,color};render();
  setStatus((n?'Cor capturada e removida. ':'Cor capturada. ')+('RGB '+color.r+', '+color.g+', '+color.b+'. Continue arrastando somente sobre essa mesma cor.'));
  try{viewport.setPointerCapture(e.pointerId)}catch(_){}
  return;
 }
 if(state.tool==='colorAreaRemove'){
  const lx=Math.floor((p.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((p.y-hit.y)/hit.h*hit.canvas.height),pixel=hit.canvas.getContext('2d',{willReadFrequently:true}).getImageData(lx,ly,1,1).data;
  if(!colorAreaRemoval.color){colorAreaRemoval.color={r:pixel[0],g:pixel[1],b:pixel[2]};syncColorAreaBrushPreview();hideColorCursorPreview();updateEraserCursor(e);setStatus('Cor capturada: '+colorAreaRgbLabel(colorAreaRemoval.color)+'. Agora arraste para remover somente essa cor.');showToolHint('Agora arraste onde deseja apagar.');return}
  startColorAreaBrush(hit,{x:p.x,y:p.y,pointerId:e.pointerId,clientX:e.clientX,clientY:e.clientY});try{viewport.setPointerCapture(e.pointerId)}catch(_){}return;
 }
},{capture:true});
viewport.addEventListener('pointermove',e=>{
 if(state.tool==='areaRemove')updateEraserCursor(e);if(!(e.buttons&1))return;
 if(state.tool==='areaRemove'&&smartAreaStroke&&smartAreaStroke.pointerId===e.pointerId){const p=clientToWorkspace(e);if(!p)return;e.preventDefault();e.stopImmediatePropagation();queueSmartAreaStrokePoint(smartAreaStroke,p);return}
 if(state.tool==='globalColorRemove'&&globalColorRemoval.stroke&&globalColorRemoval.stroke.pointerId===e.pointerId){
  const p=clientToWorkspace(e);if(!p)return;e.preventDefault();e.stopImmediatePropagation();
  const stroke=globalColorRemoval.stroke,from=stroke.last,dx=p.x-from.x,dy=p.y-from.y;
  const steps=Math.min(80,Math.max(1,Math.ceil(Math.hypot(dx,dy)/Math.max(4,state.brushSize*.35))));
  const tolerance=globalColorRemovalTolerance();
  for(let i=1;i<=steps;i++){const fraction=i/steps,point={x:from.x+dx*fraction,y:from.y+dy*fraction},hit=hitTest(point.x,point.y);stroke.removed+=removeGlobalColorAt(hit,point,tolerance,stroke.color)}
  stroke.last={x:p.x,y:p.y};render();return
 }
 if(state.tool!=='colorRemove')return;const p=clientToWorkspace(e),hit=p&&hitTest(p.x,p.y);if(!hit)return;e.preventDefault();e.stopImmediatePropagation();contiguousRemove(hit,p.x,p.y,Number(ui.clickTol&&ui.clickTol.value)||35);render()
},{capture:true});
viewport.addEventListener('pointerup',e=>{endSmartAreaStroke(e.pointerId);finishGlobalColorStroke(e.pointerId)},{capture:true});viewport.addEventListener('pointercancel',e=>{endSmartAreaStroke(e.pointerId);finishGlobalColorStroke(e.pointerId)},{capture:true});document.addEventListener('pointerup',e=>{endSmartAreaStroke(e.pointerId);finishGlobalColorStroke(e.pointerId)});document.addEventListener('pointercancel',e=>{endSmartAreaStroke(e.pointerId);finishGlobalColorStroke(e.pointerId)});
viewport.addEventListener('pointerdown',e=>{if(state.space){panDrag={sx:e.clientX,sy:e.clientY,px:state.panX,py:state.panY};try{viewport.setPointerCapture(e.pointerId)}catch(_){}return}
 if(state.tool==='eraser'||state.tool==='restore'){startBrush(e);return}
 if(['drawRect','drawText','drawLine'].includes(state.tool))return;
 const p=clientToWorkspace(e);if(!p)return;const hit=hitTest(p.x,p.y);state.lastClick=p;if(isRemovalTool())return;if(state.tool==='pick'){if(hit){const lx=Math.floor((p.x-hit.x)/hit.w*hit.canvas.width),ly=Math.floor((p.y-hit.y)/hit.h*hit.canvas.height),d=hit.canvas.getContext('2d').getImageData(lx,ly,1,1).data;pickedColor={r:d[0],g:d[1],b:d[2]};ui.pickPreview.value='#'+[d[0],d[1],d[2]].map(v=>v.toString(16).padStart(2,'0')).join('');state.tool='select';setStatus('Cor selecionada: '+ui.pickPreview.value)}return}
 if(e.altKey&&hit){selectAltStackObject(p.x,p.y);state.drag=null;return}
 if(hit){const multiSelect=e.ctrlKey||e.shiftKey;const wasSingleSelected=state.selectedIds.length===1&&state.selectedId===hit.id&&!multiSelect;const toggleTransformCandidate=wasSingleSelected&&isShapeTransformTarget(hit);const keepGroup=!multiSelect&&state.selectedIds.includes(hit.id);if(!keepGroup)selectObject(hit,multiSelect);const moving=selectedObjects();state.drag={o:hit,sx:p.x,sy:p.y,items:moving.map(o=>({o,ox:o.x,oy:o.y})),moved:false,historySaved:false,toggleTransformCandidate}}
 else {selectObject(null);state.marquee={sx:p.x,sy:p.y,ex:p.x,ey:p.y};if(marquee){marquee.style.left=p.x+'px';marquee.style.top=p.y+'px';marquee.style.width='0px';marquee.style.height='0px';marquee.classList.add('show')}}
});
viewport.addEventListener('contextmenu',e=>{e.preventDefault();const menu=$id('dtfCanvasMenu'),guideTarget=e.target.closest('.dtf-user-guide');if(guideTarget){const guideId=String(guideTarget.dataset.guideId||''),guide=state.customGuides.find(item=>item.id===guideId);if(!guide)return;state.selectedGuideId=guide.id;updateSelectedGuideStyle();if(menu){menu.classList.add('guide-context');if(guideDeleteMenu)guideDeleteMenu.style.display='block';menu.style.left=e.clientX+'px';menu.style.top=e.clientY+'px';menu.classList.add('show');menu.focus()}return}state.selectedGuideId=null;renderCustomGuides();const p=clientToWorkspace(e);const hit=p&&hitTest(p.x,p.y);if(hit&&!state.selectedIds.includes(hit.id))selectObject(hit,false);renderIntelligentContext(hit||null);if(menu){menu.classList.remove('guide-context');if(guideDeleteMenu)guideDeleteMenu.style.display='none';updateGroupMenu();menu.style.left=e.clientX+'px';menu.style.top=e.clientY+'px';menu.classList.add('show');menu.focus()} });
document.addEventListener('pointerdown',e=>{const menu=$id('dtfCanvasMenu');if(menu&&!e.target.closest('#dtfCanvasMenu'))menu.classList.remove('show')});
// Guias de encaixe entre objetos: visíveis somente durante o arraste.
const objectSnapGuideOverlay=document.createElement('div'),objectSnapGuideVertical=document.createElement('i'),objectSnapGuideHorizontal=document.createElement('i');
objectSnapGuideOverlay.className='dtf-object-snap-guides';objectSnapGuideVertical.className='dtf-object-snap-guide dtf-object-snap-guide-v';objectSnapGuideHorizontal.className='dtf-object-snap-guide dtf-object-snap-guide-h';objectSnapGuideOverlay.append(objectSnapGuideVertical,objectSnapGuideHorizontal);inner.appendChild(objectSnapGuideOverlay);
function clearObjectSnapGuides(){objectSnapGuideOverlay.classList.remove('show','show-x','show-y');objectSnapGuideVertical.style.removeProperty('left');objectSnapGuideHorizontal.style.removeProperty('top')}
function objectSnapGuideMatches(anchor,dx,dy){
 if(!state.drag)return {x:null,y:null};const threshold=1.2,movingX=anchor.ox+dx,movingY=anchor.oy+dy,xs=[movingX,movingX+anchor.o.w/2,movingX+anchor.o.w],ys=[movingY,movingY+anchor.o.h/2,movingY+anchor.o.h];let bestX=null,bestY=null;
 const targets=state.drag.snapTargets||[];
 targets.forEach(target=>{const tx=[target.x,target.x+target.w/2,target.x+target.w],ty=[target.y,target.y+target.h/2,target.y+target.h];xs.forEach((value,edge)=>tx.forEach(position=>{const distance=Math.abs(position-value);if(distance<=threshold&&(!bestX||distance<bestX.distance))bestX={position,target,edge,distance}}));ys.forEach((value,edge)=>ty.forEach(position=>{const distance=Math.abs(position-value);if(distance<=threshold&&(!bestY||distance<bestY.distance))bestY={position,target,edge,distance}}))});
 return {x:bestX,y:bestY}
}
function showObjectSnapGuides(matches){
 const showX=!!(matches&&matches.x),showY=!!(matches&&matches.y);objectSnapGuideOverlay.classList.toggle('show',showX||showY);objectSnapGuideOverlay.classList.toggle('show-x',showX);objectSnapGuideOverlay.classList.toggle('show-y',showY);
 if(showX)objectSnapGuideVertical.style.left=matches.x.position+'px';if(showY)objectSnapGuideHorizontal.style.top=matches.y.position+'px';
}
function snapToNearestObject(anchor,dx,dy){const threshold=Math.max(6,physicalMmToPx(2)),movingX=anchor.ox+dx,movingY=anchor.oy+dy,xs=[movingX,movingX+anchor.o.w/2,movingX+anchor.o.w],ys=[movingY,movingY+anchor.o.h/2,movingY+anchor.o.h];let bestX=null,bestY=null;const targets=state.drag.snapTargets||(state.drag.snapTargets=state.objects.filter(o=>o.visible&&o.id!==anchor.o.id&&!state.drag.items.some(item=>item.o.id===o.id)));targets.forEach(t=>{const tx=[t.x,t.x+t.w/2,t.x+t.w],ty=[t.y,t.y+t.h/2,t.y+t.h];xs.forEach((v,i)=>tx.forEach(target=>{const d=target-v;if(Math.abs(d)<=threshold&&(!bestX||Math.abs(d)<Math.abs(bestX.d)))bestX={d,edge:i}}));ys.forEach((v,i)=>ty.forEach(target=>{const d=target-v;if(Math.abs(d)<=threshold&&(!bestY||Math.abs(d)<Math.abs(bestY.d)))bestY={d,edge:i}}))});return {dx:bestX?dx+bestX.d:dx,dy:bestY?dy+bestY.d:dy}}
function snapToGuides(anchor,dx,dy){const threshold=Math.max(6,physicalMmToPx(2)),inset=Math.min(canvas.width,canvas.height,physicalMmToPx(5)),movingX=anchor.ox+dx,movingY=anchor.oy+dy,xs=[movingX,movingX+anchor.o.w/2,movingX+anchor.o.w],ys=[movingY,movingY+anchor.o.h/2,movingY+anchor.o.h],targetsX=state.customGuides.filter(guide=>guide.orientation==='v').map(guideCanvasPosition),targetsY=state.customGuides.filter(guide=>guide.orientation==='h').map(guideCanvasPosition);if(guidesEnabled){targetsX.push(inset,canvas.width/2,canvas.width-inset,0,canvas.width);targetsY.push(inset,canvas.height/2,canvas.height-inset,0,canvas.height)}let bestX=null,bestY=null;xs.forEach((value,index)=>targetsX.forEach(target=>{const d=target-value;if(Math.abs(d)<=threshold&&(!bestX||Math.abs(d)<Math.abs(bestX.d)))bestX={d,index}}));ys.forEach((value,index)=>targetsY.forEach(target=>{const d=target-value;if(Math.abs(d)<=threshold&&(!bestY||Math.abs(d)<Math.abs(bestY.d)))bestY={d,index}}));return {dx:bestX?dx+bestX.d:dx,dy:bestY?dy+bestY.d:dy}}
let dragRenderQueued=false;function scheduleDragRender(){if(dragRenderQueued)return;dragRenderQueued=true;requestAnimationFrame(()=>{dragRenderQueued=false;if(state.drag)render()})}
viewport.addEventListener('pointermove',e=>{if(panDrag){state.panX=panDrag.px+(e.clientX-panDrag.sx);state.panY=panDrag.py+(e.clientY-panDrag.sy);applyZoom();return}if(state.marquee){const p=clientToWorkspace(e);if(!p)return;state.marquee.ex=p.x;state.marquee.ey=p.y;const x=Math.min(state.marquee.sx,p.x),y=Math.min(state.marquee.sy,p.y),w=Math.abs(p.x-state.marquee.sx),h=Math.abs(p.y-state.marquee.sy);marquee.style.left=x+'px';marquee.style.top=y+'px';marquee.style.width=w+'px';marquee.style.height=h+'px';return}if(state.drag){const p=clientToWorkspace(e);if(!p)return;let dx=p.x-state.drag.sx,dy=p.y-state.drag.sy;const minDx=Math.max(...state.drag.items.map(({o,ox})=>-ox)),maxDx=Math.min(...state.drag.items.map(({o,ox})=>canvas.width-o.w-ox));const minDy=Math.max(...state.drag.items.map(({o,oy})=>-oy)),maxDy=Math.min(...state.drag.items.map(({o,oy})=>canvas.height-o.h-oy));dx=clamp(dx,minDx,maxDx);dy=clamp(dy,minDy,maxDy);const anchor=state.drag.items[0];if(gridEnabled){dx=snapGrid(anchor.ox+dx)-anchor.ox;dy=snapGrid(anchor.oy+dy)-anchor.oy}if(objectSnapEnabled){const snapped=snapToNearestObject(anchor,dx,dy);dx=snapped.dx;dy=snapped.dy}if(guidesEnabled||state.customGuides.length){const snapped=snapToGuides(anchor,dx,dy);dx=snapped.dx;dy=snapped.dy}dx=clamp(dx,minDx,maxDx);dy=clamp(dy,minDy,maxDy);if(objectSnapEnabled)showObjectSnapGuides(objectSnapGuideMatches(anchor,dx,dy));else clearObjectSnapGuides();if(Math.abs(dx)<.001&&Math.abs(dy)<.001)return;if(!state.drag.historySaved){pushHistory('Antes de mover objeto');state.drag.historySaved=true}state.drag.items.forEach(({o,ox,oy})=>{o.x=ox+dx;o.y=oy+dy});state.drag.moved=true;scheduleDragRender();return}clearObjectSnapGuides();updateEraserCursor(e)});
viewport.addEventListener('pointerup',e=>{clearObjectSnapGuides();if(panDrag){panDrag=null;return}if(state.marquee){selectionTransformMode=false;const m=state.marquee;const x1=Math.min(m.sx,m.ex),y1=Math.min(m.sy,m.ey),x2=Math.max(m.sx,m.ex),y2=Math.max(m.sy,m.ey);const picked=state.objects.filter(o=>o.visible&&o.x<x2&&o.x+o.w>x1&&o.y<y2&&o.y+o.h>y1);state.selectedIds=picked.map(o=>o.id);state.selectedId=picked.length?picked[picked.length-1].id:null;state.marquee=null;if(marquee)marquee.classList.remove('show');updateSelection();updateObjectUI();setStatus(picked.length+' objetos selecionados');return}if(state.drag){const drag=state.drag,moved=drag.moved,toggle=drag.toggleTransformCandidate&&!moved;state.drag=null;if(toggle){selectionTransformMode=!selectionTransformMode;updateSelection();setStatus(selectionTransformMode?'Modo girar/inclinar ativo':'Modo redimensionar ativo');return}else if(moved){render();updateObjectUI();setStatus('Objeto movido')}}finishBrush(e)});viewport.addEventListener('pointercancel',e=>{clearObjectSnapGuides();panDrag=null;state.drag=null;state.marquee=null;if(marquee)marquee.classList.remove('show');render();finishBrush(e)});
document.addEventListener('pointerup',e=>{clearObjectSnapGuides();if(state.drag){const drag=state.drag,moved=drag.moved,toggle=drag.toggleTransformCandidate&&!moved;state.drag=null;if(toggle){selectionTransformMode=!selectionTransformMode;updateSelection();setStatus(selectionTransformMode?'Modo girar/inclinar ativo':'Modo redimensionar ativo')}else if(moved){render();setStatus('Objeto movido')}}if(panDrag)panDrag=null;finishBrush(e)});

let dragAutoScrollTimer=0,dragAutoScrollX=0,dragAutoScrollY=0,dragAutoScrollActive=false;
function stopDragAutoScroll(){if(dragAutoScrollTimer){clearInterval(dragAutoScrollTimer);dragAutoScrollTimer=0}dragAutoScrollActive=false}
function dragAutoScrollTick(){if(!state.drag){stopDragAutoScroll();return}const rect=viewport.getBoundingClientRect(),margin=34,x=state.drag.lastClientX,y=state.drag.lastClientY;if(!Number.isFinite(x)||!Number.isFinite(y)){stopDragAutoScroll();return}const vx=x<rect.left+margin? -Math.max(2,Math.round((rect.left+margin-x)/6)):x>rect.right-margin?Math.max(2,Math.round((x-(rect.right-margin))/6)):0,vy=y<rect.top+margin? -Math.max(2,Math.round((rect.top+margin-y)/6)):y>rect.bottom-margin?Math.max(2,Math.round((y-(rect.bottom-margin))/6)):0;if(!vx&&!vy){stopDragAutoScroll();return}viewport.scrollLeft=clamp(viewport.scrollLeft+vx,0,Math.max(0,viewport.scrollWidth-viewport.clientWidth));viewport.scrollTop=clamp(viewport.scrollTop+vy,0,Math.max(0,viewport.scrollHeight-viewport.clientHeight));try{viewport.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,cancelable:true,clientX:x,clientY:y,buttons:1,pointerId:1,pointerType:'mouse'}))}catch(_){} }
viewport.addEventListener('pointermove',e=>{if(!state.drag)return;state.drag.lastClientX=e.clientX;state.drag.lastClientY=e.clientY;const rect=viewport.getBoundingClientRect(),margin=34,near=e.clientX<rect.left+margin||e.clientX>rect.right-margin||e.clientY<rect.top+margin||e.clientY>rect.bottom-margin;if(near&&!dragAutoScrollTimer){dragAutoScrollActive=true;dragAutoScrollTimer=setInterval(dragAutoScrollTick,16)}else if(!near)stopDragAutoScroll()},{capture:true});
document.addEventListener('pointerup',stopDragAutoScroll,{capture:true});document.addEventListener('pointercancel',stopDragAutoScroll,{capture:true});window.addEventListener('blur',stopDragAutoScroll);
let intelligentContextObject=null;
const intelligentMenu=document.createElement('div'),intelligentMenuButton=document.createElement('button'),intelligentSubmenu=document.createElement('div');
intelligentMenu.className='dtf-properties-menu dtf-intelligent-menu';intelligentMenuButton.type='button';intelligentMenuButton.textContent='Ver miniaturas inteligentes ▸';intelligentSubmenu.className='dtf-properties-submenu dtf-intelligent-submenu';intelligentMenu.append(intelligentMenuButton,intelligentSubmenu);if(canvasMenu)canvasMenu.appendChild(intelligentMenu);
function intelligentVariantTargets(object){
 if(!object)return[];const ids=new Set([object.id]);if(state.selectedIds.includes(object.id))state.selectedIds.forEach(id=>ids.add(id));if(object.groupId)state.objects.filter(item=>item.groupId===object.groupId).forEach(item=>ids.add(item.id));return state.objects.filter(item=>ids.has(item.id));
}
function applyIntelligentVariant(object,option,optionIndex=0){
 if(!object||!option)return;const changes=[];intelligentVariantTargets(object).forEach(target=>{const set=state.intelligentVariantSets[target.intelligentVariantSetId],targetOption=set&&set.options&&(set.options.find(item=>item.mode===option.mode)||set.options.find(item=>item.label===option.label)||set.options[optionIndex]);if(targetOption)changes.push({target,option:targetOption})});if(!changes.length)return;pushHistory(changes.length>1?'Trocar recorte inteligente em vários objetos':'Trocar recorte inteligente');changes.forEach(({target,option:targetOption})=>{const trim=magicVariantTrim(targetOption),rotated=target.intelligentVariantRotated===true,oldTrimW=Math.max(1,Number(target.aiTrimW)||trim.w),oldTrimH=Math.max(1,Number(target.aiTrimH)||trim.h),scaleX=target.w/(rotated?oldTrimH:oldTrimW),scaleY=target.h/(rotated?oldTrimW:oldTrimH),art=rotated?rotateCanvas90(trim.canvas):trim.canvas;target.canvas=canvasClone(art);target.baseCanvas=canvasClone(art);target.restoreCanvas=canvasClone(art);target.w=Math.max(1,(rotated?trim.h:trim.w)*scaleX);target.h=Math.max(1,(rotated?trim.w:trim.h)*scaleY);target.x=clamp(target.x,0,Math.max(0,canvas.width-target.w));target.y=clamp(target.y,0,Math.max(0,canvas.height-target.h));target.aiTrimW=trim.w;target.aiTrimH=trim.h;target.aiFrameW=target.aiFrameW||targetOption.canvas.width;target.aiFrameH=target.aiFrameH||targetOption.canvas.height;target.originalW=target.canvas.width;target.originalH=target.canvas.height;target.intelligentVariantId=targetOption.id});render();updateSelection();updateObjectUI();setStatus(changes.length+' imagem'+(changes.length===1?' alterada':'ens alteradas')+' para “'+option.label+'”');
}
function renderIntelligentContext(object){
 intelligentContextObject=object||null;const set=object&&state.intelligentVariantSets[object.intelligentVariantSetId],count=set&&set.options?set.options.length:0;intelligentSubmenu.innerHTML='';intelligentMenu.style.display=count?'block':'none';intelligentMenu.classList.toggle('disabled',count===1);intelligentMenuButton.disabled=count===1;intelligentMenuButton.title=count===1?'Somente uma miniatura inteligente disponível':'';if(!count||count===1)return;
 set.options.forEach((option,optionIndex)=>{const button=document.createElement('button'),image=document.createElement('img'),preview=magicVariantTrim(option);button.type='button';button.className='dtf-intelligent-option';button.title=option.label;button.setAttribute('aria-label',(option.id===object.intelligentVariantId?'Selecionado: ':'Usar ')+option.label);if(option.id===object.intelligentVariantId)button.classList.add('selected');image.src=magicPreviewData(preview.canvas,76,object.w,object.h);image.alt='';if(object.intelligentVariantRotated)image.classList.add('rotated');button.appendChild(image);button.onclick=event=>{event.stopPropagation();const live=state.objects.find(item=>item.id===intelligentContextObject?.id);if(live)applyIntelligentVariant(live,option,optionIndex);canvasMenu.classList.remove('show')};intelligentSubmenu.appendChild(button)});
}

function eraserRadiusWorkspace(o){return state.brushSize}
function updateEraserCursor(e){
 if(!e&& !state.lastPointer)return;
 let p=null;if(e)p=clientToWorkspace(e);else p=state.lastPointer;if(p)state.lastPointer=p;
 const areaTool=state.tool==='areaRemove',colorAreaTool=state.tool==='colorAreaRemove'&&!!colorAreaRemoval.color;
 if(state.tool!=='eraser'&&state.tool!=='restore'&&!areaTool&&!colorAreaTool){eraserCursor.classList.remove('show','dtf-area-cursor');return}
 if(!p)return;
 const diameter=areaTool?smartAreaCursorRadius()*2:state.brushSize,offset=diameter/2;
 eraserCursor.style.width=diameter+'px';eraserCursor.style.height=diameter+'px';eraserCursor.style.left=(p.x-offset)+'px';eraserCursor.style.top=(p.y-offset)+'px';
 eraserCursor.classList.toggle('dtf-area-cursor',areaTool||colorAreaTool);eraserCursor.classList.add('show')
}
function removeCapturedColorInBrush(object,wx,wy,color){
 const canvasObj=object.canvas,context=canvasObj.getContext('2d',{willReadFrequently:true});
 const lx=(wx-object.x)/object.w*canvasObj.width,ly=(wy-object.y)/object.h*canvasObj.height;
 const rx=state.brushSize/object.w*canvasObj.width/2,ry=state.brushSize/object.h*canvasObj.height/2;
 const sx=Math.max(0,Math.floor(lx-rx)),sy=Math.max(0,Math.floor(ly-ry)),sw=Math.min(canvasObj.width-sx,Math.ceil(rx*2)),sh=Math.min(canvasObj.height-sy,Math.ceil(ry*2));
 if(sw<=0||sh<=0)return 0;
 const image=context.getImageData(sx,sy,sw,sh),pixels=image.data,threshold=28;
 let changed=0;
 for(let y=0;y<sh;y++)for(let x=0;x<sw;x++){
  const dx=x+sx-lx,dy=y+sy-ly;
  if((dx*dx)/(rx*rx)+(dy*dy)/(ry*ry)>1)continue;
  const i=(y*sw+x)*4;
  if(!pixels[i+3])continue;
  if(Math.hypot(pixels[i]-color.r,pixels[i+1]-color.g,pixels[i+2]-color.b)<=threshold){pixels[i+3]=0;changed++}
 }
 if(changed){context.putImageData(image,sx,sy);clearDisplayPreview(object)}
 return changed
}
function removeCapturedColorInBrushTargets(targets,wx,wy){let changed=0;targets.forEach(object=>{changed+=removeCapturedColorInBrush(object,wx,wy,colorAreaRemoval.color)});return changed}
function startColorAreaBrush(hit,pointerEvent){
 const targets=state.selectedIds.includes(hit.id)&&selectedObjects().length?selectedObjects():[hit];
 pushHistory('Antes de remover área por cor');
 colorAreaRemoval.stroke={last:{x:pointerEvent.x,y:pointerEvent.y},targets,pointerId:pointerEvent.pointerId};
 removeCapturedColorInBrushTargets(targets,pointerEvent.x,pointerEvent.y);render();updateEraserCursor({clientX:pointerEvent.clientX,clientY:pointerEvent.clientY});
 setStatus('Arraste para remover somente a cor capturada')
}
function continueColorAreaBrush(e){
 if(!colorAreaRemoval.stroke)return;const p=clientToWorkspace(e);if(!p)return;const from=colorAreaRemoval.stroke.last,dx=p.x-from.x,dy=p.y-from.y,dist=Math.hypot(dx,dy),steps=Math.max(1,Math.ceil(dist/Math.max(2,state.brushSize*.28)));
 for(let i=1;i<=steps;i++){const t=i/steps;removeCapturedColorInBrushTargets(colorAreaRemoval.stroke.targets,from.x+dx*t,from.y+dy*t)}colorAreaRemoval.stroke.last=p;render();updateEraserCursor(e)
}
function finishColorAreaBrush(){if(colorAreaRemoval.stroke){colorAreaRemoval.stroke=null;setStatus('Remoção de área por cor concluída')}}

let brushStroke=null;
let resizeDrag=null;
function brushAt(o,wx,wy,erase=true){const lx=(wx-o.x)/o.w*o.canvas.width,ly=(wy-o.y)/o.h*o.canvas.height;const rx=state.brushSize/o.w*o.canvas.width/2,ry=state.brushSize/o.h*o.canvas.height/2;const c=o.canvas,cc=c.getContext('2d');if(erase){cc.save();cc.globalCompositeOperation='destination-out';cc.beginPath();cc.ellipse(lx,ly,Math.max(1,rx),Math.max(1,ry),0,0,Math.PI*2);cc.fill();cc.restore()}else{const source=o.restoreCanvas||o.baseCanvas;const base=source.getContext('2d');const sx=Math.max(0,Math.floor(lx-rx)),sy=Math.max(0,Math.floor(ly-ry)),sw=Math.min(c.width-sx,Math.ceil(rx*2)),sh=Math.min(c.height-sy,Math.ceil(ry*2));if(sw>0&&sh>0){const crop=base.getImageData(sx,sy,sw,sh);const out=cc.getImageData(sx,sy,sw,sh);for(let y=0;y<sh;y++)for(let x=0;x<sw;x++){const dx=x+sx-lx,dy=y+sy-ly;if((dx*dx)/(rx*rx)+(dy*dy)/(ry*ry)<=1){const i=(y*sw+x)*4;out.data[i]=crop.data[i];out.data[i+1]=crop.data[i+1];out.data[i+2]=crop.data[i+2];out.data[i+3]=crop.data[i+3]}}cc.putImageData(out,sx,sy)}}}
function brushAtAll(wx,wy,erase=true){for(const o of state.objects)brushAt(o,wx,wy,erase)}
function startBrush(e){const p=clientToWorkspace(e);if(!p)return;if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}pushHistory(state.tool==='eraser'?'Antes da borracha':'Antes do restaurador');brushStroke={last:p};brushAtAll(p.x,p.y,state.tool==='eraser');render();updateEraserCursor(e);setStatus(state.tool==='eraser'?'Borra circular ativa em toda a tela':'Restaurador circular ativo em toda a tela')}
function continueBrush(e){if(!brushStroke)return;const p=clientToWorkspace(e);if(!p)return;const from=brushStroke.last,dx=p.x-from.x,dy=p.y-from.y,dist=Math.hypot(dx,dy),steps=Math.max(1,Math.ceil(dist/(state.brushSize*.28)));for(let i=1;i<=steps;i++){const t=i/steps;brushAtAll(from.x+dx*t,from.y+dy*t,state.tool==='eraser')}brushStroke.last=p;render();updateEraserCursor(e)}
function finishBrush(){if(brushStroke){brushStroke=null;setStatus('Edição da borracha concluída')}}
document.addEventListener('pointerup',e=>{if(colorAreaRemoval.stroke&&e.pointerId===colorAreaRemoval.stroke.pointerId)finishColorAreaBrush()},{capture:true});
document.addEventListener('pointercancel',e=>{if(colorAreaRemoval.stroke&&e.pointerId===colorAreaRemoval.stroke.pointerId)finishColorAreaBrush()},{capture:true});
function deactivateBrush(){state.tool='select';brushStroke=null;state.lastPointer=null;editorRoot.classList.remove('dtf-brush-measure-active');eraserCursor.classList.remove('show','dtf-area-cursor');ui.eraser.classList.remove('active');ui.restoreBrush.classList.remove('active');ui.eraser.setAttribute('aria-pressed','false');ui.restoreBrush.setAttribute('aria-pressed','false')}
viewport.addEventListener('pointermove',e=>{if(brushStroke)continueBrush(e);else if(colorAreaRemoval.stroke)continueColorAreaBrush(e);else updateEraserCursor(e)});
viewport.addEventListener('pointerleave',()=>eraserCursor.classList.remove('show'));

ui.eraser.addEventListener('click',()=>{if(state.tool==='eraser'){deactivateBrush();setStatus('Borra desativada');return}if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();state.tool='eraser';editorRoot.classList.add('dtf-brush-measure-active');ui.eraser.classList.add('active');ui.eraser.setAttribute('aria-pressed','true');ui.restoreBrush.setAttribute('aria-pressed','false');setStatus('Borracha circular ativa');showToolHint('Arraste para apagar. M+scroll ajusta.')});
ui.restoreBrush.addEventListener('click',()=>{if(state.tool==='restore'){deactivateBrush();setStatus('Restaurador desativado');return}if(!state.objects.length){setStatus('Carregue uma imagem primeiro');return}setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();state.tool='restore';editorRoot.classList.add('dtf-brush-measure-active');ui.restoreBrush.classList.add('active');ui.restoreBrush.setAttribute('aria-pressed','true');ui.eraser.setAttribute('aria-pressed','false');setStatus('Restaurador circular ativo');showToolHint('Arraste para restaurar. M+scroll ajusta.');updateEraserCursor()});
function activateRemovalTool(tool,button,status){
 if(!selectedObjects().length){setStatus('Selecione um objeto primeiro');return false}
 setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();state.tool=tool;if(ui.colorAreaBrushPreview)ui.colorAreaBrushPreview.style.setProperty('display',tool==='colorAreaRemove'?'inline-flex':'none','important');[clickRemove,areaRemoveButton,outerAreaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton].forEach(item=>{if(item){const active=item===button;item.classList.toggle('active',active);item.setAttribute('aria-pressed',String(active))}});if(ui.reset){ui.reset.classList.remove('active');ui.reset.setAttribute('aria-pressed','false')}editorRoot.classList.add('dtf-color-remove-cursor');editorRoot.classList.toggle('dtf-area-remove-cursor',tool==='areaRemove');removalOptionsApplied=true;updateRemovalOptionControls();setStatus(tool==='globalColorRemove'?'Clique em uma cor para capturá-la; depois arraste somente sobre essa mesma cor.':status+' Ajuste a borda e use na imagem.');if(tool==='colorRemove')showToolHint('Clique na cor que deseja remover. B+scroll ajusta a borda.');else if(tool==='outerAreaRemove')showToolHint('Clique na cor externa. Só a região ligada à borda será removida; furos internos ficam protegidos.');else if(tool==='areaRemove')showToolHint('Arraste o círculo sobre a área. B+scroll ajusta borda e M+scroll a medida.');else if(tool==='globalColorRemove')showToolHint('Clique na cor desejada. Depois arraste somente nessa mesma cor.');return true;
}
function activateClickRemove(status='Clique em uma cor para remover somente a região conectada'){return activateRemovalTool('colorRemove',clickRemove,status)}
if(clickRemove)clickRemove.addEventListener('click',()=>{if(state.tool==='colorRemove')deactivateSpecialTool();else activateClickRemove()});
if(areaRemoveButton)areaRemoveButton.addEventListener('click',()=>{if(state.tool==='areaRemove')deactivateSpecialTool();else if(activateRemovalTool('areaRemove',areaRemoveButton,'Remoção de fundo de área (com IA): arraste o círculo sobre qualquer sobra. A decisão é recalculada em cada trecho sem atravessar os contornos.'))selectedObjects().forEach(scheduleSmartAreaGuidePreparation)});
if(outerAreaRemoveButton)outerAreaRemoveButton.addEventListener('click',()=>{if(state.tool==='outerAreaRemove')deactivateSpecialTool();else activateRemovalTool('outerAreaRemove',outerAreaRemoveButton,'Remover somente a área externa conectada à borda')});
if(globalColorRemoveButton)globalColorRemoveButton.addEventListener('click',()=>{if(state.tool==='globalColorRemove')deactivateSpecialTool();else activateRemovalTool('globalColorRemove',globalColorRemoveButton,'Remover por cor')});
if(colorAreaRemoveButton)colorAreaRemoveButton.addEventListener('click',()=>{if(state.tool==='colorAreaRemove')deactivateSpecialTool();else{if(!selectedObjects().length){setStatus('Selecione um objeto primeiro');return}deactivateSpecialTool(true);deactivateBrush();state.tool='colorAreaRemove';colorAreaRemoval.color=null;syncColorAreaBrushPreview();hideColorCursorPreview();[clickRemove,areaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton].forEach(item=>{if(item){const active=item===colorAreaRemoveButton;item.classList.toggle('active',active);item.setAttribute('aria-pressed',String(active))}});if(ui.reset){ui.reset.classList.remove('active');ui.reset.setAttribute('aria-pressed','false')}editorRoot.classList.add('dtf-color-remove-cursor');removalOptionsApplied=true;updateRemovalOptionControls();eraserCursor.classList.remove('show');setStatus('Clique em uma cor para capturar. Depois arraste para remover somente essa cor.');showToolHint('Clique na cor desejada. M+scroll ajusta a medida.')}});
if(ui.colorRemoveConnected)ui.colorRemoveConnected.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();colorRemoveScope='connected';updateRemovalOptionControls();setStatus('Modo: apenas áreas conectadas. Clique na cor da imagem.');});
if(ui.colorRemoveAll)ui.colorRemoveAll.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();colorRemoveScope='all';updateRemovalOptionControls();setStatus('Modo: em toda área. Clique na cor da imagem.');});

if(ui.clickTol&&ui.clickTolValue)ui.clickTol.addEventListener('input',()=>{ui.clickTolValue.textContent=ui.clickTol.value;markRemovalOptionsChanged('Borda '+ui.clickTol.value);});
ui.brush.addEventListener('input',()=>setBrushSizeValue(ui.brush.value,false));
if(ui.colorAreaBrush)ui.colorAreaBrush.addEventListener('input',()=>{setBrushSizeValue(ui.colorAreaBrush.value,false);markRemovalOptionsChanged('Medida '+state.brushSize);});
if(ui.colorAreaBrushPreview)ui.colorAreaBrushPreview.addEventListener('click',()=>{if(state.tool!=='colorAreaRemove'){setStatus('Ative a Borracha por seleção de cor para capturar outra cor');return}resetColorAreaBrushColor();showToolHint('Clique na imagem para capturar outra cor.')});
if(ui.removalApply)ui.removalApply.addEventListener('click',()=>{const mode=currentRemovalOptionMode();if(!mode){updateRemovalOptionControls();return}if(mode==='tolerance'){if(state.tool==='uniformRemove')applyUniformBackgroundRemoval();else applyAutoBackgroundRemoval();}});


function copySelected(){const items=selectedObjects();if(!items.length)return;state.clipboard=items.map(o=>({...o,canvas:cloneCanvasData(o.canvas),baseCanvas:cloneCanvasData(o.baseCanvas),restoreCanvas:o.restoreCanvas?cloneCanvasData(o.restoreCanvas):cloneCanvasData(o.baseCanvas||o.canvas),aiOriginal:o.aiOriginalCanvas?cloneCanvasData(o.aiOriginalCanvas):null}));updateContextTools();setStatus(items.length+' objeto'+(items.length===1?'':'s')+' copiado'+(items.length===1?'':'s'))}
let pasteOffset=0;function pasteClipboard(){if(!state.clipboard.length)return;pushHistory('Antes de colar objetos');pasteOffset+=Math.max(12,Math.round(state.dpi/25.4*4));const pasted=state.clipboard.map((s,i)=>({...s,id:uid(),name:s.name+' cópia',x:clamp(s.x+pasteOffset,0,Math.max(0,canvas.width-s.w)),y:clamp(s.y+pasteOffset,0,Math.max(0,canvas.height-s.h)),canvas:canvasFromData(s.canvas),baseCanvas:canvasFromData(s.baseCanvas),restoreCanvas:canvasFromData(s.restoreCanvas||s.baseCanvas||s.canvas),aiOriginalCanvas:s.aiOriginal?canvasFromData(s.aiOriginal):null}));state.objects.push(...pasted);normalizeObjectNames();state.selectedIds=pasted.map(o=>o.id);state.selectedId=pasted[pasted.length-1].id;render();updateObjectUI();setStatus(pasted.length+' objeto'+(pasted.length===1?'':'s')+' colado'+(pasted.length===1?'':'s'))}
function groupSelected(){const items=selectedObjects();if(items.length<2){setStatus('Selecione pelo menos dois objetos');return}if(items.some(o=>o.groupId)){setStatus('Desagrupe os objetos antes de criar um novo grupo');return}pushHistory('Agrupar objetos');const id=uid();items.forEach(o=>o.groupId=id);updateContextTools();render();setStatus('Objetos agrupados')}
function ungroupSelected(){const items=selectedObjects(),grouped=items.filter(o=>o.groupId);if(!grouped.length){setStatus('Selecione objetos agrupados');return}pushHistory('Desagrupar objetos');const groupIds=new Set(grouped.map(o=>o.groupId));state.objects.filter(o=>groupIds.has(o.groupId)).forEach(o=>delete o.groupId);updateContextTools();render();setStatus('Objetos desagrupados')}

/* Busca rápida de comandos: Ctrl+K abre uma paleta pesquisável sem retirar
 * espaço permanente da faixa de ferramentas. Os atalhos seguem o padrão
 * mais conhecido do Corel Draw para edição e alinhamento. */
const commandPalette=document.createElement('div'),commandInput=document.createElement('input'),commandResults=document.createElement('div');
commandPalette.className='dtf-command-palette';commandPalette.setAttribute('role','dialog');commandPalette.setAttribute('aria-label','Buscar comando');commandInput.type='search';commandInput.placeholder='Digite uma ferramenta ou comando…';commandInput.setAttribute('aria-label','Buscar comando');commandResults.className='dtf-command-results';commandPalette.append(commandInput,commandResults);document.body.appendChild(commandPalette);
let localAiSettingsModal=null,localAiSettingsStatus=null;
function localAiStars(value){const score=Math.max(0,Math.min(5,Number(value)||0));return '★★★★★'.split('').map((star,index)=>'<span class="'+(index<score?'is-on':'')+'">'+star+'</span>').join('')}
function localAiExample(kind){
 const accent=kind==='precision'?'#f59e0b':kind==='fast'?'#22c55e':'#0ea5e9';
 return '<svg viewBox="0 0 180 74" role="img" aria-label="Exemplo visual do modelo"><rect width="180" height="74" rx="8" fill="#e5e7eb"/><path d="M0 18h180M0 36h180M0 54h180M45 0v74M90 0v74M135 0v74" stroke="#cbd5e1" stroke-width="1"/><circle cx="47" cy="39" r="19" fill="'+accent+'" opacity=".9"/><path d="M40 39l6 6 12-16" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M91 54c8-28 18-35 30-18 9 13 14 5 20-12" fill="none" stroke="#111827" stroke-width="5" stroke-linecap="round"/><path d="M94 55c8-24 18-30 27-16 8 11 15 5 21-13" fill="none" stroke="'+accent+'" stroke-width="2" stroke-linecap="round"/></svg>';
}
function ensureLocalAiSettingsModal(){
 if(localAiSettingsModal)return;
 localAiSettingsModal=document.createElement('div');localAiSettingsModal.className='dtf-properties-modal dtf-local-ai-settings-modal';localAiSettingsModal.setAttribute('role','dialog');localAiSettingsModal.setAttribute('aria-modal','true');localAiSettingsModal.setAttribute('aria-labelledby','dtfLocalAiTitle');
 const box=document.createElement('div');box.className='dtf-properties-box dtf-local-ai-settings-box';box.innerHTML='<div class="dtf-local-ai-header"><div><h3 id="dtfLocalAiTitle">Configurações de IA local</h3><p>Escolha a IA usada na remoção de fundo e na montagem inteligente. A selecionada é a que será usada pelo editor.</p></div><button type="button" class="dtf-local-ai-close" aria-label="Fechar">×</button></div><label class="dtf-local-ai-auto"><input type="checkbox" data-ai-auto> <span><b>Escolher automaticamente (recomendado)</b><small>Analisa cada imagem e usa a IA mais adequada entre rapidez, equilíbrio e precisão.</small></span></label><div class="dtf-local-ai-models"></div><small class="dtf-local-ai-note">Cada modelo é baixado uma vez e fica salvo no cache do navegador. A imagem permanece no seu computador.</small><div class="dtf-local-ai-status" role="status" aria-live="polite"></div><div class="dtf-properties-actions"><button type="button" data-ai-settings-close>Fechar</button></div>';
 localAiSettingsModal.appendChild(box);document.body.appendChild(localAiSettingsModal);localAiSettingsStatus=box.querySelector('.dtf-local-ai-status');const list=box.querySelector('.dtf-local-ai-models');const autoToggle=box.querySelector('[data-ai-auto]');autoToggle.addEventListener('change',()=>{setLocalAiAuto(autoToggle.checked);renderLocalAiSettings();if(localAiSettingsStatus)localAiSettingsStatus.textContent=autoToggle.checked?'Seleção automática ativada.':'Seleção manual ativada.'});
 Object.values(LOCAL_AI_MODELS).forEach(model=>{const card=document.createElement('div');card.className='dtf-local-ai-card';card.dataset.modelId=model.id;card.innerHTML='<label class="dtf-local-ai-card-select"><input type="radio" name="dtfLocalAiModel" value="'+model.id+'"><span>'+model.name+'</span></label><div class="dtf-local-ai-example">'+localAiExample(model.example)+'</div><p>'+model.description+'</p><div class="dtf-local-ai-ratings"><span>Qualidade <i>'+localAiStars(model.id==='quality'?5:model.id==='balanced'?4:3)+'</i></span><span>Velocidade <i>'+localAiStars(model.id==='fast'?5:model.id==='balanced'?4:2)+'</i></span><span>Memória <i>'+localAiStars(model.id==='fast'?5:model.id==='balanced'?4:2)+'</i></span></div><small><b>Melhor para:</b> '+model.best+'<br>'+model.speed+'</small><div class="dtf-local-ai-download"><em data-ai-model-state>Disponível — ainda não baixada</em><button type="button" data-ai-install>Instalar</button></div><div class="dtf-local-ai-progress" data-ai-progress-wrap><i data-ai-progress></i></div><button type="button" class="dtf-local-ai-cancel" data-ai-cancel>Cancelar download</button>';list.appendChild(card);const radio=card.querySelector('input');radio.addEventListener('change',()=>selectLocalAiModelFromSettings(model.id));card.querySelector('[data-ai-install]').addEventListener('click',()=>installLocalAiModel(model.id));card.querySelector('[data-ai-cancel]').addEventListener('click',()=>cancelLocalAiModelInstall(model.id))});
 const close=()=>{localAiSettingsModal.classList.remove('show');if(ui.aiSettingsButton)ui.aiSettingsButton.setAttribute('aria-expanded','false')};box.querySelector('.dtf-local-ai-close').addEventListener('click',close);box.querySelector('[data-ai-settings-close]').addEventListener('click',close);localAiSettingsModal.addEventListener('pointerdown',event=>{if(event.target===localAiSettingsModal)close()});localAiSettingsModal.addEventListener('keydown',event=>{if(event.key==='Escape')close()});
}
function selectLocalAiModelFromSettings(id){if(!setLocalAiModel(id))return;renderLocalAiSettings();const model=selectedLocalAiModel();if(localAiSettingsStatus)localAiSettingsStatus.textContent='Modelo selecionado: '+model.name+'. Clique em Instalar se ainda não estiver disponível.';setStatus(model.name+' selecionado para uso local.')}
async function installLocalAiModel(id){
 if(!LOCAL_AI_MODELS[id]||localAiInstalled[id])return;
 if(localAiInstallJob){if(localAiSettingsStatus)localAiSettingsStatus.textContent='Aguarde o download atual terminar ou cancele-o.';return}
 const model=LOCAL_AI_MODELS[id],job={id,cancelled:false,progress:3,timer:null};localAiInstallJob=job;renderLocalAiSettings();
 job.timer=window.setInterval(()=>{if(job.cancelled)return;job.progress=Math.min(91,job.progress+Math.max(1,Math.round((91-job.progress)/12)));renderLocalAiSettings()},180);
 if(localAiSettingsStatus)localAiSettingsStatus.textContent='Baixando '+model.name+'…';setStatus('Baixando '+model.name+' para uso local…');
 try{await preloadBrowserBgModel(model);if(job.cancelled)return;localAiInstalled[id]=true;writeInstalledLocalAiModels();browserBgModelReady=true;browserBgModelReadyId=id;job.progress=100;if(localAiSettingsStatus)localAiSettingsStatus.textContent=model.name+' instalada e disponível localmente.';setStatus(model.name+' instalada para uso local.')}catch(error){if(!job.cancelled&&localAiSettingsStatus)localAiSettingsStatus.textContent='Não foi possível instalar '+model.name+': '+(error&&error.message?error.message:'erro desconhecido');if(!job.cancelled)setStatus('Falha ao instalar a IA local')}finally{if(job.timer)clearInterval(job.timer);if(localAiInstallJob===job)localAiInstallJob=null;renderLocalAiSettings()}
}
function cancelLocalAiModelInstall(id){if(!localAiInstallJob||localAiInstallJob.id!==id)return;localAiInstallJob.cancelled=true;browserBgModelRequest++;browserBgModelPromise=null;if(localAiSettingsStatus)localAiSettingsStatus.textContent='Download cancelado. Você pode instalar novamente quando quiser.';setStatus('Download da IA cancelado.');renderLocalAiSettings()}
function renderLocalAiSettings(){if(!localAiSettingsModal)return;const selected=selectedLocalAiModel();const autoToggle=localAiSettingsModal.querySelector('[data-ai-auto]');if(autoToggle)autoToggle.checked=localAiAuto;localAiSettingsModal.querySelectorAll('.dtf-local-ai-card').forEach(card=>{const id=card.dataset.modelId,model=LOCAL_AI_MODELS[id],radio=card.querySelector('input'),state=card.querySelector('[data-ai-model-state]'),install=card.querySelector('[data-ai-install]'),cancel=card.querySelector('[data-ai-cancel]'),bar=card.querySelector('[data-ai-progress]');radio.checked=!localAiAuto&&id===selected.id;radio.disabled=localAiAuto;card.classList.toggle('is-selected',!localAiAuto&&id===selected.id);const active=localAiInstallJob&&localAiInstallJob.id===id;const installed=!!localAiInstalled[id]||(browserBgModelReady&&browserBgModelReadyId===id);state.textContent=installed?'Disponível localmente — já instalada':active?'Baixando…':'Disponível — ainda não baixada';install.disabled=installed||!!localAiInstallJob;install.textContent=installed?'Instalada':'Instalar';cancel.hidden=!active;cancel.disabled=!active;if(bar){const percent=installed?100:active?(localAiInstallJob.progress||3):0;bar.style.width=percent+'%'}card.classList.toggle('is-installed',installed);card.classList.toggle('is-downloading',!!active)})}
function openLocalAiSettings(){ensureLocalAiSettingsModal();renderLocalAiSettings();localAiSettingsModal.classList.add('show');if(ui.aiSettingsButton)ui.aiSettingsButton.setAttribute('aria-expanded','true');if(localAiSettingsStatus)localAiSettingsStatus.textContent=localAiAuto?'Seleção automática ativada — a IA será escolhida para cada imagem.':'Modelo atual: '+selectedLocalAiModel().name+'.';localAiSettingsModal.querySelector('input:checked')?.focus()}
const commandButton=document.createElement('button');commandButton.type='button';commandButton.className='dtf-command-search dtf-tool';commandButton.title='Buscar comando (Ctrl+K)';commandButton.setAttribute('aria-label','Buscar comando (Ctrl+K)');commandButton.innerHTML='<span class="dtf-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.4 15.4L21 21"/></svg></span>';
const aiSettingsButton=document.createElement('button');aiSettingsButton.type='button';aiSettingsButton.id='dtfAiSettingsButton';aiSettingsButton.className='dtf-command-search dtf-tool';aiSettingsButton.title='Configurações de IA local';aiSettingsButton.setAttribute('aria-label','Configurações de IA local');aiSettingsButton.setAttribute('aria-haspopup','dialog');aiSettingsButton.setAttribute('aria-expanded','false');aiSettingsButton.innerHTML='<span class="dtf-icon" aria-hidden="true">⚙</span>';ui.aiSettingsButton=aiSettingsButton;aiSettingsButton.addEventListener('click',openLocalAiSettings);
const tabsBar=Q('.dtf-tabs');if(tabsBar){const helpTab=tabsBar.querySelector('.dtf-help-tab');if(helpTab){tabsBar.insertBefore(commandButton,helpTab);tabsBar.insertBefore(aiSettingsButton,helpTab)}else{tabsBar.appendChild(commandButton);tabsBar.appendChild(aiSettingsButton)}if(ui.fullscreenButton){ui.fullscreenButton.classList.add('dtf-top-fullscreen');if(helpTab)tabsBar.insertBefore(ui.fullscreenButton,helpTab);else tabsBar.appendChild(ui.fullscreenButton)}}QA('.dtf-tab').forEach(tab=>tab.addEventListener('click',()=>setTimeout(()=>{if(tab.dataset.tab!=='editar'&&ui.reset&&ui.reset.classList.contains('active'))deactivateAutoRemoveOption();else updateRemovalOptionControls()},0)));
function discoverCurrentToolCommands(){
 const seen=new Set(),items=[];
 QA('#dtfEditor button').forEach(button=>{
  if(!button||button===commandButton||button===aiSettingsButton||button.closest('.dtf-command-palette'))return;
  const label=(button.getAttribute('aria-label')||button.getAttribute('title')||button.textContent||'').replace(/\s+/g,' ').trim();
  if(!label||label.length<2||seen.has(button))return;
  seen.add(button);items.push({label,shortcut:'',target:button});
 });
 return items;
}
const commandEntries=()=>[
 {label:'Desfazer',shortcut:'Ctrl+Z',target:ui.undo},{label:'Refazer',shortcut:'Ctrl+Y',target:ui.redo},{label:'Configurações de IA local',shortcut:'',run:openLocalAiSettings},
 {label:'Selecionar todos',shortcut:'Ctrl+A',run:()=>{state.selectedIds=state.objects.filter(o=>o.visible).map(o=>o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;updateSelection();updateObjectUI();setStatus(state.selectedIds.length+' objetos selecionados')}},
 {label:'Copiar',shortcut:'Ctrl+C',run:copySelected},{label:'Colar',shortcut:'Ctrl+V',run:pasteClipboard},{label:'Duplicar',shortcut:'Ctrl+D',run:duplicateSelected},
 {label:'Excluir',shortcut:'Delete',target:ui.del},{label:'Agrupar',shortcut:'Ctrl+G',run:groupSelected},{label:'Desagrupar',shortcut:'Ctrl+U',run:ungroupSelected},
 {label:'Alinhar à esquerda',shortcut:'L',target:ui.alignLeft},{label:'Centralizar horizontalmente',shortcut:'C',target:ui.alignCenter},{label:'Alinhar à direita',shortcut:'R',target:ui.alignRight},
 {label:'Alinhar acima',shortcut:'U',target:ui.alignTop},{label:'Centralizar verticalmente',shortcut:'E',target:ui.alignMiddle},{label:'Alinhar abaixo',shortcut:'D',target:ui.alignBottom},
 {label:'Ajustar largura da página',shortcut:'',target:ui.zoomFit},{label:'Ajustar a imagem',shortcut:'',target:fitHeightButton},{label:'Modo tela cheia',shortcut:'',target:ui.fullscreenButton},{label:'Grade',shortcut:'',target:gridButton},{label:'Guias e margens',shortcut:'',target:guidesButton},
 {label:'Excluir todas as guias',shortcut:'',run:clearCustomGuides},
 {label:'Inserir quadrado/retângulo',shortcut:'',target:drawRectangleButton},{label:'Inserir texto',shortcut:'',target:drawTextButton},{label:'Cursor padrão',shortcut:'Esc',target:cursorSelectButton},{label:'Importar imagem',shortcut:'',target:ui.loadEdit||ui.load},{label:'Remover fundo',shortcut:'',target:ui.reset},{label:'Conta-gotas',shortcut:'',target:eyedropperButton},{label:'Substituir cor',shortcut:'',target:replaceColorButton},{label:'Nitidez',shortcut:'',target:sharpenButton},{label:'Lupa',shortcut:'',target:magnifierButton},{label:'Montagem inteligente com IA',shortcut:'',target:ui.magicFill},
 ...discoverCurrentToolCommands()
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
  if(e.key==='Escape'){
    const editableEsc=e.target&&(/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)||e.target.isContentEditable);
    if(commandPalette.classList.contains('show'))closeCommandPalette();
    if(typeof closeMainColorPalette==='function')closeMainColorPalette();
    const activeButtons=[
      ui.reset,ui.eraser,ui.restoreBrush,fillTool,clickRemove,areaRemoveButton,outerAreaRemoveButton,globalColorRemoveButton,colorAreaRemoveButton,
      typeof drawRectangleButton!=='undefined'?drawRectangleButton:null,
      typeof eyedropperButton!=='undefined'?eyedropperButton:null,
      typeof replaceColorButton!=='undefined'?replaceColorButton:null,
      typeof magnifierButton!=='undefined'?magnifierButton:null
    ].filter(Boolean);
    const hasActiveButton=activeButtons.some(button=>button.classList&&button.classList.contains('active'));
    const selectInactive=cursorSelectButton&&!cursorSelectButton.classList.contains('active');
    const normalToolActive=state.tool!=='select'||hasActiveButton||selectInactive;
    if(normalToolActive){
      e.preventDefault();
      resetToDefaultCursor(true);
      if(typeof hideMagnifier==='function')hideMagnifier();
      if(typeof magnifierActive!=='undefined')magnifierActive=false;
      if(typeof magnifierButton!=='undefined'&&magnifierButton){magnifierButton.classList.remove('active');magnifierButton.setAttribute('aria-pressed','false')}
      setCursorSelectButtonActive(true);
      setStatus('Ferramenta desativada — seta de seleção ativa');
      return;
    }
    if(!editableEsc&&selectInactive){e.preventDefault();setCursorSelectButtonActive(true);setStatus('Seta de seleção ativa');return}
  }
  const editable=e.target&&(/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)||e.target.isContentEditable);
  if(e.key==='Delete'&&state.selectedGuideId&&!editable){e.preventDefault();e.stopImmediatePropagation();removeCustomGuide(state.selectedGuideId);if(canvasMenu)canvasMenu.classList.remove('show','guide-context');if(guideDeleteMenu)guideDeleteMenu.style.display='none';return}
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
    if((key==='+'||key==='='||e.code==='NumpadAdd')&&(state.tool==='eraser'||state.tool==='restore'||state.tool==='colorAreaRemove')){e.preventDefault();setBrushSizeValue(state.brushSize+4,true);return;}
    if((key==='-'||key==='_'||e.code==='NumpadSubtract')&&(state.tool==='eraser'||state.tool==='restore'||state.tool==='colorAreaRemove')){e.preventDefault();setBrushSizeValue(state.brushSize-4,true);return;}
  }
  if(!e.ctrlKey&&!e.metaKey&&!e.altKey&&!editable){
    if(isScrollAdjustReservedKey(key))return;
    const alignShortcuts={l:ui.alignLeft,r:ui.alignRight,u:ui.alignTop,d:ui.alignBottom,c:ui.alignCenter,e:ui.alignMiddle};
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
 const d={...o,id:uid(),name:o.name+' cópia',x:clamp(place.x,0,Math.max(0,canvas.width-o.w)),y:clamp(place.y,0,Math.max(0,canvas.height-o.h)),canvas:canvasFromData(cloneCanvasData(o.canvas)),baseCanvas:canvasFromData(cloneCanvasData(o.baseCanvas)),restoreCanvas:o.restoreCanvas?canvasFromData(cloneCanvasData(o.restoreCanvas)):canvasFromData(cloneCanvasData(o.baseCanvas||o.canvas)),aiOriginalCanvas:o.aiOriginalCanvas?canvasFromData(cloneCanvasData(o.aiOriginalCanvas)):null};state.objects.push(d);normalizeObjectNames();selectObject(d);render();setStatus('Objeto duplicado à direita ou na próxima linha')
}
ui.duplicate.addEventListener('click',duplicateSelected);ui.del.addEventListener('click',()=>{const items=selectedObjects();if(!items.length)return;pushHistory('Antes de excluir');const ids=new Set(items.map(o=>o.id));state.objects=state.objects.filter(x=>!ids.has(x.id));state.selectedId=null;state.selectedIds=[];render();setStatus(items.length+' objeto'+(items.length===1?'':'s')+' excluído'+(items.length===1?'':'s'))});if(ui.center&&ui.center.addEventListener)ui.center.addEventListener('click',()=>{const o=selected();if(!o)return;pushHistory('Antes de centralizar');o.x=(canvas.width-o.w)/2;o.y=(canvas.height-o.h)/2;render();setStatus('Objeto centralizado')});
function withSelected(label,fn){const items=selectedObjects();if(!items.length){setStatus('Selecione um ou mais objetos');return}pushHistory(label);items.forEach(fn);render();setStatus(label)}
function withAlignmentReference(label,canvasFn,referenceFn){const items=selectedObjects();if(!items.length){setStatus('Selecione um ou mais objetos');return}const reference=items.length>1?(selected()&&items.includes(selected())?selected():items[items.length-1]):null;pushHistory(label);if(reference)items.filter(item=>item!==reference).forEach(item=>referenceFn(item,reference));else items.forEach(canvasFn);render();setStatus(reference?label+' em relação a '+reference.name:label)}
if(ui.alignLeft)ui.alignLeft.addEventListener('click',()=>withAlignmentReference('Alinhado à esquerda',o=>o.x=0,(o,r)=>o.x=r.x));
if(ui.alignCenter)ui.alignCenter.addEventListener('click',()=>withAlignmentReference('Alinhado ao centro',o=>o.x=(canvas.width-o.w)/2,(o,r)=>o.x=r.x+(r.w-o.w)/2));
if(ui.alignRight)ui.alignRight.addEventListener('click',()=>withAlignmentReference('Alinhado à direita',o=>o.x=canvas.width-o.w,(o,r)=>o.x=r.x+r.w-o.w));
if(ui.alignTop)ui.alignTop.addEventListener('click',()=>withAlignmentReference('Alinhado acima',o=>o.y=0,(o,r)=>o.y=r.y));
if(ui.alignMiddle)ui.alignMiddle.addEventListener('click',()=>withAlignmentReference('Alinhado ao centro vertical',o=>o.y=(canvas.height-o.h)/2,(o,r)=>o.y=r.y+(r.h-o.h)/2));
if(ui.alignBottom)ui.alignBottom.addEventListener('click',()=>withAlignmentReference('Alinhado abaixo',o=>o.y=canvas.height-o.h,(o,r)=>o.y=r.y+r.h-o.h));
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
function refreshDisplayModeMenu(){if(!ui.displayModeMenu||!ui.displayModeButton)return;const mode=DISPLAY_MODES[state.displayMode]?state.displayMode:'auto';ui.displayModeButton.title='Modo de exibição: '+DISPLAY_MODES[mode].label;ui.displayModeButton.setAttribute('aria-label','Modo de exibição: '+DISPLAY_MODES[mode].label);ui.displayModeMenu.querySelectorAll('[data-display-mode]').forEach(button=>{const active=button.dataset.displayMode===mode;button.setAttribute('aria-checked',String(active));button.classList.toggle('active',active)})}
function closeDisplayModeMenu(){if(!ui.displayModeMenu||!ui.displayModeButton)return;ui.displayModeMenu.hidden=true;ui.displayModeMenu.classList.remove('show');ui.displayModeButton.setAttribute('aria-expanded','false')}
function setDisplayMode(mode,notify=true){if(!DISPLAY_MODES[mode])mode='auto';state.displayMode=mode;clearAllDisplayPreviews();try{localStorage.setItem(DISPLAY_MODE_KEY,mode)}catch(_){ }refreshDisplayModeMenu();requestAnimationFrame(()=>render());if(notify)setStatus('Modo de exibição: '+DISPLAY_MODES[mode].label)}
function toggleDisplayModeMenu(){if(!ui.displayModeMenu||!ui.displayModeButton)return;const opening=ui.displayModeMenu.hidden;ui.displayModeMenu.hidden=!opening;ui.displayModeMenu.classList.toggle('show',opening);ui.displayModeButton.setAttribute('aria-expanded',String(opening));if(opening){const current=ui.displayModeMenu.querySelector('[aria-checked="true"]');if(current)current.focus()}}
if(ui.displayModeButton){ui.displayModeButton.addEventListener('click',toggleDisplayModeMenu);ui.displayModeMenu?.querySelectorAll('[data-display-mode]').forEach(button=>button.addEventListener('click',()=>{setDisplayMode(button.dataset.displayMode);closeDisplayModeMenu()}));document.addEventListener('pointerdown',event=>{if(!ui.displayModeMenu||ui.displayModeMenu.hidden)return;if(event.target===ui.displayModeButton||ui.displayModeButton.contains(event.target)||ui.displayModeMenu.contains(event.target))return;closeDisplayModeMenu()},{capture:true});refreshDisplayModeMenu()}
function syncFullscreenButton(){const active=document.fullscreenElement===editorRoot||editorRoot.classList.contains('dtf-fullscreen-fallback');if(ui.fullscreenButton){ui.fullscreenButton.classList.toggle('active',active);ui.fullscreenButton.setAttribute('aria-pressed',String(active));ui.fullscreenButton.title=active?'Sair da tela cheia':'Modo tela cheia';ui.fullscreenButton.setAttribute('aria-label',active?'Sair da tela cheia':'Modo tela cheia')}}
async function toggleEditorFullscreen(){try{if(document.fullscreenElement===editorRoot){await document.exitFullscreen();return}if(document.fullscreenElement){await document.exitFullscreen()}if(editorRoot.requestFullscreen){await editorRoot.requestFullscreen()}else{editorRoot.classList.toggle('dtf-fullscreen-fallback');syncFullscreenButton();setStatus(editorRoot.classList.contains('dtf-fullscreen-fallback')?'Modo tela cheia ativo':'Modo tela cheia encerrado')}}catch(error){editorRoot.classList.toggle('dtf-fullscreen-fallback');syncFullscreenButton();setStatus(editorRoot.classList.contains('dtf-fullscreen-fallback')?'Modo tela cheia ativo':'Modo tela cheia encerrado')}}
if(ui.fullscreenButton)ui.fullscreenButton.addEventListener('click',toggleEditorFullscreen);document.addEventListener('fullscreenchange',()=>{syncFullscreenButton();if(document.fullscreenElement===editorRoot)setStatus('Modo tela cheia ativo');else if(!editorRoot.classList.contains('dtf-fullscreen-fallback'))setStatus('Modo tela cheia encerrado')});syncFullscreenButton();
ui.zoomOut.addEventListener('click',()=>setZoom(state.zoom-.1));ui.zoomIn.addEventListener('click',()=>setZoom(state.zoom+.1));ui.zoom100.addEventListener('click',()=>fitZoom(true));ui.zoomFit.addEventListener('click',()=>fitWidthZoom(true));if(ui.zoomFitHeight)ui.zoomFitHeight.addEventListener('click',()=>fitHeightZoom(true));
viewport.addEventListener('wheel',e=>{if(!e.ctrlKey&&!e.metaKey){if((scrollAdjustKeys.t||scrollAdjustKeys.p)&&ui.tol&&editorRoot.classList.contains('dtf-show-tolerance')){e.preventDefault();changeRangeByScroll(ui.tol,e.deltaY,1);showToolHint('Tolerância '+ui.tol.value);return}if(scrollAdjustKeys.b&&ui.clickTol&&editorRoot.classList.contains('dtf-show-border')){e.preventDefault();changeRangeByScroll(ui.clickTol,e.deltaY,1);showToolHint('Borda '+ui.clickTol.value);return}if(scrollAdjustKeys.m&&(state.tool==='eraser'||state.tool==='restore'||state.tool==='colorAreaRemove'||state.tool==='areaRemove'||state.tool==='globalColorRemove'||editorRoot.classList.contains('dtf-show-measure')||editorRoot.classList.contains('dtf-show-color-area-brush'))){e.preventDefault();setBrushSizeValue(state.brushSize+(e.deltaY<0?4:-4),true);markRemovalOptionsChanged('Medida '+state.brushSize);return}}if(e.ctrlKey||e.metaKey){e.preventDefault();const r=canvas.getBoundingClientRect(),old=state.zoom,next=e.deltaY>0?minimumZoom():clamp(old+.1,minimumZoom(),4);if(e.deltaY>0){state.zoom=next;state.panX=0;state.panY=0}else{const localX=(e.clientX-r.left)/old,localY=(e.clientY-r.top)/old;state.zoom=next;state.panX+=e.clientX-(r.left+localX*next);state.panY+=e.clientY-(r.top+localY*next)}applyZoom();setStatus('Zoom '+Math.round(next*100)+'%')}},{passive:false});

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
function workspaceExportCanvas(fillJpeg=false,width=canvas.width,height=canvas.height,enhance=false){const c=document.createElement('canvas');c.width=Math.max(1,Math.round(width));c.height=Math.max(1,Math.round(height));const cctx=c.getContext('2d',{alpha:!fillJpeg});cctx.imageSmoothingEnabled=true;cctx.imageSmoothingQuality='high';if(fillJpeg){cctx.fillStyle=state.bgColor;cctx.fillRect(0,0,c.width,c.height)}else if(!state.transparent){cctx.fillStyle=state.bgColor;cctx.fillRect(0,0,c.width,c.height)}const sx=c.width/canvas.width,sy=c.height/canvas.height;for(const o of state.objects){if(!o.visible)continue;drawObject(o,cctx,sx,sy,enhance)}return c}
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
function pdfObjectCanvas(o){const points=typeof shapeTransformedCornerPoints==='function'?shapeTransformedCornerPoints(o):[{x:o.x,y:o.y},{x:o.x+o.w,y:o.y},{x:o.x+o.w,y:o.y+o.h},{x:o.x,y:o.y+o.h}],minX=Math.floor(Math.min(...points.map(point=>point.x))),minY=Math.floor(Math.min(...points.map(point=>point.y))),maxX=Math.ceil(Math.max(...points.map(point=>point.x))),maxY=Math.ceil(Math.max(...points.map(point=>point.y))),width=Math.max(1,maxX-minX),height=Math.max(1,maxY-minY),out=document.createElement('canvas');out.width=width;out.height=height;const outCtx=out.getContext('2d',{alpha:true}),renderObject={...o,x:o.x-minX,y:o.y-minY,w:o.w,h:o.h};drawObject(renderObject,outCtx,1,1,false);return {canvas:out,x:minX,y:minY,w:width,h:height}}
function pdfPageBackground(doc,mmW,mmH){if(state.transparent)return;const color=rgbParts(state.bgColor||'#ffffff');doc.setFillColor(color.r,color.g,color.b);doc.rect(0,0,mmW,mmH,'F')}
async function exportPdf(){try{const separate=pdfExportMode==='objects';progress(8,'Carregando exportador PDF...');await ensurePdfExport();const size=exportPixelSize(exportScaleForFormat('pdf')),mmW=Number(state.workWmm),mmH=Number(state.workHmm),{jsPDF}=window.jspdf,doc=new jsPDF({orientation:mmW>=mmH?'landscape':'portrait',unit:'mm',format:[mmW,mmH],compress:false,precision:16,putOnlyUsedFonts:true});if(separate){const objects=state.objects.filter(object=>object.visible&&object.canvas&&object.canvas.width&&object.canvas.height);pdfPageBackground(doc,mmW,mmH);if(!objects.length){progress(72,'Criando PDF sem objetos visíveis...')}for(let index=0;index<objects.length;index++){const object=objects[index],rendered=pdfObjectCanvas(object);progress(22+index/Math.max(1,objects.length)*58,'Preparando objeto '+(index+1)+' de '+objects.length+'...');await nextPaint();const png=await canvasToBlob(rendered.canvas,'image/png',1),bytes=new Uint8Array(await png.arrayBuffer()),xMm=rendered.x/state.dpi*25.4,yMm=mmH-(rendered.y+rendered.h)/state.dpi*25.4,wMm=rendered.w/state.dpi*25.4,hMm=rendered.h/state.dpi*25.4;doc.addImage(bytes,'PNG',xMm,yMm,wMm,hMm,undefined,'NONE')}saveBlob(doc.output('blob'),'dtf_uv_objetos_separados.pdf');finish('PDF com objetos separados exportado');setStatus('PDF com '+objects.length+' objetos separados — posições e transparência preservadas • '+mmW+' × '+mmH+' mm')}else{progress(30,'Renderizando a folha sem reduzir os pixels originais...');const c=workspaceExportCanvas(false,size.width,size.height),png=await canvasToBlob(c,'image/png',1),bytes=new Uint8Array(await png.arrayBuffer());progress(82,'Montando PDF de imagem única...');doc.addImage(bytes,'PNG',0,0,mmW,mmH,undefined,'NONE');saveBlob(doc.output('blob'),'dtf_uv_alta_qualidade.pdf');finish('PDF de imagem exportado');setStatus('PDF salvo como imagem única, sem fontes — '+mmW+' × '+mmH+' mm • '+c.width+' × '+c.height+' px • '+Math.round(size.effectiveDpi)+' DPI')}}catch(e){finish('Erro');showError('Falha ao gerar PDF em alta qualidade',e)}}

const LAST_IMPORTED_FORMAT_KEY='printway_dtf_last_import_format';
function importedFormatFor(file){
 const name=String(file&&file.name||'').toLowerCase(),type=String(file&&file.type||'').toLowerCase();
 if(/\.pdf$/.test(name)||type==='application/pdf')return 'pdf';
 if(isTiffFile(file))return 'tiff';
 if(/\.(jpe?g)$/.test(name)||type==='image/jpeg')return 'jpeg';
 if(/\.webp$/.test(name)||type==='image/webp')return 'webp';
 return 'png';
}
let lastImportedFormat='png';
try{const saved=localStorage.getItem(LAST_IMPORTED_FORMAT_KEY);if(['png','tiff','webp','jpeg','pdf'].includes(saved))lastImportedFormat=saved}catch(_){ }
let exportFormat=lastImportedFormat;
const PDF_EXPORT_MODE_KEY='printway_dtf_pdf_export_mode';
let pdfExportMode='single';
try{const savedPdfMode=localStorage.getItem(PDF_EXPORT_MODE_KEY);if(savedPdfMode==='objects'||savedPdfMode==='single')pdfExportMode=savedPdfMode}catch(_){ }
function rememberImportedFormat(file){const format=importedFormatFor(file);lastImportedFormat=format;exportFormat=format;try{localStorage.setItem(LAST_IMPORTED_FORMAT_KEY,format)}catch(_){ }}
const exportChoices=QA('.dtf-export-choice');
const pdfModeChoices=QA('.dtf-export-pdf-mode');
const exportHints={png:'PNG sem perda · recorta automaticamente as áreas transparentes',tiff:'TIFF RGBA sem perda · resolução original automática',webp:'WebP em qualidade máxima · folha completa',jpeg:'JPEG em qualidade máxima · medida física gravada',pdf:'PDF fiel em alta resolução · escolha entre imagem única ou objetos independentes'};
let exportPreviewTimer=0,exportPreviewToken=0,exportPreviewZoom=100,exportPreviewView='fit',exportPreviewPanX=0,exportPreviewPanY=0,exportPreviewRendered=null;
function setExportPreviewProgress(value,text){if(!ui.exportPreviewProgress)return;const amount=clamp(Number(value)||0,0,100);ui.exportPreviewProgress.hidden=false;if(ui.exportPreviewEmpty)ui.exportPreviewEmpty.hidden=true;if(ui.exportPreviewProgressBar)ui.exportPreviewProgressBar.style.width=amount+'%';if(ui.exportPreviewProgressText)ui.exportPreviewProgressText.textContent=text||'Criando prévia…'}
function hideExportPreviewProgress(){if(ui.exportPreviewProgress)ui.exportPreviewProgress.hidden=true}
function exportPreviewSource(){
 const solid=exportFormat==='jpeg';
 const enhance=exportEnhancementEnabled();
 const output=exportPixelSize(exportScaleForFormat()),lossy=exportFormat==='jpeg'||exportFormat==='webp',maxSide=lossy?2400:4096,maxPixels=lossy?6000000:12000000,scale=Math.min(1,maxSide/output.width,maxSide/output.height,Math.sqrt(maxPixels/(output.width*output.height))),width=Math.max(1,Math.round(output.width*scale)),height=Math.max(1,Math.round(output.height*scale));
 const source=workspaceExportCanvas(solid,width,height,enhance);
 const pdfLabel=exportFormat==='pdf'?(pdfExportMode==='objects'?' · PDF com objetos separados':' · PDF como imagem única'):'';
 return {canvas:source,label:(state.objects.some(o=>o.visible)?'Área de trabalho completa':'Área de trabalho vazia')+pdfLabel+(enhance?' · melhoria de IA':'')+(scale<.999?' · prévia HD':'')};
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
function syncExportDialog(){const quality=exportFormat==='webp'||exportFormat==='jpeg',isPdf=exportFormat==='pdf';exportChoices.forEach(button=>{const active=button.dataset.exportFormat===exportFormat;button.classList.toggle('active',active);button.setAttribute('aria-checked',String(active))});pdfModeChoices.forEach(button=>{const active=button.dataset.pdfMode===pdfExportMode;button.classList.toggle('active',active);button.setAttribute('aria-checked',String(active))});if(ui.exportImageOptions)ui.exportImageOptions.hidden=!quality;if(ui.exportPdfOptions)ui.exportPdfOptions.hidden=!isPdf;if(ui.exportFormatHint)ui.exportFormatHint.textContent=exportHints[exportFormat]||'';updateOutputInfo();scheduleExportPreview()}
function closeExportDialog(){if(!ui.exportModal)return;ui.exportModal.hidden=true;if(ui.exportOpen)ui.exportOpen.focus();else Q('[data-tab="texto"]')?.focus()}
function openExportDialog(){if(!ui.exportModal)return;exportFormat=lastImportedFormat;const box=ui.exportModal.querySelector('.dtf-export-box');if(box){box.style.left='';box.style.top='';box.style.transform=''}syncExportDialog();ui.exportModal.hidden=false;const active=exportChoices.find(button=>button.dataset.exportFormat===exportFormat);if(active)active.focus()}
async function confirmExport(){const actions={png:()=>exportRaster('image/png','png',1),tiff:exportTiff,webp:()=>exportRaster('image/webp','webp',(parseInt(ui.quality.value,10)||100)/100),jpeg:()=>exportRaster('image/jpeg','jpg',(parseInt(ui.quality.value,10)||100)/100),pdf:exportPdf},action=actions[exportFormat];closeExportDialog();if(action)await action()}
exportChoices.forEach(button=>button.addEventListener('click',()=>{exportFormat=button.dataset.exportFormat||'png';syncExportDialog()}));
pdfModeChoices.forEach(button=>button.addEventListener('click',()=>{const mode=button.dataset.pdfMode==='objects'?'objects':'single';pdfExportMode=mode;try{localStorage.setItem(PDF_EXPORT_MODE_KEY,mode)}catch(_){ }syncExportDialog()}));
if(ui.exportOpen)ui.exportOpen.addEventListener('click',openExportDialog);
if(ui.exportCancel)ui.exportCancel.addEventListener('click',closeExportDialog);
if(ui.exportConfirm)ui.exportConfirm.addEventListener('click',confirmExport);
if(ui.exportModal){ui.exportModal.addEventListener('pointerdown',event=>{if(event.target===ui.exportModal)closeExportDialog()});ui.exportModal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeExportDialog()}})}
if(ui.exportPreviewCanvas){const frame=ui.exportPreviewCanvas.parentElement;frame.addEventListener('wheel',event=>{if(!exportPreviewRendered)return;event.preventDefault();const fitPercent=Math.max(.1,exportPreviewRendered.fitPercent||100),oldPercent=exportPreviewView==='fit'?fitPercent:exportPreviewZoom,newPercent=clamp(oldPercent*(event.deltaY<0?1.18:.85),Math.min(10,fitPercent),1000);if(event.deltaY>0&&newPercent<=fitPercent*1.01){exportPreviewView='fit';exportPreviewPanX=0;exportPreviewPanY=0}else{exportPreviewView='manual';exportPreviewZoom=newPercent;const rect=frame.getBoundingClientRect(),dx=event.clientX-(rect.left+rect.width/2),dy=event.clientY-(rect.top+rect.height/2),factor=newPercent/oldPercent;exportPreviewPanX=(exportPreviewPanX-dx)*factor+dx;exportPreviewPanY=(exportPreviewPanY-dy)*factor+dy}drawExportPreview(exportPreviewRendered.source,exportPreviewRendered.label,exportPreviewToken,false)},{passive:false});frame.addEventListener('dblclick',event=>{if(!exportPreviewRendered)return;event.preventDefault();event.stopPropagation();exportPreviewView=exportPreviewView==='fit'?'manual':'fit';exportPreviewZoom=100;exportPreviewPanX=0;exportPreviewPanY=0;drawExportPreview(exportPreviewRendered.source,exportPreviewRendered.label,exportPreviewToken,false)});let drag=null;frame.addEventListener('pointerdown',event=>{if(exportPreviewView==='fit')return;drag={x:event.clientX,y:event.clientY,px:exportPreviewPanX,py:exportPreviewPanY,id:event.pointerId};frame.setPointerCapture(event.pointerId)});frame.addEventListener('pointermove',event=>{if(!drag||drag.id!==event.pointerId)return;exportPreviewPanX=drag.px+event.clientX-drag.x;exportPreviewPanY=drag.py+event.clientY-drag.y;drawExportPreview(exportPreviewRendered.source,exportPreviewRendered.label,exportPreviewToken,false)});frame.addEventListener('pointerup',()=>drag=null);frame.addEventListener('pointercancel',()=>drag=null)}
if(ui.quality)ui.quality.addEventListener('input',()=>{if(ui.qualityValue)ui.qualityValue.textContent=ui.quality.value;scheduleExportPreview()});if(ui.exportAiEnhance)ui.exportAiEnhance.addEventListener('change',()=>{updateOutputInfo();scheduleExportPreview()});syncExportDialog();

// PDF page is controlled by the selected PDF object: double-click PDF object while selected to move page? For a simple UI, a PDF object keeps page 1. Re-convert is exposed through selecting PDF and pressing original reset if needed.

// Keyboard delete and arrow movement.
document.addEventListener('keydown',e=>{if(activeEditorRoot!==editorRoot)return;const o=selected();if(!o)return;if(e.key==='Delete'&&document.activeElement.tagName!=='INPUT'&&document.activeElement.tagName!=='SELECT'){e.preventDefault();ui.del.click()}if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)&&!e.ctrlKey&&!e.metaKey&&document.activeElement.tagName!=='INPUT'){e.preventDefault();pushHistory('Antes de mover por teclado');const d=(gridEnabled&&gridStepEnabled)?gridStep():(e.shiftKey?10:1);if(e.key==='ArrowUp')o.y=clamp(o.y-d,0,canvas.height-o.h);if(e.key==='ArrowDown')o.y=clamp(o.y+d,0,canvas.height-o.h);if(e.key==='ArrowLeft')o.x=clamp(o.x-d,0,canvas.width-o.w);if(e.key==='ArrowRight')o.x=clamp(o.x+d,0,canvas.width-o.w);render()}});

// Keep legacy exposed API for compatibility/debugging.
const editorApi={state,render,selectObject,addObject,fitZoom};
function activateEditor(){activeEditorRoot=editorRoot;window._dtfEditor=editorApi;}
if(!activeEditorRoot)activateEditor();
editorRoot.addEventListener('pointerdown',activateEditor);
editorRoot.addEventListener('focusin',activateEditor);

// Global error logging.
window.addEventListener('error',e=>log('Global JS error',e.error?e.error.stack:e.message));window.addEventListener('unhandledrejection',e=>log('Unhandled promise rejection',e.reason));


// A tolerância é apenas configurada na barra. A análise pesada só acontece ao clicar em Remover fundo.
ui.tol.addEventListener('input',()=>{if(ui.tolValue)ui.tolValue.textContent=ui.tol.value;markRemovalOptionsChanged('Tolerância '+ui.tol.value);});
let propertyClipboard=null;function openPropertiesModal(o){const oldW=o.w,oldH=o.h,m=document.createElement('div');m.className='dtf-properties-modal';m.innerHTML='<div class="dtf-properties-box"><h3>Propriedades do objeto</h3><label>Largura (mm)<input id="propW" type="number" min="0.1" step="0.1"></label><label>Altura (mm)<input id="propH" type="number" min="0.1" step="0.1"></label><div class="dtf-properties-actions"><button type="button" data-cancel>Cancelar</button><button type="button" data-preview>Prévia</button><button type="button" data-confirm>Confirmar</button></div></div>';document.body.appendChild(m);const w=m.querySelector('#propW'),h=m.querySelector('#propH');w.value=pxToMm(o.w).toFixed(1);h.value=pxToMm(o.h).toFixed(1);const apply=()=>{o.w=Math.min(canvas.width,physicalMmToPx(parseFloat(w.value)||pxToMm(oldW)));o.h=Math.min(canvas.height,physicalMmToPx(parseFloat(h.value)||pxToMm(oldH)));o.x=clamp(o.x,0,canvas.width-o.w);o.y=clamp(o.y,0,canvas.height-o.h);render()};m.querySelector('[data-preview]').onclick=apply;m.querySelector('[data-confirm]').onclick=()=>{pushHistory('Alterar propriedades');apply();m.remove()};m.querySelector('[data-cancel]').onclick=()=>{o.w=oldW;o.h=oldH;render();m.remove()}}
// As propriedades são abertas exclusivamente pelo menu de contexto.
if(copyPropMenu&&pastePropMenu&&canvasMenu){copyPropMenu.style.display='none';pastePropMenu.style.display='none';const pm=document.createElement('div');pm.className='dtf-properties-menu';const pb=document.createElement('button');pb.type='button';pb.textContent='Propriedades ▸';const ps=document.createElement('div');ps.className='dtf-properties-submenu';const edit=document.createElement('button');edit.type='button';edit.textContent='Editar';const cp=copyPropMenu.cloneNode(true),pp=pastePropMenu.cloneNode(true);cp.textContent='Copiar';pp.textContent='Colar';ps.append(edit,cp,pp);pm.append(pb,ps);canvasMenu.appendChild(pm);edit.onclick=()=>{const o=selected();if(o)openPropertiesModal(o);canvasMenu.classList.remove('show')};cp.onclick=()=>copyPropMenu.click();pp.onclick=()=>pastePropMenu.click()}
function openPropertiesModal(o){const old={x:o.x,y:o.y,w:o.w,h:o.h},ratio=o.w/o.h,m=document.createElement('div');m.className='dtf-properties-modal';m.innerHTML='<div class="dtf-properties-box"><h3>Propriedades do objeto</h3><label>Largura (mm)<input id="propW" type="number" min="0.1" step="0.1"></label><label>Altura (mm)<input id="propH" type="number" min="0.1" step="0.1"></label><label><input id="propLock" type="checkbox" checked> Manter proporção</label><hr><small id="propOriginal"></small><label>Posição X (mm)<input id="propX" type="number" step="0.1"></label><label>Posição Y (mm)<input id="propY" type="number" step="0.1"></label><div class="dtf-properties-actions"><button type="button" data-center-h>Centralizar H</button><button type="button" data-center-v>Centralizar V</button><button type="button" data-cancel>Cancelar</button><button type="button" data-confirm>Confirmar</button></div></div>';document.body.appendChild(m);const q=s=>m.querySelector(s),w=q('#propW'),h=q('#propH'),x=q('#propX'),y=q('#propY'),lock=q('#propLock');const set=()=>{w.value=pxToMm(o.w).toFixed(1);h.value=pxToMm(o.h).toFixed(1);x.value=pxToMm(o.x).toFixed(1);y.value=pxToMm(o.y).toFixed(1);q('#propOriginal').textContent='Original: '+pxToMm(old.w).toFixed(1)+' × '+pxToMm(old.h).toFixed(1)+' mm'};set();let applying=false;const live=()=>{if(applying)return;let nw=Math.max(1,physicalMmToPx(parseFloat(w.value)||1)),nh=Math.max(1,physicalMmToPx(parseFloat(h.value)||1));if(lock.checked){if(document.activeElement===w)nh=nw/ratio;else nw=nh*ratio;w.value=pxToMm(nw).toFixed(1);h.value=pxToMm(nh).toFixed(1)}o.w=Math.min(canvas.width,nw);o.h=Math.min(canvas.height,nh);o.x=clamp(physicalMmToPx(parseFloat(x.value)||0),0,canvas.width-o.w);o.y=clamp(physicalMmToPx(parseFloat(y.value)||0),0,canvas.height-o.h);render()};[w,h,x,y].forEach(el=>el.addEventListener('input',live));q('[data-center-h]').onclick=()=>{o.x=(canvas.width-o.w)/2;x.value=pxToMm(o.x).toFixed(1);render()};q('[data-center-v]').onclick=()=>{o.y=(canvas.height-o.h)/2;y.value=pxToMm(o.y).toFixed(1);render()};q('[data-confirm]').onclick=()=>{applying=true;pushHistory('Alterar propriedades');applying=false;m.remove()};q('[data-cancel]').onclick=()=>{o.x=old.x;o.y=old.y;o.w=old.w;o.h=old.h;render();m.remove()}}
function openGuideMarginsModal(focusEdge='top'){
 const original={...guideMargins},m=document.createElement('div');m.className='dtf-properties-modal dtf-guide-margins-modal';m.setAttribute('role','dialog');m.setAttribute('aria-modal','true');m.setAttribute('aria-labelledby','dtfGuideMarginsTitle');
 m.innerHTML='<div class="dtf-properties-box dtf-guide-margins-box"><h3 id="dtfGuideMarginsTitle">Margens de segurança e linhas de corte</h3><p class="dtf-guide-margins-intro">Defina a distância de cada linha até a borda da página.</p><div class="dtf-guide-margins-grid"><label>Superior (mm)<input data-edge="top" type="text" inputmode="decimal"></label><label>Inferior (mm)<input data-edge="bottom" type="text" inputmode="decimal"></label><label>Esquerda (mm)<input data-edge="left" type="text" inputmode="decimal"></label><label>Direita (mm)<input data-edge="right" type="text" inputmode="decimal"></label></div><label class="dtf-guide-margins-lock"><input data-lock type="checkbox"> Travar margens (alterar uma aplica às quatro)</label><div class="dtf-properties-actions"><button type="button" data-cancel>Cancelar</button><button type="button" data-confirm>Aplicar</button></div></div>';
 document.body.appendChild(m);const box=m.querySelector('.dtf-guide-margins-box'),inputs=Object.fromEntries(GUIDE_MARGIN_EDGES.map(edge=>[edge,m.querySelector('input[data-edge="'+edge+'"]')])),lock=m.querySelector('[data-lock]');const wasDirty=projectDirty;let historySaved=false;
 const setFields=()=>GUIDE_MARGIN_EDGES.forEach(edge=>{inputs[edge].value=String(Number(guideMargins[edge].toFixed(2))).replace('.',',')});setFields();
 const numberValue=input=>Number(String(input&&input.value||'').trim().replace(',','.'));
 const updateLive=source=>{const next={...guideMargins};if(lock.checked&&source){const n=numberValue(inputs[source]);if(Number.isFinite(n))GUIDE_MARGIN_EDGES.forEach(edge=>{next[edge]=n;inputs[edge].value=String(n).replace('.',',')})}else GUIDE_MARGIN_EDGES.forEach(edge=>{const n=numberValue(inputs[edge]);if(Number.isFinite(n))next[edge]=n});const sanitized=sanitizeGuideMargins(next),changed=sanitized&&GUIDE_MARGIN_EDGES.some(edge=>Math.abs(sanitized[edge]-original[edge])>.0005);if(changed&&!historySaved){pushHistory('Ajustar margens de segurança');historySaved=true}guideMargins=sanitized;updateGuidesOverlay()};
 GUIDE_MARGIN_EDGES.forEach(edge=>{inputs[edge].addEventListener('input',()=>updateLive(edge));inputs[edge].addEventListener('focus',()=>inputs[edge].select())});
 let closed=false;const cancel=()=>{guideMargins=original;if(historySaved){state.undo.pop();state.redo=[];updateHistoryUI();setProjectDirty(wasDirty)}updateGuidesOverlay();close()};const close=()=>{if(closed)return;closed=true;m.remove()};m.querySelector('[data-cancel]').addEventListener('click',cancel);m.querySelector('[data-confirm]').addEventListener('click',()=>{const changed=GUIDE_MARGIN_EDGES.some(edge=>Math.abs(guideMargins[edge]-original[edge])>.0005);if(changed&&!historySaved){pushHistory('Ajustar margens de segurança');historySaved=true}setStatus(changed?'Margens de segurança atualizadas':'Margens mantidas');close()});m.addEventListener('pointerdown',event=>{if(event.target===m)cancel()});m.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();cancel()}if(event.key==='Enter'&&event.target.tagName==='INPUT'){event.preventDefault();m.querySelector('[data-confirm]').click()}});(inputs[focusEdge]||inputs.top).focus();
}
const root=editorRoot;
// Todas as janelas podem ser reposicionadas pela respectiva barra de título.
let modalWindowDrag=null;
function modalDragHandle(target){return target.closest('.dtf-drawing-library-header,.dtf-properties-box>h3,.dtf-export-box>h3,.dtf-magic-box>h3,.dtf-corner-title-row h3')}
function modalWindowFromHandle(handle){return handle&&handle.closest('.dtf-drawing-library-box,.dtf-properties-box,.dtf-export-box,.dtf-magic-box')}
document.addEventListener('pointerdown',event=>{if(event.button!==0||event.target.closest('button,input,select,textarea,a,label'))return;const handle=modalDragHandle(event.target),box=modalWindowFromHandle(handle);if(!box)return;const rect=box.getBoundingClientRect();modalWindowDrag={box,id:event.pointerId,sx:event.clientX,sy:event.clientY,x:rect.left,y:rect.top,w:rect.width,h:rect.height};box.classList.add('dtf-window-dragging');box.style.position='fixed';box.style.left=rect.left+'px';box.style.top=rect.top+'px';box.style.transform='none';event.preventDefault()});
document.addEventListener('pointermove',event=>{const drag=modalWindowDrag;if(!drag||event.pointerId!==drag.id)return;const maxX=Math.max(0,window.innerWidth-drag.w),maxY=Math.max(0,window.innerHeight-drag.h),x=clamp(drag.x+event.clientX-drag.sx,0,maxX),y=clamp(drag.y+event.clientY-drag.sy,0,maxY);drag.box.style.left=x+'px';drag.box.style.top=y+'px'});
document.addEventListener('pointerup',event=>{if(!modalWindowDrag||event.pointerId!==modalWindowDrag.id)return;modalWindowDrag.box.classList.remove('dtf-window-dragging');modalWindowDrag=null});document.addEventListener('pointercancel',()=>{if(modalWindowDrag)modalWindowDrag.box.classList.remove('dtf-window-dragging');modalWindowDrag=null});

const drawRectangleButton=$id('dtfDrawRectangle');
const drawCircleButton=$id('dtfDrawCircle');
const drawTriangleButton=$id('dtfDrawTriangle');
const drawLineButton=$id('dtfDrawLine');
const drawMoreShapesButton=$id('dtfDrawMoreShapes');
const drawTextButton=$id('dtfDrawText');
const cornerRadiusButton=$id('dtfCornerRadius');
const drawShapeButtons={rectangle:drawRectangleButton,circle:drawCircleButton,triangle:drawTriangleButton};
let activeDrawShape='rectangle';
const moreDrawShapes=['pentagon','star','diamond','heart','arrow','speech'];
const drawShapeNames={rectangle:'Quadrado/Retângulo',circle:'Círculo/Elipse',triangle:'Triângulo',pentagon:'Pentágono',star:'Estrela',diamond:'Losango',heart:'Coração',arrow:'Seta',speech:'Balão de fala'};
const drawShapeIcons={pentagon:'<svg viewBox="0 0 24 24"><path d="M12 3.8 20.2 9.8l-3.1 9.7H6.9L3.8 9.8z"/></svg>',star:'<svg viewBox="0 0 24 24"><path d="m12 3.8 2.55 5.17 5.7.83-4.12 4.02.97 5.68L12 16.8l-5.1 2.68.97-5.68L3.75 9.8l5.7-.83z"/></svg>',diamond:'<svg viewBox="0 0 24 24"><path d="m12 3.5 8.5 8.5-8.5 8.5L3.5 12z"/></svg>',heart:'<svg viewBox="0 0 24 24"><path d="M12 20.2 4.6 13a5.1 5.1 0 0 1 7.2-7.2L12 6.1l.2-.3a5.1 5.1 0 0 1 7.2 7.2z"/></svg>',arrow:'<svg viewBox="0 0 24 24"><path d="M3.5 9h9V5l8 7-8 7v-4h-9z"/></svg>',speech:'<svg viewBox="0 0 24 24"><path d="M4.5 5.5h15v10h-8l-4 3v-3h-3z"/></svg>'};
const drawShapePicker=document.createElement('div');drawShapePicker.className='dtf-shape-picker';drawShapePicker.setAttribute('role','dialog');drawShapePicker.setAttribute('aria-label','Escolher outra forma');
moreDrawShapes.forEach(type=>{const button=document.createElement('button');button.type='button';button.dataset.shape=type;button.title='Inserir '+drawShapeNames[type];button.setAttribute('aria-label','Inserir '+drawShapeNames[type]);button.innerHTML='<span class="dtf-icon" aria-hidden="true">'+drawShapeIcons[type]+'</span><span>'+drawShapeNames[type]+'</span>';button.addEventListener('click',()=>{closeDrawShapePicker();activateDrawRectTool(type)});drawShapePicker.appendChild(button)});
document.body.appendChild(drawShapePicker);
function closeDrawShapePicker(){drawShapePicker.classList.remove('show');if(drawMoreShapesButton)drawMoreShapesButton.setAttribute('aria-expanded','false')}
function openDrawShapePicker(){if(!drawMoreShapesButton)return;drawShapePicker.classList.add('show');drawMoreShapesButton.setAttribute('aria-expanded','true');const rect=drawMoreShapesButton.getBoundingClientRect(),width=drawShapePicker.offsetWidth||294,height=drawShapePicker.offsetHeight||172;drawShapePicker.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-width-8))+'px';drawShapePicker.style.top=Math.max(8,Math.min(rect.bottom+6,window.innerHeight-height-8))+'px'}
document.addEventListener('pointerdown',event=>{if(!drawShapePicker.classList.contains('show'))return;if(drawShapePicker.contains(event.target)||event.target===drawMoreShapesButton||drawMoreShapesButton&&drawMoreShapesButton.contains(event.target))return;closeDrawShapePicker()},{capture:true});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeDrawShapePicker()});
let cornerRadiusModal=null,cornerRadiusSession=null;
const cornerKeys=['tl','tr','br','bl'];
function syncCornerRadiusButton(){
 const button=$id('dtfCornerRadius'),items=selectedObjects(),enabled=!!(button&&items.length===1&&String(items[0].sourceType||'')==='shape-rectangle');
 if(!button)return;
 button.disabled=!enabled;button.setAttribute('aria-disabled',String(!enabled));
 button.title=enabled?'Arredondar os quatro cantos':'Cantos — selecione um quadrado ou retângulo';
}
function rectangleWithCornerRadii(object,radii){
 if(!object||String(object.sourceType||'')!=='shape-rectangle')return false;
 const source=object.baseCanvas||object.canvas||object.restoreCanvas;
 object.shapeCornerRadii=normalizeShapeCornerRadii(radii,(source&&source.width)||object.originalW||object.w||1,(source&&source.height)||object.originalH||object.h||1);
 repaintRectangleObjectColor(object,null,null,effectiveShapeStrokeWidth(object));
 return true;
}
function cornerRadiiToMm(object){
 const r=objectShapeCornerRadii(object);return {tl:pxToMm(r.tl),tr:pxToMm(r.tr),br:pxToMm(r.br),bl:pxToMm(r.bl)}
}
function closeCornerRadiusModal(cancel=true){
 if(!cornerRadiusModal)return;
 if(cornerRadiusSession&&cornerRadiusSession.positionHandler)window.removeEventListener('resize',cornerRadiusSession.positionHandler);
 if(cancel&&cornerRadiusSession){
  const object=state.objects.find(item=>item.id===cornerRadiusSession.id);
  if(object){rectangleWithCornerRadii(object,cornerRadiusSession.original);render()}
 }
 cornerRadiusModal.remove();cornerRadiusModal=null;cornerRadiusSession=null;syncCornerRadiusButton();
}
function clampCornerRadiusModalPosition(left,top){
 if(!cornerRadiusModal)return {left:8,top:8};
 const box=cornerRadiusModal.querySelector('.dtf-corner-radius-box');if(!box)return {left:8,top:8};
 const pad=8,w=box.offsetWidth||510,h=box.offsetHeight||330,maxLeft=Math.max(pad,window.innerWidth-w-pad),maxTop=Math.max(pad,window.innerHeight-h-pad);
 return {left:clamp(Number(left)||pad,pad,maxLeft),top:clamp(Number(top)||pad,pad,maxTop)}
}
function positionCornerRadiusModal(forceAuto=false){
 if(!cornerRadiusModal)return;
 const box=cornerRadiusModal.querySelector('.dtf-corner-radius-box');if(!box)return;
 if(cornerRadiusSession&&cornerRadiusSession.userPositioned&&!forceAuto){
  const current=clampCornerRadiusModalPosition(parseFloat(box.style.left),parseFloat(box.style.top));
  box.style.left=current.left+'px';box.style.top=current.top+'px';box.style.right='auto';box.style.bottom='auto';box.style.transform='none';return
 }
 const anchor=cornerRadiusButton&&cornerRadiusButton.getBoundingClientRect?cornerRadiusButton.getBoundingClientRect():null,avoid=visibleSelectedObjectRect(),w=box.offsetWidth||510,h=box.offsetHeight||330,pos=chooseFloatingPosition(anchor,w,h,avoid),current=clampCornerRadiusModalPosition(pos.x,pos.y);
 box.style.left=current.left+'px';box.style.top=current.top+'px';box.style.right='auto';box.style.bottom='auto';box.style.transform='none';
}
function enableCornerRadiusModalDrag(){
 if(!cornerRadiusModal||!cornerRadiusSession)return;
 const box=cornerRadiusModal.querySelector('.dtf-corner-radius-box'),handle=cornerRadiusModal.querySelector('.dtf-corner-title-row');if(!box||!handle)return;
 let drag=null;
 handle.addEventListener('pointerdown',event=>{
  if(event.button!==0||event.target.closest('button,input,select,textarea'))return;
  event.preventDefault();event.stopPropagation();
  const rect=box.getBoundingClientRect();drag={pointerId:event.pointerId,startX:event.clientX,startY:event.clientY,left:rect.left,top:rect.top};
  cornerRadiusSession.userPositioned=true;handle.classList.add('dragging');
  try{handle.setPointerCapture(event.pointerId)}catch(_){}
 });
 handle.addEventListener('pointermove',event=>{
  if(!drag||event.pointerId!==drag.pointerId)return;
  event.preventDefault();
  const pos=clampCornerRadiusModalPosition(drag.left+(event.clientX-drag.startX),drag.top+(event.clientY-drag.startY));
  box.style.left=pos.left+'px';box.style.top=pos.top+'px';box.style.right='auto';box.style.bottom='auto';box.style.transform='none';
 });
 const finish=event=>{
  if(!drag||event.pointerId!==drag.pointerId)return;
  drag=null;handle.classList.remove('dragging');
  try{handle.releasePointerCapture(event.pointerId)}catch(_){}
 };
 handle.addEventListener('pointerup',finish);handle.addEventListener('pointercancel',finish);
}
function openCornerRadiusModal(){
 const items=selectedObjects(),object=items.length===1&&String(items[0].sourceType||'')==='shape-rectangle'?items[0]:null;
 if(!object){syncCornerRadiusButton();setStatus('Selecione um quadrado ou retângulo para arredondar os cantos.');return}
 closeCornerRadiusModal(true);
 const original=objectShapeCornerRadii(object),values=cornerRadiiToMm(object),source=object.baseCanvas||object.canvas,maxMm=Math.max(.1,pxToMm(Math.min((source&&source.width)||object.w,(source&&source.height)||object.h)/2));
 cornerRadiusSession={id:object.id,original:{...original},draft:{...original},linked:true,userPositioned:false};
 const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-corner-radius-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','dtfCornerRadiusTitle');
 modal.innerHTML='<div class="dtf-properties-box dtf-corner-radius-box"><div class="dtf-corner-title-row"><h3 id="dtfCornerRadiusTitle">Arredondar cantos</h3><button type="button" class="dtf-corner-close" data-close aria-label="Fechar e cancelar" title="Fechar e cancelar">×</button></div><div class="dtf-corner-link-row"><button type="button" class="dtf-corner-lock active" data-corner-lock aria-pressed="true" title="Cantos vinculados"><span aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="6" y="10" width="12" height="10" rx="2"/><path d="M8.5 10V7a3.5 3.5 0 0 1 7 0v3"/></svg></span><b>Vincular cantos</b></button><small>Valores em mm</small></div><div class="dtf-corner-grid"></div><div class="dtf-properties-actions"><button type="button" data-cancel>Cancelar</button><button type="button" data-apply>Aplicar</button></div></div>';
 const grid=modal.querySelector('.dtf-corner-grid'),labels={tl:'Superior esquerdo',tr:'Superior direito',br:'Inferior direito',bl:'Inferior esquerdo'};
 const icons={
  tl:'<svg viewBox="0 0 24 24"><path d="M20 5H11a6 6 0 0 0-6 6v9"/></svg>',
  tr:'<svg viewBox="0 0 24 24"><path d="M4 5h9a6 6 0 0 1 6 6v9"/></svg>',
  br:'<svg viewBox="0 0 24 24"><path d="M4 19h9a6 6 0 0 0 6-6V4"/></svg>',
  bl:'<svg viewBox="0 0 24 24"><path d="M20 19H11a6 6 0 0 1-6-6V4"/></svg>'
 };
 cornerKeys.forEach(key=>{
  const field=document.createElement('label');field.className='dtf-corner-field dtf-corner-'+key;field.innerHTML='<span class="dtf-corner-icon" aria-hidden="true">'+icons[key]+'</span><span class="dtf-corner-name">'+labels[key]+'</span><span class="dtf-corner-input-wrap"><input type="number" min="0" max="'+maxMm.toFixed(2)+'" step="0.1" inputmode="decimal" data-corner="'+key+'" aria-label="'+labels[key]+' em milímetros"><em>mm</em></span>';
  field.querySelector('input').value=Number(values[key].toFixed(2));grid.appendChild(field)
 });
 document.body.appendChild(modal);cornerRadiusModal=modal;
 const positionHandler=()=>positionCornerRadiusModal(false);cornerRadiusSession.positionHandler=positionHandler;positionCornerRadiusModal(true);enableCornerRadiusModalDrag();window.addEventListener('resize',positionHandler);
 const lock=modal.querySelector('[data-corner-lock]'),inputs=Array.from(modal.querySelectorAll('[data-corner]'));
 const readMm=input=>clamp(Number(String(input.value||'0').replace(',','.'))||0,0,maxMm);
 const preview=(changed)=>{
  if(!cornerRadiusSession)return;
  if(cornerRadiusSession.linked&&changed){const value=readMm(changed);inputs.forEach(input=>{if(input!==changed)input.value=String(value)})}
  const px={};inputs.forEach(input=>px[input.dataset.corner]=physicalMmToPx(readMm(input)));
  const sourceNow=object.baseCanvas||object.canvas;cornerRadiusSession.draft=normalizeShapeCornerRadii(px,(sourceNow&&sourceNow.width)||object.w,(sourceNow&&sourceNow.height)||object.h);
  rectangleWithCornerRadii(object,cornerRadiusSession.draft);render();positionCornerRadiusModal(false);setStatus('Prévia do arredondamento dos cantos')
 };
 inputs.forEach(input=>{input.addEventListener('input',()=>preview(input));input.addEventListener('change',()=>preview(input))});
 lock.addEventListener('click',()=>{
  cornerRadiusSession.linked=!cornerRadiusSession.linked;lock.classList.toggle('active',cornerRadiusSession.linked);lock.setAttribute('aria-pressed',String(cornerRadiusSession.linked));lock.title=cornerRadiusSession.linked?'Cantos vinculados':'Cantos independentes';
  if(cornerRadiusSession.linked){const first=inputs[0];inputs.slice(1).forEach(input=>input.value=first.value);preview(first)}
 });
 modal.querySelector('[data-cancel]').addEventListener('click',()=>closeCornerRadiusModal(true));modal.querySelector('[data-close]').addEventListener('click',()=>closeCornerRadiusModal(true));
 modal.querySelector('[data-apply]').addEventListener('click',()=>{
  const session=cornerRadiusSession;if(!session)return;
  const target=state.objects.find(item=>item.id===session.id),draft={...session.draft},originalRadii={...session.original};
  if(!target){closeCornerRadiusModal(false);return}
  rectangleWithCornerRadii(target,originalRadii);pushHistory('Arredondar cantos');rectangleWithCornerRadii(target,draft);render();cornerRadiusSession=null;closeCornerRadiusModal(false);setStatus('Arredondamento dos cantos aplicado')
 });
 modal.addEventListener('pointerdown',event=>{if(event.target===modal)closeCornerRadiusModal(true)});
 modal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeCornerRadiusModal(true)}});
 inputs[0].focus();inputs[0].select();
}
if(cornerRadiusButton)cornerRadiusButton.addEventListener('click',event=>{event.preventDefault();openCornerRadiusModal()});
const drawRectPreview=document.createElement('div');
drawRectPreview.className='dtf-draw-rect-preview';
drawRectPreview.setAttribute('aria-hidden','true');
inner.appendChild(drawRectPreview);
const drawRectMeasureLabel=document.createElement('div');
drawRectMeasureLabel.className='dtf-draw-measure-label';
drawRectMeasureLabel.setAttribute('aria-hidden','true');
document.body.appendChild(drawRectMeasureLabel);
let drawRectDrag=null;
function currentPaletteHex(){
 try{return normalizeHexColor(fillColor&&fillColor.value?fillColor.value:'#8c3f00')}catch(_){return '#8c3f00'}
}

const TEXT_FONT_CATEGORIES=[
 {id:'favorites',label:'Favoritas',fonts:[]},
 {id:'sans',label:'Sans-serif',fonts:['Roboto','Open Sans','Montserrat','Poppins','Lato','Nunito','Raleway','Inter','Ubuntu','Work Sans','DM Sans','Rubik','Oswald','Barlow','Fira Sans','Quicksand','Manrope','Archivo','Karla','Mulish']},
 {id:'serif',label:'Serif',fonts:['Merriweather','Playfair Display','Lora','PT Serif','Source Serif 4','Libre Baskerville','Crimson Text','EB Garamond','Cormorant Garamond','Bitter','Arvo','Alegreya','Spectral','Cardo','Vollkorn','Noto Serif','Domine','Zilla Slab','Roboto Slab','Josefin Slab']},
 {id:'mono',label:'Monospace',fonts:['Roboto Mono','Source Code Pro','Fira Code','JetBrains Mono','Space Mono','Inconsolata','IBM Plex Mono','Ubuntu Mono','Courier Prime','Anonymous Pro','Share Tech Mono','Nanum Gothic Coding','Overpass Mono','Red Hat Mono','Azeret Mono','Cutive Mono','Martian Mono','DM Mono','PT Mono','Kode Mono']},
 {id:'cursive',label:'Cursiva',fonts:['Dancing Script','Pacifico','Lobster','Great Vibes','Satisfy','Caveat','Sacramento','Allura','Kalam','Marck Script','Indie Flower','Shadows Into Light','Permanent Marker','Kaushan Script','Courgette','Yellowtail','Parisienne','Petit Formal Script','Bad Script','Patrick Hand']},
 {id:'fantasy',label:'Fantasia',fonts:['Bebas Neue','Anton','Bangers','Black Ops One','Righteous','Fredoka','Press Start 2P','Audiowide','Monoton','Luckiest Guy','Alfa Slab One','Russo One','Orbitron','Cinzel Decorative','UnifrakturCook','Creepster','Fascinate','Abril Fatface','Bungee','Titan One']},
 {id:'themes',label:'Temas',fonts:['Press Start 2P','Pixelify Sans','VT323','Bangers','Luckiest Guy','Titan One','Henny Penny','Creepster','Eater','Butcherman','Nosifer','Rubik Glitch','Monoton','Orbitron','Audiowide','Black Ops One','Cinzel Decorative','Uncial Antiqua','Pirata One','Rye','Mountains of Christmas','Bungee']}
];
const THEME_FONT_HINTS={
 'Press Start 2P':'Pixel / Minecraft', 'Pixelify Sans':'Pixel moderno / jogos', 'VT323':'Arcade / terminal retrô', 'Bangers':'Histórias em quadrinhos', 'Luckiest Guy':'Desenho animado', 'Titan One':'Desenho / título divertido', 'Henny Penny':'Infantil / personagem', 'Creepster':'Terror', 'Eater':'Terror / zumbi', 'Butcherman':'Terror / monstro', 'Nosifer':'Terror / sangue', 'Rubik Glitch':'Glitch / série futurista', 'Monoton':'Neon / anos 80', 'Orbitron':'Ficção científica / espaço', 'Audiowide':'Tecnologia / corrida', 'Black Ops One':'Ação / militar', 'Cinzel Decorative':'Fantasia épica', 'Uncial Antiqua':'Medieval / magia', 'Pirata One':'Pirata', 'Rye':'Faroeste', 'Mountains of Christmas':'Natal', 'Bungee':'Cartaz retrô'
};
const GOOGLE_TEXT_FONTS=TEXT_FONT_CATEGORIES.flatMap(category=>category.fonts.map(value=>({value,label:value,category:category.id})));
const TEXT_FONT_FAVORITES_KEY='printway_dtf_font_favorites_'+autosaveUserKey;
function readTextFontFavorites(){try{const values=JSON.parse(localStorage.getItem(TEXT_FONT_FAVORITES_KEY)||'[]');return Array.isArray(values)?[...new Set(values.map(value=>String(value||'').trim()).filter(Boolean))].slice(0,80):[]}catch(_){return []}}
function writeTextFontFavorites(values){try{localStorage.setItem(TEXT_FONT_FAVORITES_KEY,JSON.stringify([...new Set(values.map(value=>String(value||'').trim()).filter(Boolean))].slice(0,80)))}catch(_){}}
function isTextFontFavorite(value){return readTextFontFavorites().includes(String(value||'').trim())}
function toggleTextFontFavorite(value){const font=String(value||'').trim();if(!font)return false;const values=readTextFontFavorites(),index=values.indexOf(font);if(index>=0)values.splice(index,1);else values.unshift(font);writeTextFontFavorites(values);return index<0}
function allTextFontNames(){return [...new Set([...GOOGLE_TEXT_FONTS.map(font=>font.value),...readTextFontFavorites()])]} 
function textFontGroupLabel(value){const labels=[...new Set(TEXT_FONT_CATEGORIES.filter(category=>category.id!=='favorites'&&category.fonts.includes(value)).map(category=>category.label))];return labels.length?'('+labels.join(', ')+')':''}
function isCatalogTextFont(value){return GOOGLE_TEXT_FONTS.some(font=>font.value===String(value||''))}
function textFontMatches(term,category){const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(),query=normalize(term),base=category&&category.id==='favorites'?readTextFontFavorites():(category&&category.fonts||[]);if(!query)return base;const all=[...new Set([...base,...allTextFontNames()])],direct=all.filter(value=>normalize(value+' '+(THEME_FONT_HINTS[value]||'')).includes(query));if(direct.length)return direct;const letters=query.replace(/[^a-z0-9]/g,''),ranked=all.map(value=>{const name=normalize(value),overlap=[...letters].filter(letter=>name.includes(letter)).length,starts=name.startsWith(letters)?20:0;return {value,score:starts+overlap/Math.max(1,letters.length)}}).filter(item=>item.score>=.35).sort((a,b)=>b.score-a.score||a.value.localeCompare(b.value,'pt-BR')).slice(0,8).map(item=>item.value);return ranked}
function decorateTextFontFavorites(container,rerender,searchTerm='',category=null){if(!container)return;const searching=String(searchTerm||'').trim();container.querySelectorAll('.dtf-font-choice[data-value]').forEach(button=>{if(button.querySelector('.dtf-font-favorite-toggle'))return;const value=button.dataset.value;if(searching&&isCatalogTextFont(value)&&(!category||category.id!=='favorites'))return;const toggle=document.createElement('i');toggle.className='dtf-font-favorite-toggle';toggle.textContent=isTextFontFavorite(value)?'★':'☆';toggle.title=isTextFontFavorite(value)?'Remover das Favoritas':'Adicionar às Favoritas';toggle.setAttribute('role','button');toggle.setAttribute('aria-label',toggle.title);toggle.addEventListener('pointerdown',event=>{event.preventDefault();event.stopPropagation()});toggle.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();const added=toggleTextFontFavorite(value);toggle.textContent=added?'★':'☆';toggle.title=added?'Remover das Favoritas':'Adicionar às Favoritas';toggle.setAttribute('aria-label',toggle.title);if(rerender&&button.closest('.dtf-font-choice-list'))rerender()});button.appendChild(toggle)})}
const googleTextFontLinks=new Map();
const googleTextFontsLoaded=new Set();
function ensureGoogleTextFontsStyles(fonts=[]){
 const families=[...new Set((Array.isArray(fonts)?fonts:[fonts]).map(font=>String(font&&font.value||font||'').trim()).filter(Boolean))];if(!families.length)return;
 const key=families.slice().sort().join('|');if(googleTextFontLinks.has(key))return;
 const params=families.map(family=>'family='+encodeURIComponent(family).replace(/%20/g,'+').replace(/%2B/g,'+')+':wght@400;700').join('&');
 const link=document.createElement('link');link.rel='stylesheet';link.href='https://fonts.googleapis.com/css2?'+params+'&display=swap';document.head.appendChild(link);googleTextFontLinks.set(key,link);
}
function ensureGoogleTextFont(fontFamily){
 const family=String(fontFamily||'Roboto').trim()||'Roboto';
 ensureGoogleTextFontsStyles([family]);
 if(googleTextFontsLoaded.has(family))return Promise.resolve(family);
 googleTextFontsLoaded.add(family);
 if(document.fonts&&document.fonts.load){
  return document.fonts.load('16px "'+family+'"').then(()=>family).catch(()=>family);
 }
 return Promise.resolve(family);
}
function isTextShape(object){return !!object&&String(object.sourceType||'')==='shape-text'}
function textAlignValue(value){return ['left','center','right'].includes(String(value||''))?String(value):'center'}
function normalizeTextBoxData(input={}){
 return {
  textContent:String(input.textContent||'Digite aqui'),
  textFontFamily:String(input.textFontFamily||'Roboto'),
  textFontSize:clamp(Number(input.textFontSize)||32,8,400),
  textColor:normalizeHexColor(input.textColor||currentPaletteHex()||'#000000'),
  textAlign:textAlignValue(input.textAlign||'center'),
  textBold:input.textBold===true,
  textItalic:input.textItalic===true,
  textPadding:clamp(Number(input.textPadding)||8,0,120)
 }
}
function wrapTextForBox(ctx,text,maxWidth){
 const content=String(text==null?'':text).replace(/\r/g,'');
 const paragraphs=content.split('\n');
 const lines=[];
 paragraphs.forEach((paragraph,index)=>{
  const clean=paragraph.replace(/\s+/g,' ').trim();
  if(!clean){lines.push('');return}
  const words=clean.split(' ');
  let line='';
  words.forEach(word=>{
   const next=line?line+' '+word:word;
   if(!line||ctx.measureText(next).width<=maxWidth){
    line=next;
   }else{
    lines.push(line);
    line=word;
   }
  });
  if(line)lines.push(line);
  if(index<paragraphs.length-1&&paragraph===''&&lines[lines.length-1]!=='')lines.push('');
 });
 return lines.length?lines:['Digite aqui'];
}
function buildTextCanvas(width,height,input){
 const textData=normalizeTextBoxData(input);
 const c=document.createElement('canvas');
 const w=Math.max(24,Math.round(Number(width)||1)),h=Math.max(24,Math.round(Number(height)||1));
 c.width=w;c.height=h;
 const cx=c.getContext('2d');
 cx.clearRect(0,0,w,h);
 const padding=clamp(Number(textData.textPadding)||8,0,Math.floor(Math.min(w,h)/2));
 const fontSize=clamp(Number(textData.textFontSize)||32,8,400);
 const style=(textData.textItalic?'italic ':'')+(textData.textBold?'700 ':'400 ');
 cx.fillStyle=textData.textColor||'#000000';
 cx.font=style+fontSize+'px "'+textData.textFontFamily+'", sans-serif';
 cx.textAlign=textData.textAlign;
 cx.textBaseline='top';
 const maxWidth=Math.max(1,w-padding*2);
 const lines=wrapTextForBox(cx,textData.textContent,maxWidth);
 const lineHeight=Math.max(fontSize*1.2,fontSize+4);
 let y=padding;
 const x=textData.textAlign==='left'?padding:textData.textAlign==='right'?w-padding:w/2;
 lines.forEach(line=>{
  if(y+lineHeight>h-padding+lineHeight*0.25)return;
  cx.fillText(line||' ',x,y);
  y+=lineHeight;
 });
 return c;
}
function snapshotTextObjectProps(object){
 return {textContent:String(object.textContent||''),textFontFamily:String(object.textFontFamily||'Roboto'),textFontSize:clamp(Number(object.textFontSize)||32,8,400),textColor:normalizeHexColor(object.textColor||'#000000'),textAlign:textAlignValue(object.textAlign||'center'),textBold:object.textBold===true,textItalic:object.textItalic===true,textPadding:clamp(Number(object.textPadding)||8,0,120),originalW:Number(object.originalW)||Math.max(24,Math.round(object.w||1)),originalH:Number(object.originalH)||Math.max(24,Math.round(object.h||1))}
}
function repaintTextObject(object,overrides=null){
 if(!isTextShape(object))return false;
 const data=normalizeTextBoxData({...object,...(overrides||{})});
 const width=Math.max(24,Math.round(Number((overrides&&overrides.originalW)||object.originalW||object.w)||1));
 const height=Math.max(24,Math.round(Number((overrides&&overrides.originalH)||object.originalH||object.h)||1));
 const textCanvas=buildTextCanvas(width,height,data);
 object.textContent=data.textContent;
 object.textFontFamily=data.textFontFamily;
 object.textFontSize=data.textFontSize;
 object.textColor=data.textColor;
 object.textAlign=data.textAlign;
 object.textBold=data.textBold;
 object.textItalic=data.textItalic;
 object.textPadding=data.textPadding;
 object.canvas=textCanvas;
 object.baseCanvas=canvasFromData(cloneCanvasData(textCanvas));
 object.restoreCanvas=canvasFromData(cloneCanvasData(textCanvas));
 object.originalW=textCanvas.width;
 object.originalH=textCanvas.height;
 ensureGoogleTextFont(object.textFontFamily).then(()=>{
  try{
   const fresh=buildTextCanvas(width,height,object);
   object.canvas=fresh;
   object.baseCanvas=canvasFromData(cloneCanvasData(fresh));
   object.restoreCanvas=canvasFromData(cloneCanvasData(fresh));
   object.originalW=fresh.width;
   object.originalH=fresh.height;
   render();
  }catch(_){}
 });
 return true;
}

let textEditorModal=null,textEditorSession=null;
let textInlineSyncing=false,textInlineHistoryObjectId=null,textInlineHistoryArmed=false,textInlineSizeCommitTimer=0;
function closeTextEditorModal(){textEditorModal=null;textEditorSession=null}
function textInlineTarget(){return state.selectedIds.length===1&&isTextShape(selected())?selected():null}
function resetInlineTextHistory(){textInlineHistoryObjectId=null;textInlineHistoryArmed=false}
function setInlineTextDisabled(disabled){
 const controls=[ui.textContentInline,ui.textFontInline,ui.textSizeInline,ui.textColorInline,ui.textAlignInline,ui.textPaddingInline,ui.textBoldInline,ui.textItalicInline];
 controls.forEach(control=>{if(control)control.disabled=!!disabled});
 if(ui.textInlineControls)ui.textInlineControls.classList.toggle('is-disabled',!!disabled);
}
function ensureInlineFontOptions(){
 if(!ui.textFontInline||ui.textFontInline.options.length)return;
 GOOGLE_TEXT_FONTS.forEach(font=>{const option=document.createElement('option');option.value=font.value;option.textContent=font.label;ui.textFontInline.appendChild(option)});
}
function syncInlineTextControls(){
 if(!ui.textInlineControls)return;
 ensureInlineFontOptions();
 const object=textInlineTarget();
 textInlineSyncing=true;
 if(!object){
  setInlineTextDisabled(true);
  if(ui.textContentInline)ui.textContentInline.value='';
  if(ui.textFontInline)ui.textFontInline.value='Roboto';
  if(ui.textSizeInline)ui.textSizeInline.value='32';
  if(ui.textColorInline)ui.textColorInline.value='#000000';
  if(ui.textAlignInline)ui.textAlignInline.value='left';
  if(ui.textPaddingInline)ui.textPaddingInline.value='8';
  if(ui.textBoldInline)ui.textBoldInline.checked=false;
  if(ui.textItalicInline)ui.textItalicInline.checked=false;
  resetInlineTextHistory();
  textInlineSyncing=false;
  return;
 }
 setInlineTextDisabled(false);
 if(textInlineHistoryObjectId!==object.id){textInlineHistoryObjectId=object.id;textInlineHistoryArmed=false}
 if(ui.textContentInline)ui.textContentInline.value=String(object.textContent||'');
 if(ui.textFontInline)ui.textFontInline.value=String(object.textFontFamily||'Roboto');
 if(ui.textSizeInline)ui.textSizeInline.value=String(clamp(Number(object.textFontSize)||32,8,400));
 if(ui.textColorInline)ui.textColorInline.value=normalizeHexColor(object.textColor||'#000000');
 if(ui.textAlignInline)ui.textAlignInline.value=textAlignValue(object.textAlign||'left');
 if(ui.textPaddingInline)ui.textPaddingInline.value=String(clamp(Number(object.textPadding)||8,0,120));
 if(ui.textBoldInline)ui.textBoldInline.checked=object.textBold===true;
 if(ui.textItalicInline)ui.textItalicInline.checked=object.textItalic===true;
 textInlineSyncing=false;
}
function syncTextBoxCanvasSize(object,width,height){
 if(!isTextShape(object))return false;
 const newWidth=Math.max(24,Math.round(Number(width)||object.w||object.originalW||24));
 const newHeight=Math.max(24,Math.round(Number(height)||object.h||object.originalH||24));
 object.originalW=newWidth;object.originalH=newHeight;
 const fresh=buildTextCanvas(newWidth,newHeight,object);
 object.canvas=fresh;object.baseCanvas=canvasFromData(cloneCanvasData(fresh));object.restoreCanvas=canvasFromData(cloneCanvasData(fresh));
 return true;
}
function applyInlineTextChanges(){
 if(textInlineSyncing)return;
 const object=textInlineTarget();if(!object)return;
 if(textInlineHistoryObjectId!==object.id){textInlineHistoryObjectId=object.id;textInlineHistoryArmed=false}
 if(!textInlineHistoryArmed){pushHistory('Editar texto');textInlineHistoryArmed=true}
 const next={
  textContent:ui.textContentInline?ui.textContentInline.value:String(object.textContent||''),
  textFontFamily:ui.textFontInline?ui.textFontInline.value:'Roboto',
  textFontSize:clamp(Number(ui.textSizeInline&&ui.textSizeInline.value)||32,8,400),
  textColor:normalizeHexColor(ui.textColorInline&&ui.textColorInline.value?ui.textColorInline.value:'#000000'),
  textAlign:textAlignValue(ui.textAlignInline&&ui.textAlignInline.value?ui.textAlignInline.value:'left'),
  textBold:ui.textBoldInline&&ui.textBoldInline.checked===true,
  textItalic:ui.textItalicInline&&ui.textItalicInline.checked===true,
  textPadding:clamp(Number(ui.textPaddingInline&&ui.textPaddingInline.value)||8,0,120),
  originalW:Math.max(24,Math.round(object.w||object.originalW||24)),
  originalH:Math.max(24,Math.round(object.h||object.originalH||24))
 };
 const apply=()=>{repaintTextObject(object,next);render();updateSelection();if(textDirectEditSession&&textDirectEditSession.objectId===object.id)styleDirectTextEditor();setProjectDirty(true)};
 if(next.textFontFamily!==object.textFontFamily)ensureGoogleTextFont(next.textFontFamily).then(apply);else apply();
}
function attachInlineTextEvents(){
 ensureInlineFontOptions();
 const controls=[ui.textContentInline,ui.textFontInline,ui.textSizeInline,ui.textColorInline,ui.textAlignInline,ui.textPaddingInline,ui.textBoldInline,ui.textItalicInline].filter(Boolean);
 controls.forEach(control=>{
  control.addEventListener('input',applyInlineTextChanges);
  control.addEventListener('change',applyInlineTextChanges);
  control.addEventListener('focus',()=>{const object=textInlineTarget();if(object&&textInlineHistoryObjectId!==object.id){textInlineHistoryObjectId=object.id;textInlineHistoryArmed=false}});
  control.addEventListener('blur',()=>{window.setTimeout(()=>{if(!ui.textInlineControls||!ui.textInlineControls.contains(document.activeElement))textInlineHistoryArmed=false},0)});
 });
}
function directTextTransform(object){
 const sx=(object.skewAnchorX==='left'||object.skewAnchorX==='right'?object.skewAnchorX:'center'),sy=(object.skewAnchorY==='top'||object.skewAnchorY==='bottom'?object.skewAnchorY:'center');
 const originX=sx==='left'?'0%':sx==='right'?'100%':'50%',originY=sy==='top'?'0%':sy==='bottom'?'100%':'50%';
 return {transform:'rotate('+(Number(object.rotation)||0)+'deg) skew('+(Number(object.skewX)||0)+'deg,'+(Number(object.skewY)||0)+'deg)',origin:originX+' '+originY};
}
function styleDirectTextEditor(){
 if(!textDirectEditSession)return;
 const object=state.objects.find(item=>item.id===textDirectEditSession.objectId),editor=textDirectEditSession.editor;if(!object||!editor)return;
 const tx=directTextTransform(object);
 editor.style.left=object.x+'px';editor.style.top=object.y+'px';editor.style.width=object.w+'px';editor.style.height=object.h+'px';
 editor.style.fontFamily='"'+String(object.textFontFamily||'Roboto').replace(/"/g,'')+'", sans-serif';
 editor.style.fontSize=clamp(Number(object.textFontSize)||32,8,400)+'px';editor.style.fontWeight=object.textBold===true?'700':'400';editor.style.fontStyle=object.textItalic===true?'italic':'normal';
 editor.style.color=normalizeHexColor(object.textColor||'#000000');editor.style.textAlign=textAlignValue(object.textAlign||'left');editor.style.padding=clamp(Number(object.textPadding)||8,0,120)+'px';editor.style.lineHeight=String(Number(object.textLineHeight)||1.22);editor.style.letterSpacing=(Number(object.textLetterSpacing)||0)+'px';editor.style.writingMode=textDirectionValue(object.textDirection||'horizontal')==='vertical'?'vertical-rl':'horizontal-tb';editor.style.background=Number(object.textBackgroundOpacity)>0?textHexRgba(object.textBackgroundColor||'#ffffff',object.textBackgroundOpacity):'transparent';
 editor.style.transform=tx.transform;editor.style.transformOrigin=tx.origin;
}
function finishDirectTextEdit(commit=true){
 const session=textDirectEditSession;if(!session)return;
 const object=state.objects.find(item=>item.id===session.objectId);textDirectEditSession=null;
 if(object&&!commit&&session.original){Object.assign(object,session.original);repaintTextObject(object,{originalW:Math.max(24,Math.round(object.w)),originalH:Math.max(24,Math.round(object.h))})}
 if(session.editor&&session.editor.isConnected)session.editor.remove();
 if(selection)selection.classList.remove('dtf-text-direct-editing');
 if(object){syncTextBoxCanvasSize(object,object.w,object.h);render();updateSelection();syncInlineTextControls();setProjectDirty(true)}
}
function startDirectTextEdit(targetObject=null,selectAll=false){
 const object=targetObject&&isTextShape(targetObject)?targetObject:(isTextShape(selected())?selected():null);if(!object||object.locked)return false;
 if(textDirectEditSession&&textDirectEditSession.objectId===object.id){const current=textDirectEditSession.editor;styleDirectTextEditor();current.focus();if(selectAll)current.select();return true}
 if(textDirectEditSession)finishDirectTextEdit(true);
 if(selected()!==object)selectObject(object,false);
 if(typeof showEditorTab==='function'&&activeTab!=='desenhar')showEditorTab('desenhar');
 deactivateDrawTool(true);state.tool='select';setCursorSelectButtonActive(true);
 const editor=document.createElement('textarea');editor.className='dtf-direct-text-editor';editor.value=String(object.textContent||'');editor.spellcheck=true;editor.setAttribute('aria-label','Editar texto diretamente no objeto');
 inner.appendChild(editor);textDirectEditSession={objectId:object.id,editor,original:snapshotTextObjectProps(object),historySaved:false};
 if(selection)selection.classList.add('dtf-text-direct-editing');styleDirectTextEditor();render();updateSelection();
 editor.addEventListener('pointerdown',event=>event.stopPropagation());editor.addEventListener('dblclick',event=>event.stopPropagation());
 editor.addEventListener('input',()=>{
  const session=textDirectEditSession;if(!session||session.objectId!==object.id)return;
  if(!session.historySaved){pushHistory('Editar texto');session.historySaved=true}
  object.textContent=editor.value;repaintTextObject(object,{textContent:editor.value,originalW:Math.max(24,Math.round(object.w)),originalH:Math.max(24,Math.round(object.h))});
  syncInlineTextControls();styleDirectTextEditor();setProjectDirty(true);
 });
 editor.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();event.stopPropagation();finishDirectTextEdit(true);return}if((event.ctrlKey||event.metaKey)&&event.key==='Enter'){event.preventDefault();finishDirectTextEdit(true)}});
 editor.addEventListener('blur',()=>window.setTimeout(()=>{if(textDirectEditSession&&textDirectEditSession.editor===editor)finishDirectTextEdit(true)},0));
 requestAnimationFrame(()=>{editor.focus();if(selectAll)editor.select();else{try{editor.setSelectionRange(editor.value.length,editor.value.length)}catch(_){}}});
 setStatus('Editando texto diretamente no objeto. Clique fora para concluir.');return true;
}
function focusInlineTextControls(selectAll=false){
 syncInlineTextControls();
 const object=textInlineTarget();if(object)startDirectTextEdit(object,selectAll);
}
function openTextEditorModal(targetObject=null){
 const object=targetObject&&isTextShape(targetObject)?targetObject:(isTextShape(selected())?selected():null);
 if(!object){setStatus('Selecione uma caixa de texto para editar.');return}
 startDirectTextEdit(object,true);
}
ensureGoogleTextFontsStyles(['Roboto']);

/* 1.0.305 — texto artístico com formatação por seleção, ajuste automático da
 * caixa e prévia reversível de fonte/tamanho. As propriedades simples do
 * objeto continuam existindo para manter compatibilidade com projetos antigos. */
const MIN_TEXT_FONT_SIZE=6;
const TEXT_SIZE_CHOICES=[6,7,8,9,10,11,12,14,16,18,24,36,48,72,100,150,200];
let textChoiceMenu=null,textChoicePreview=null;

function textStyleFromInput(input={}){
 return {
  fontFamily:String(input.fontFamily||input.textFontFamily||'Roboto'),
  fontSize:clamp(Number(input.fontSize||input.textFontSize)||32,MIN_TEXT_FONT_SIZE,400),
  color:normalizeHexColor(input.color||input.textColor||'#000000'),
  bold:input.bold===true||input.textBold===true,
  italic:input.italic===true||input.textItalic===true,
  underline:input.underline===true||input.textUnderline===true
 }
}
function normalizeTextRun(run={},fallback={}){
 const style=textStyleFromInput({...fallback,...run});
 return {text:String(run.text==null?'':run.text),...style}
}
function sameTextRunStyle(a,b){
 return !!a&&!!b&&a.fontFamily===b.fontFamily&&Number(a.fontSize)===Number(b.fontSize)&&a.color===b.color&&a.bold===b.bold&&a.italic===b.italic&&a.underline===b.underline
}
function mergeTextRuns(runs){
 const merged=[];
 (runs||[]).forEach(item=>{
  const run=normalizeTextRun(item,item);
  if(!run.text&&merged.length)return;
  const previous=merged[merged.length-1];
  if(previous&&sameTextRunStyle(previous,run))previous.text+=run.text;
  else merged.push(run)
 });
 return merged.length?merged:[normalizeTextRun({text:''},{})]
}
function cloneTextRuns(runs){return (runs||[]).map(run=>({...run}))}
function normalizeTextRuns(input={}){
 const fallback=textStyleFromInput(input);
 const content=input.textContent==null?'Digite aqui':String(input.textContent);
 const source=Array.isArray(input.textRuns)&&input.textRuns.length?input.textRuns:[{text:content,...fallback}];
 return mergeTextRuns(source.map(run=>normalizeTextRun(run,fallback)))
}
function textContentFromRuns(runs){return (runs||[]).map(run=>String(run.text||'')).join('')}
function textVerticalAlignValue(value){return ['top','center','bottom'].includes(String(value||''))?String(value):'top'}
function textDirectionValue(value){return ['horizontal','vertical','rotate90'].includes(String(value||''))?String(value):'horizontal'}
function textListValue(value){return ['none','bullet','number'].includes(String(value||''))?String(value):'none'}
function textCaseValue(value){return ['normal','upper','lower','title'].includes(String(value||''))?String(value):'normal'}
function textHexRgba(hex,opacity){const color=normalizeHexColor(hex||'#ffffff'),value=clamp(Number(opacity)==null?1:Number(opacity),0,1),r=parseInt(color.slice(1,3),16),g=parseInt(color.slice(3,5),16),b=parseInt(color.slice(5,7),16);return 'rgba('+r+','+g+','+b+','+value+')'}
function textMeasureWithSpacing(ctx,text,spacing){const value=String(text||''),gap=Number(spacing)||0;return value?ctx.measureText(value).width+gap*Math.max(0,[...value].length-1):0}
function drawTextWithSpacing(ctx,text,x,y,spacing){const value=String(text||''),gap=Number(spacing)||0,paint=ctx.lineWidth>1&&ctx.strokeText?ctx.strokeText.bind(ctx):ctx.fillText.bind(ctx);if(!gap){paint(value,x,y);return}let cursor=x;for(const character of [...value]){paint(character,cursor,y);cursor+=ctx.measureText(character).width+gap}}
function textRunsWithList(runs,list,start=1){const mode=textListValue(list);if(mode==='none')return cloneTextRuns(runs);let paragraph=0,atStart=true,result=[];normalizeTextRuns({textRuns:runs}).forEach(run=>{let output='';String(run.text||'').split(/(\n)/).forEach(part=>{if(part==='\n'){output+='\n';atStart=true}else{if(atStart){paragraph++;output+=(mode==='bullet'?'• ':String(start+paragraph-1)+'. ');atStart=false}output+=part}});result.push({...run,text:output})});return mergeTextRuns(result)}
function normalizeTextBoxData(input={}){
 const textRuns=normalizeTextRuns(input),first=textRuns[0]||normalizeTextRun({text:''},input);
 return {
  textContent:textContentFromRuns(textRuns),textRuns,
  textFontFamily:String(input.textFontFamily||first.fontFamily||'Roboto'),
  textFontSize:clamp(Number(input.textFontSize)||Number(first.fontSize)||32,MIN_TEXT_FONT_SIZE,400),
  textColor:normalizeHexColor(input.textColor||first.color||currentPaletteHex()||'#000000'),
  textAlign:textAlignValue(input.textAlign||'center'),
  textBold:input.textBold===true,textItalic:input.textItalic===true,textUnderline:input.textUnderline===true,
  textPadding:clamp(Number(input.textPadding)==null?8:Number(input.textPadding),0,120),
  textLineHeight:clamp(Number(input.textLineHeight)||1.22,.8,3),
  textLetterSpacing:clamp(Number(input.textLetterSpacing)||0,-20,100),
  textVerticalAlign:textVerticalAlignValue(input.textVerticalAlign||'top'),
  textDirection:textDirectionValue(input.textDirection||'horizontal'),
  textIndent:clamp(Number(input.textIndent)||0,0,300),
  textList:textListValue(input.textList||'none'),
  textListStart:Math.max(1,Number(input.textListStart)||1),
  textStrokeColor:normalizeHexColor(input.textStrokeColor||'#000000'),
  textStrokeWidth:clamp(Number(input.textStrokeWidth)||0,0,20),
  textBackgroundColor:normalizeHexColor(input.textBackgroundColor||'#ffffff'),
  textBackgroundOpacity:clamp(Number(input.textBackgroundOpacity)||0,0,1),
  textBackgroundRadius:clamp(Number(input.textBackgroundRadius)||0,0,120),
  textShadow:input.textShadow===true,
  textShadowColor:normalizeHexColor(input.textShadowColor||'#000000'),
  textShadowBlur:clamp(Number(input.textShadowBlur)||6,0,80),
  textShadowX:clamp(Number(input.textShadowX)||3,-80,80),
  textShadowY:clamp(Number(input.textShadowY)||3,-80,80)
 }
}
function textCanvasFont(run){
 return (run.italic?'italic ':'')+(run.bold?'700 ':'400 ')+clamp(Number(run.fontSize)||32,MIN_TEXT_FONT_SIZE,400)+'px "'+String(run.fontFamily||'Roboto').replace(/"/g,'')+'", sans-serif'
}
function layoutTextRuns(input={}){
 const data=normalizeTextBoxData(input);data.textRuns=textRunsWithList(data.textRuns,data.textList,data.textListStart);data.textContent=textContentFromRuns(data.textRuns);const measure=document.createElement('canvas').getContext('2d');
 const makeLine=fontSize=>({fragments:[],width:0,height:Math.max(MIN_TEXT_FONT_SIZE,Number(fontSize)||32)*data.textLineHeight,minX:0,maxX:0,maxFont:Math.max(MIN_TEXT_FONT_SIZE,Number(fontSize)||32)});
 if(data.textDirection==='vertical'){
  const columns=[];let column=[];
  data.textRuns.forEach(run=>{for(const character of String(run.text||'')){if(character==='\n'){if(column.length)columns.push(column);column=[];continue}measure.font=textCanvasFont(run);const fontSize=clamp(Number(run.fontSize)||32,MIN_TEXT_FONT_SIZE,400);column.push({text:character,run,width:Math.max(fontSize,measure.measureText(character).width),height:fontSize*data.textLineHeight})}});if(column.length||!columns.length)columns.push(column);
  const maxColumnHeight=Math.max(1,...columns.map(items=>items.reduce((sum,item)=>sum+item.height+data.textLetterSpacing,0))),columnWidth=Math.max(MIN_TEXT_FONT_SIZE,...columns.map(items=>Math.max(1,...items.map(item=>item.width))));
  const padding=clamp(Number(data.textPadding)||0,0,120),glyphInsetX=Math.max(2,Math.ceil(columnWidth*.07)),glyphInsetY=Math.max(2,Math.ceil(columnWidth*.12));
  return {data,vertical:true,columns,padding,glyphInsetX,glyphInsetY,width:Math.max(4,Math.ceil(columns.length*columnWidth+padding*2+glyphInsetX*2)),height:Math.max(4,Math.ceil(maxColumnHeight+padding*2+glyphInsetY*2))}
 }
 const lines=[makeLine(data.textFontSize)];let line=lines[0];
 data.textRuns.forEach(run=>{
  const parts=String(run.text||'').split('\n');
  parts.forEach((part,index)=>{
   if(part){
    measure.font=textCanvasFont(run);const metrics=measure.measureText(part),width=textMeasureWithSpacing(measure,part,data.textLetterSpacing),start=line.width,fontSize=clamp(Number(run.fontSize)||32,MIN_TEXT_FONT_SIZE,400),left=Number.isFinite(metrics.actualBoundingBoxLeft)?metrics.actualBoundingBoxLeft:0,right=Number.isFinite(metrics.actualBoundingBoxRight)?metrics.actualBoundingBoxRight:width,ascent=Number.isFinite(metrics.actualBoundingBoxAscent)?metrics.actualBoundingBoxAscent:fontSize*.82,descent=Number.isFinite(metrics.actualBoundingBoxDescent)?metrics.actualBoundingBoxDescent:fontSize*.28;
    line.fragments.push({text:part,run,width});line.width+=width;line.minX=Math.min(line.minX,start-left);line.maxX=Math.max(line.maxX,start+Math.max(width,right));line.maxFont=Math.max(line.maxFont,fontSize);
    line.height=Math.max(line.height,Math.ceil(ascent+descent+2),fontSize*data.textLineHeight)
   }
   if(index<parts.length-1){line=makeLine(Number(run.fontSize)||Number(data.textFontSize)||32);lines.push(line)}
  })
 });
 const padding=clamp(Number(data.textPadding)||0,0,120);
 const maxFont=Math.max(MIN_TEXT_FONT_SIZE,...lines.map(line=>line.maxFont||MIN_TEXT_FONT_SIZE)),glyphInsetX=Math.max(2,Math.ceil(maxFont*.07)),glyphInsetY=Math.max(2,Math.ceil(maxFont*.12)),indent=data.textAlign==='left'?data.textIndent:0;
 return {data,lines,padding,glyphInsetX,glyphInsetY,width:Math.max(4,Math.ceil(Math.max(1,...lines.map(item=>item.maxX-item.minX))+padding*2+glyphInsetX*2+indent)),height:Math.max(4,Math.ceil(lines.reduce((total,item)=>total+item.height,0)+padding*2+glyphInsetY*2))}
}
function buildTextCanvas(width,height,input){
 const layout=layoutTextRuns(input),c=document.createElement('canvas');
 const logicalW=layout.width,logicalH=layout.height,rotated=layout.data.textDirection==='rotate90',w=Math.max(4,Math.round(Number(width)||(rotated?logicalH:logicalW))),h=Math.max(4,Math.round(Number(height)||(rotated?logicalW:logicalH)));
 c.width=w;c.height=h;const cx=c.getContext('2d');cx.clearRect(0,0,w,h);
 const drawRoundedBackground=(context,bgW,bgH)=>{if(layout.data.textBackgroundOpacity<=0)return;context.save();context.fillStyle=textHexRgba(layout.data.textBackgroundColor,layout.data.textBackgroundOpacity);const radius=Math.min(layout.data.textBackgroundRadius,bgW/2,bgH/2);context.beginPath();if(context.roundRect)context.roundRect(0,0,bgW,bgH,radius);else{context.moveTo(radius,0);context.arcTo(bgW,0,bgW,bgH,radius);context.arcTo(bgW,bgH,0,bgH,radius);context.arcTo(0,bgH,0,0,radius);context.arcTo(0,0,bgW,0,radius)}context.fill();context.restore()};
 const drawHorizontal=(context,bgW,bgH)=>{drawRoundedBackground(context,bgW,bgH);const totalHeight=layout.lines.reduce((sum,line)=>sum+line.height,0),free=Math.max(0,bgH-layout.padding*2-layout.glyphInsetY*2-totalHeight);let y=layout.padding+layout.glyphInsetY+(layout.data.textVerticalAlign==='center'?free/2:layout.data.textVerticalAlign==='bottom'?free:0);context.textBaseline='top';layout.lines.forEach(line=>{let x=layout.data.textAlign==='right'?bgW-layout.padding-layout.glyphInsetX-line.maxX:layout.data.textAlign==='center'?bgW/2-(line.minX+line.maxX)/2:layout.padding+layout.glyphInsetX+layout.data.textIndent-line.minX;line.fragments.forEach(fragment=>{const run=fragment.run,fontSize=clamp(Number(run.fontSize)||32,MIN_TEXT_FONT_SIZE,400);context.font=textCanvasFont(run);if(layout.data.textShadow){context.shadowColor=textHexRgba(layout.data.textShadowColor,.65);context.shadowBlur=layout.data.textShadowBlur;context.shadowOffsetX=layout.data.textShadowX;context.shadowOffsetY=layout.data.textShadowY}else{context.shadowColor='transparent';context.shadowBlur=0;context.shadowOffsetX=0;context.shadowOffsetY=0}if(layout.data.textStrokeWidth>0){context.strokeStyle=layout.data.textStrokeColor;context.lineWidth=layout.data.textStrokeWidth;drawTextWithSpacing(context,fragment.text,x,y,layout.data.textLetterSpacing)}context.fillStyle=run.color||'#000000';drawTextWithSpacing(context,fragment.text,x,y,layout.data.textLetterSpacing);if(run.underline){context.save();context.shadowColor='transparent';context.strokeStyle=run.color||'#000000';context.lineWidth=Math.max(1,fontSize/16);context.beginPath();context.moveTo(x,y+fontSize*1.08);context.lineTo(x+fragment.width,y+fontSize*1.08);context.stroke();context.restore()}x+=fragment.width});y+=line.height});};
 if(layout.vertical){drawRoundedBackground(cx,w,h);const columnWidth=Math.max(MIN_TEXT_FONT_SIZE,...layout.columns.flatMap(column=>column.map(item=>item.width))),totalWidth=layout.columns.length*columnWidth,offsetX=layout.data.textAlign==='right'?w-layout.padding-layout.glyphInsetX-totalWidth:layout.data.textAlign==='center'?(w-totalWidth)/2:layout.padding+layout.glyphInsetX;layout.columns.forEach((column,index)=>{let y=layout.padding+layout.glyphInsetY;const totalHeight=column.reduce((sum,item)=>sum+item.height+layout.data.textLetterSpacing,0),free=Math.max(0,h-layout.padding*2-layout.glyphInsetY*2-totalHeight);if(layout.data.textVerticalAlign==='center')y+=free/2;else if(layout.data.textVerticalAlign==='bottom')y+=free;const x=offsetX+index*columnWidth;column.forEach(item=>{const run=item.run,fontSize=clamp(Number(run.fontSize)||32,MIN_TEXT_FONT_SIZE,400);cx.font=textCanvasFont(run);cx.fillStyle=run.color||'#000000';drawTextWithSpacing(cx,item.text,x,y,layout.data.textLetterSpacing);y+=item.height+layout.data.textLetterSpacing})})}else if(rotated){cx.save();cx.translate(w,0);cx.rotate(Math.PI/2);drawHorizontal(cx,h,w);cx.restore()}else drawHorizontal(cx,w,h);
 return c
}
function syncTextDefaultsFromRuns(object){
 const first=normalizeTextRuns(object)[0]||normalizeTextRun({text:''},object);
 object.textContent=textContentFromRuns(object.textRuns);object.textFontFamily=first.fontFamily;object.textFontSize=first.fontSize;object.textColor=first.color;
 object.textBold=first.bold;object.textItalic=first.italic;object.textUnderline=first.underline
}
function snapshotTextObjectProps(object){
 return {x:Number(object.x)||0,y:Number(object.y)||0,w:Number(object.w)||4,h:Number(object.h)||4,textContent:String(object.textContent||''),textRuns:cloneTextRuns(normalizeTextRuns(object)),textFontFamily:String(object.textFontFamily||'Roboto'),textFontSize:clamp(Number(object.textFontSize)||32,MIN_TEXT_FONT_SIZE,400),textColor:normalizeHexColor(object.textColor||'#000000'),textAlign:textAlignValue(object.textAlign||'center'),textBold:object.textBold===true,textItalic:object.textItalic===true,textUnderline:object.textUnderline===true,textPadding:clamp(Number(object.textPadding)||0,0,120),textLineHeight:clamp(Number(object.textLineHeight)||1.22,.8,3),textLetterSpacing:clamp(Number(object.textLetterSpacing)||0,-20,100),textVerticalAlign:textVerticalAlignValue(object.textVerticalAlign||'top'),textDirection:textDirectionValue(object.textDirection||'horizontal'),textIndent:clamp(Number(object.textIndent)||0,0,300),textList:textListValue(object.textList||'none'),textListStart:Math.max(1,Number(object.textListStart)||1),textStrokeColor:normalizeHexColor(object.textStrokeColor||'#000000'),textStrokeWidth:clamp(Number(object.textStrokeWidth)||0,0,20),textBackgroundColor:normalizeHexColor(object.textBackgroundColor||'#ffffff'),textBackgroundOpacity:clamp(Number(object.textBackgroundOpacity)||0,0,1),textBackgroundRadius:clamp(Number(object.textBackgroundRadius)||0,0,120),textShadow:object.textShadow===true,textShadowColor:normalizeHexColor(object.textShadowColor||'#000000'),textShadowBlur:clamp(Number(object.textShadowBlur)||6,0,80),textShadowX:clamp(Number(object.textShadowX)||3,-80,80),textShadowY:clamp(Number(object.textShadowY)||3,-80,80),textAutoFit:object.textAutoFit!==false,originalW:Number(object.originalW)||Math.max(4,Math.round(object.w||1)),originalH:Number(object.originalH)||Math.max(4,Math.round(object.h||1))}
}
function repaintTextObject(object,overrides=null){
 if(!isTextShape(object))return false;
 const source={...object,...(overrides||{})};
 if(overrides&&Object.prototype.hasOwnProperty.call(overrides,'textContent')&&!Object.prototype.hasOwnProperty.call(overrides,'textRuns')){const style=textStyleFromInput(source);source.textRuns=[{text:String(overrides.textContent==null?'':overrides.textContent),...style}]}
 const data=normalizeTextBoxData(source),width=Math.max(4,Math.round(Number((overrides&&overrides.originalW)||object.originalW||object.w)||1)),height=Math.max(4,Math.round(Number((overrides&&overrides.originalH)||object.originalH||object.h)||1));
 object.textRuns=cloneTextRuns(data.textRuns);object.textContent=data.textContent;object.textAlign=data.textAlign;object.textPadding=data.textPadding;object.textLineHeight=data.textLineHeight;object.textLetterSpacing=data.textLetterSpacing;object.textVerticalAlign=data.textVerticalAlign;object.textDirection=data.textDirection;object.textIndent=data.textIndent;object.textList=data.textList;object.textListStart=data.textListStart;object.textStrokeColor=data.textStrokeColor;object.textStrokeWidth=data.textStrokeWidth;object.textBackgroundColor=data.textBackgroundColor;object.textBackgroundOpacity=data.textBackgroundOpacity;object.textBackgroundRadius=data.textBackgroundRadius;object.textShadow=data.textShadow;object.textShadowColor=data.textShadowColor;object.textShadowBlur=data.textShadowBlur;object.textShadowX=data.textShadowX;object.textShadowY=data.textShadowY;syncTextDefaultsFromRuns(object);
 const textCanvas=buildTextCanvas(width,height,object);object.canvas=textCanvas;object.baseCanvas=canvasFromData(cloneCanvasData(textCanvas));object.restoreCanvas=canvasFromData(cloneCanvasData(textCanvas));object.originalW=textCanvas.width;object.originalH=textCanvas.height;
 const token=(Number(object._textPaintToken)||0)+1;object._textPaintToken=token;const families=[...new Set(object.textRuns.map(run=>run.fontFamily))];
 Promise.all(families.map(ensureGoogleTextFont)).then(()=>{if(object._textPaintToken!==token)return;try{fitTextObjectToContent(object);if(textDirectEditSession&&textDirectEditSession.objectId===object.id)styleDirectTextEditor();render();updateSelection()}catch(_){}});
 return true
}
function syncTextBoxCanvasSize(object,width,height){
 if(!isTextShape(object))return false;
 const newWidth=Math.max(4,Math.round(Number(width)||object.w||object.originalW||4)),newHeight=Math.max(4,Math.round(Number(height)||object.h||object.originalH||4));
 object.originalW=newWidth;object.originalH=newHeight;const fresh=buildTextCanvas(newWidth,newHeight,object);
 object.canvas=fresh;object.baseCanvas=canvasFromData(cloneCanvasData(fresh));object.restoreCanvas=canvasFromData(cloneCanvasData(fresh));return true
}
function fitTextObjectToContent(object,options={}){
 if(!isTextShape(object))return false;
 const measured=layoutTextRuns(object),preserveRight=options.preserveRight===true,preserveBottom=options.preserveBottom===true;
 const oldRight=object.x+object.w,oldBottom=object.y+object.h,left=object.x,top=object.y;
 object.w=Math.min(canvas.width,Math.max(4,measured.width));object.h=Math.min(canvas.height,Math.max(4,measured.height));
 object.x=clamp(preserveRight?oldRight-object.w:left,0,Math.max(0,canvas.width-object.w));object.y=clamp(preserveBottom?oldBottom-object.h:top,0,Math.max(0,canvas.height-object.h));
 return syncTextBoxCanvasSize(object,object.w,object.h)
}
const fitTextObjectToContentWithBoxMode=fitTextObjectToContent;
fitTextObjectToContent=function(object,options={}){if(!isTextShape(object))return false;const measured=layoutTextRuns(object),overflow=measured.width>Number(object.w||0)+.5||measured.height>Number(object.h||0)+.5;if(object.textAutoFit===false&&!overflow)return syncTextBoxCanvasSize(object,object.w,object.h);return fitTextObjectToContentWithBoxMode(object,options)};
function scaleTextRuns(runs,factor){
 const safe=Math.max(.01,Number(factor)||1);
 return normalizeTextRuns({textRuns:runs}).map(run=>({...run,fontSize:clamp(Number(run.fontSize)*safe,MIN_TEXT_FONT_SIZE,400)}))
}
function scaleTextObjectFromBaseline(object,baselineRuns,baselineFontSize,factor,width,height,options={}){
 if(!isTextShape(object))return;
 object.textAutoFit=true;object._textPaintToken=(Number(object._textPaintToken)||0)+1;
 object.textRuns=scaleTextRuns(baselineRuns&&baselineRuns.length?baselineRuns:[{text:object.textContent||'',...textStyleFromInput(object),fontSize:baselineFontSize||object.textFontSize}],factor);
 syncTextDefaultsFromRuns(object);object.w=width;object.h=height;fitTextObjectToContentWithBoxMode(object,{preserveRight:options.preserveRight===true,preserveBottom:options.preserveBottom===true})
}
function textScaleFactorFromResize(handle,sx,sy){
 const horizontal=handle==='e'||handle==='w',vertical=handle==='n'||handle==='s';
 return Math.max(.01,horizontal?sx:vertical?sy:Math.min(sx,sy))
}
function textRunSelectionStyle(object,start,end){
 const runs=normalizeTextRuns(object),styles=[],from=Math.max(0,Math.min(start,end)),to=Math.max(from,Math.max(start,end));let offset=0;
 runs.forEach(run=>{const next=offset+run.text.length;if((to>from&&next>from&&offset<to)||(to===from&&offset<=from&&next>=from))styles.push(run);offset=next});
 const first=styles[0]||runs[0],mixed=key=>styles.some(run=>run[key]!==first[key]);
 return {first,mixedFont:mixed('fontFamily'),mixedSize:mixed('fontSize'),mixedColor:mixed('color'),mixedBold:mixed('bold'),mixedItalic:mixed('italic'),mixedUnderline:mixed('underline')}
}
function applyStyleToTextRuns(runs,start,end,patch){
 const from=Math.max(0,Math.min(start,end)),to=Math.max(from,Math.max(start,end));let offset=0;const result=[];
 normalizeTextRuns({textRuns:runs}).forEach(run=>{
  const next=offset+run.text.length,localStart=Math.max(0,from-offset),localEnd=Math.min(run.text.length,to-offset);
  if(localStart>=localEnd)result.push({...run});
  else{if(localStart>0)result.push({...run,text:run.text.slice(0,localStart)});result.push(normalizeTextRun({...run,...patch,text:run.text.slice(localStart,localEnd)},run));if(localEnd<run.text.length)result.push({...run,text:run.text.slice(localEnd)})}
  offset=next
 });
 return mergeTextRuns(result)
}
function selectionOffsetsInEditor(editor){
 const selectionNow=window.getSelection&&window.getSelection();if(!editor||!selectionNow||!selectionNow.rangeCount)return null;
 const range=selectionNow.getRangeAt(0);if(!editor.contains(range.startContainer)||!editor.contains(range.endContainer))return null;
 const before=document.createRange();before.selectNodeContents(editor);before.setEnd(range.startContainer,range.startOffset);
 const through=document.createRange();through.selectNodeContents(editor);through.setEnd(range.endContainer,range.endOffset);
 return {start:before.toString().length,end:through.toString().length}
}
function textNodePosition(root,targetOffset){
 const walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT),length=Math.max(0,targetOffset);let node,seen=0,last=null;
 while((node=walker.nextNode())){last=node;const next=seen+node.nodeValue.length;if(length<=next)return {node,offset:Math.max(0,length-seen)};seen=next}
 return last?{node:last,offset:last.nodeValue.length}:{node:root,offset:0}
}
function restoreEditorSelection(editor,start,end=start){
 if(!editor)return;const a=textNodePosition(editor,start),b=textNodePosition(editor,end),range=document.createRange(),selectionNow=window.getSelection();
 try{range.setStart(a.node,a.offset);range.setEnd(b.node,b.offset);selectionNow.removeAllRanges();selectionNow.addRange(range)}catch(_){}
 if(textDirectEditSession&&textDirectEditSession.editor===editor){const selection={start,end};textDirectEditSession.selection=selection;textDirectEditSession.formatSelection=start!==end?{...selection}:null}
}
function rememberDirectTextSelection(){
 if(!textDirectEditSession)return null;
 const range=selectionOffsetsInEditor(textDirectEditSession.editor);
 if(range){const selection={start:Math.min(range.start,range.end),end:Math.max(range.start,range.end)};textDirectEditSession.selection=selection;textDirectEditSession.formatSelection=selection.start!==selection.end?{...selection}:null}
 return range||textDirectEditSession.selection||null
}
function saveTextSelectionBeforeToolbar(){
 if(!textDirectEditSession)return null;
 const range=selectionOffsetsInEditor(textDirectEditSession.editor);
 if(range){const selection={start:Math.min(range.start,range.end),end:Math.max(range.start,range.end)};textDirectEditSession.selection=selection;if(selection.start!==selection.end)textDirectEditSession.formatSelection={...selection}}
 return textDirectEditSession.formatSelection||textDirectEditSession.selection||range||null
}
function formattingRange(object){
 const length=textContentFromRuns(normalizeTextRuns(object)).length;
 if(textDirectEditSession&&textDirectEditSession.objectId===object.id){const range=saveTextSelectionBeforeToolbar()||textDirectEditSession.formatSelection||textDirectEditSession.selection||{start:0,end:0};return {start:clamp(range.start,0,length),end:clamp(range.end,0,length),editing:true}}
 return {start:0,end:length,editing:false}
}
function styleSpanFromRun(span,run){
 span.dataset.textRun='1';span.dataset.fontFamily=run.fontFamily;span.dataset.fontSize=String(run.fontSize);span.dataset.color=run.color;span.dataset.bold=run.bold?'1':'0';span.dataset.italic=run.italic?'1':'0';span.dataset.underline=run.underline?'1':'0';
 span.style.fontFamily='"'+String(run.fontFamily).replace(/"/g,'')+'", sans-serif';span.style.fontSize=run.fontSize+'px';span.style.fontWeight=run.bold?'700':'400';span.style.fontStyle=run.italic?'italic':'normal';span.style.textDecoration=run.underline?'underline':'none';span.style.color=run.color
}
function renderDirectTextEditor(object,range=null){
 if(!textDirectEditSession||textDirectEditSession.objectId!==object.id)return;
 const editor=textDirectEditSession.editor,keep=range||saveTextSelectionBeforeToolbar()||textDirectEditSession.selection;editor.replaceChildren();
 normalizeTextRuns(object).forEach(run=>{const span=document.createElement('span');styleSpanFromRun(span,run);span.textContent=run.text;editor.appendChild(span)});
 if(!editor.textContent){const span=document.createElement('span');styleSpanFromRun(span,normalizeTextRun({text:''},object));span.appendChild(document.createElement('br'));editor.appendChild(span)}
 styleDirectTextEditor();if(keep)restoreEditorSelection(editor,keep.start,keep.end)
}
function editorRunsFromDom(editor,object){
 const fallback=textStyleFromInput(normalizeTextRuns(object)[0]||object),runs=[];
 const append=(text,style)=>{if(text==null||text==='')return;runs.push(normalizeTextRun({text:String(text).replace(/\u200b/g,''),...style},fallback))};
 const walk=(node,inherited,isRoot=false)=>{
  if(node.nodeType===Node.TEXT_NODE){append(node.nodeValue,inherited);return}
  if(node.nodeType!==Node.ELEMENT_NODE)return;if(node.tagName==='BR'){append('\n',inherited);return}
  let style={...inherited};if(node.dataset&&node.dataset.textRun)style={fontFamily:node.dataset.fontFamily||style.fontFamily,fontSize:Number(node.dataset.fontSize)||style.fontSize,color:node.dataset.color||style.color,bold:node.dataset.bold==='1',italic:node.dataset.italic==='1',underline:node.dataset.underline==='1'};
  const block=!isRoot&&(node.tagName==='DIV'||node.tagName==='P');if(block&&runs.length&&!textContentFromRuns(runs).endsWith('\n'))append('\n',style);
  Array.from(node.childNodes).forEach(child=>walk(child,style,false))
 };
 walk(editor,fallback,true);return mergeTextRuns(runs.length?runs:[normalizeTextRun({text:''},fallback)])
}
function setInlineTextDisabled(disabled){
 const controls=[ui.textContentInline,ui.textFontInline,ui.textSizeInline,ui.textSizeOpen,ui.textColorInline,ui.textAlignInline,ui.textPaddingInline,ui.textBoldInline,ui.textItalicInline,ui.textUnderlineInline,ui.textLineHeightInline,ui.textLetterSpacingInline,ui.textVerticalAlignInline,ui.textDirectionInline,ui.textIndentInline,ui.textListInline,ui.textStrokeColorInline,ui.textStrokeWidthInline,ui.textBackgroundColorInline,ui.textBackgroundOpacityInline,ui.textBackgroundRadiusInline,ui.textAutoFitInline,ui.textCaseInline,ui.textShadowInline,ui.textStyleInline,ui.textSaveStyle,ui.textDeleteStyle,ui.textConvertCurves];
 controls.forEach(control=>{if(control)control.disabled=!!disabled});[ui.textBoldInline,ui.textItalicInline,ui.textUnderlineInline].forEach(control=>{const label=control&&control.closest('label');if(label){label.tabIndex=disabled?-1:0;label.setAttribute('aria-disabled',String(!!disabled))}});if(ui.textInlineControls)ui.textInlineControls.classList.toggle('is-disabled',!!disabled)
}
function ensureInlineFontOptions(){
 if(!ui.textFontInline||ui.textFontInline.options.length)return;GOOGLE_TEXT_FONTS.forEach(font=>{const option=document.createElement('option');option.value=font.value;option.textContent=font.label;ui.textFontInline.appendChild(option)})
}
function syncInlineTextControls(){
 if(!ui.textInlineControls)return;ensureInlineFontOptions();const object=textInlineTarget();textInlineSyncing=true;
 if(!object){
  setInlineTextDisabled(true);if(ui.textFontInline)ui.textFontInline.value='Roboto';if(ui.textSizeInline)ui.textSizeInline.value='32';if(ui.textColorInline)ui.textColorInline.value='#000000';if(ui.textAlignInline)ui.textAlignInline.value='left';if(ui.textPaddingInline)ui.textPaddingInline.value='8';if(ui.textLineHeightInline)ui.textLineHeightInline.value='1.22';if(ui.textLetterSpacingInline)ui.textLetterSpacingInline.value='0';if(ui.textVerticalAlignInline)ui.textVerticalAlignInline.value='top';if(ui.textDirectionInline)ui.textDirectionInline.value='horizontal';if(ui.textIndentInline)ui.textIndentInline.value='0';if(ui.textListInline)ui.textListInline.value='none';if(ui.textStrokeColorInline)ui.textStrokeColorInline.value='#000000';if(ui.textStrokeWidthInline)ui.textStrokeWidthInline.value='0';if(ui.textBackgroundColorInline)ui.textBackgroundColorInline.value='#ffffff';if(ui.textBackgroundOpacityInline)ui.textBackgroundOpacityInline.value='0';if(ui.textBackgroundRadiusInline)ui.textBackgroundRadiusInline.value='0';if(ui.textAutoFitInline)ui.textAutoFitInline.checked=true;if(ui.textCaseInline)ui.textCaseInline.value='normal';if(ui.textShadowInline)ui.textShadowInline.checked=false;syncTextStyleOptions();
  [ui.textBoldInline,ui.textItalicInline,ui.textUnderlineInline].forEach(control=>{if(control){control.checked=false;control.indeterminate=false}});resetInlineTextHistory();textInlineSyncing=false;return
 }
 setInlineTextDisabled(false);if(textInlineHistoryObjectId!==object.id){textInlineHistoryObjectId=object.id;textInlineHistoryArmed=false}
 const range=formattingRange(object),summary=textRunSelectionStyle(object,range.start,range.end),style=summary.first;
 if(ui.textFontInline){if(!Array.from(ui.textFontInline.options).some(option=>option.value===style.fontFamily)){const option=document.createElement('option');option.value=style.fontFamily;option.textContent=style.fontFamily;ui.textFontInline.appendChild(option)}ui.textFontInline.value=style.fontFamily}
 if(ui.textSizeInline&&document.activeElement!==ui.textSizeInline)ui.textSizeInline.value=String(Math.round(Number(style.fontSize)*100)/100);if(ui.textColorInline)ui.textColorInline.value=normalizeHexColor(style.color||'#000000');if(ui.textAlignInline)ui.textAlignInline.value=textAlignValue(object.textAlign||'left');if(ui.textPaddingInline)ui.textPaddingInline.value=String(clamp(Number(object.textPadding)||0,0,120));if(ui.textLineHeightInline)ui.textLineHeightInline.value=String(Number(object.textLineHeight)||1.22);if(ui.textLetterSpacingInline)ui.textLetterSpacingInline.value=String(Number(object.textLetterSpacing)||0);if(ui.textVerticalAlignInline)ui.textVerticalAlignInline.value=textVerticalAlignValue(object.textVerticalAlign||'top');if(ui.textDirectionInline)ui.textDirectionInline.value=textDirectionValue(object.textDirection||'horizontal');if(ui.textIndentInline)ui.textIndentInline.value=String(Number(object.textIndent)||0);if(ui.textListInline)ui.textListInline.value=textListValue(object.textList||'none');if(ui.textStrokeColorInline)ui.textStrokeColorInline.value=normalizeHexColor(object.textStrokeColor||'#000000');if(ui.textStrokeWidthInline)ui.textStrokeWidthInline.value=String(Number(object.textStrokeWidth)||0);if(ui.textBackgroundColorInline)ui.textBackgroundColorInline.value=normalizeHexColor(object.textBackgroundColor||'#ffffff');if(ui.textBackgroundOpacityInline)ui.textBackgroundOpacityInline.value=String(Math.round((Number(object.textBackgroundOpacity)||0)*100));if(ui.textBackgroundRadiusInline)ui.textBackgroundRadiusInline.value=String(Number(object.textBackgroundRadius)||0);if(ui.textAutoFitInline)ui.textAutoFitInline.checked=object.textAutoFit!==false;if(ui.textCaseInline)ui.textCaseInline.value='normal';if(ui.textShadowInline)ui.textShadowInline.checked=object.textShadow===true;syncTextStyleOptions();
 [[ui.textBoldInline,'bold','mixedBold'],[ui.textItalicInline,'italic','mixedItalic'],[ui.textUnderlineInline,'underline','mixedUnderline']].forEach(([control,key,mixed])=>{if(control){control.checked=style[key]===true;control.indeterminate=summary[mixed]===true;const label=control.closest('label');if(label)label.setAttribute('aria-pressed',String(control.checked))}});textInlineSyncing=false
}
function inlinePropertyPatch(property,value){
 if(property==='fontFamily')return {fontFamily:String(value||'Roboto')};if(property==='fontSize')return {fontSize:clamp(Number(value)||32,MIN_TEXT_FONT_SIZE,400)};if(property==='color')return {color:normalizeHexColor(value||'#000000')};if(property==='bold')return {bold:value===true};if(property==='italic')return {italic:value===true};if(property==='underline')return {underline:value===true};return null
}
function applyInlineTextProperty(property,value,options={}){
 if(textInlineSyncing)return false;const object=textInlineTarget();if(!object)return false;
 saveTextSelectionBeforeToolbar();const preview=options.preview===true,range=formattingRange(object),patch=inlinePropertyPatch(property,value);
 if(patch&&range.editing&&range.start===range.end){if(!preview)setStatus('Selecione uma palavra ou parte do texto para alterar somente esse trecho.');syncInlineTextControls();return false}
 if(!preview&&!textInlineHistoryArmed){pushHistory('Formatar texto');textInlineHistoryArmed=true}
 if(patch){object.textRuns=applyStyleToTextRuns(normalizeTextRuns(object),range.start,range.end,patch);if(property==='fontSize'||property==='fontFamily')object.textAutoFit=true;syncTextDefaultsFromRuns(object)}
 else if(property==='textAlign')object.textAlign=textAlignValue(value);else if(property==='textPadding')object.textPadding=clamp(Number(value)||0,0,120);else if(property==='textLineHeight')object.textLineHeight=clamp(Number(value)||1.22,.8,3);else if(property==='textLetterSpacing')object.textLetterSpacing=clamp(Number(value)||0,-20,100);else if(property==='textVerticalAlign')object.textVerticalAlign=textVerticalAlignValue(value);else if(property==='textDirection')object.textDirection=textDirectionValue(value);else if(property==='textIndent')object.textIndent=clamp(Number(value)||0,0,300);else if(property==='textList')object.textList=textListValue(value);else if(property==='textStrokeColor')object.textStrokeColor=normalizeHexColor(value||'#000000');else if(property==='textStrokeWidth')object.textStrokeWidth=clamp(Number(value)||0,0,20);else if(property==='textBackgroundColor')object.textBackgroundColor=normalizeHexColor(value||'#ffffff');else if(property==='textBackgroundOpacity')object.textBackgroundOpacity=clamp((Number(value)||0)/100,0,1);else if(property==='textBackgroundRadius')object.textBackgroundRadius=clamp(Number(value)||0,0,120);else if(property==='textAutoFit')object.textAutoFit=value===true;else if(property==='textShadow')object.textShadow=value===true;else return false;
 repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});fitTextObjectToContent(object);render();updateSelection();
 if(textDirectEditSession&&textDirectEditSession.objectId===object.id)renderDirectTextEditor(object,range);
 if(!preview)setProjectDirty(true);syncInlineTextControls();return true
}
const TEXT_STYLE_STORAGE_KEY='printway_dtf_text_styles_'+autosaveUserKey;
function readTextStyles(){try{const value=JSON.parse(localStorage.getItem(TEXT_STYLE_STORAGE_KEY)||'{}');return value&&typeof value==='object'&&!Array.isArray(value)?value:{}}catch(_){return {}}}
function writeTextStyles(styles){try{localStorage.setItem(TEXT_STYLE_STORAGE_KEY,JSON.stringify(styles))}catch(_){}
}
function textStyleValues(object){return {textFontFamily:object.textFontFamily||'Roboto',textFontSize:Number(object.textFontSize)||32,textColor:normalizeHexColor(object.textColor||'#000000'),textAlign:textAlignValue(object.textAlign||'left'),textPadding:Number(object.textPadding)||0,textBold:object.textBold===true,textItalic:object.textItalic===true,textUnderline:object.textUnderline===true,textLineHeight:Number(object.textLineHeight)||1.22,textLetterSpacing:Number(object.textLetterSpacing)||0,textVerticalAlign:textVerticalAlignValue(object.textVerticalAlign||'top'),textDirection:textDirectionValue(object.textDirection||'horizontal'),textIndent:Number(object.textIndent)||0,textList:textListValue(object.textList||'none'),textStrokeColor:normalizeHexColor(object.textStrokeColor||'#000000'),textStrokeWidth:Number(object.textStrokeWidth)||0,textBackgroundColor:normalizeHexColor(object.textBackgroundColor||'#ffffff'),textBackgroundOpacity:Number(object.textBackgroundOpacity||0),textBackgroundRadius:Number(object.textBackgroundRadius)||0,textAutoFit:object.textAutoFit!==false,textShadow:object.textShadow===true}}
function syncTextStyleOptions(){const select=ui.textStyleInline;if(!select)return;const current=select.value,styles=readTextStyles();select.replaceChildren(new Option('Escolher estilo',''));Object.keys(styles).sort((a,b)=>a.localeCompare(b,'pt-BR')).forEach(name=>select.appendChild(new Option(name,name)));if(Object.prototype.hasOwnProperty.call(styles,current))select.value=current;else select.value=''}
function applySavedTextStyle(name){const object=textInlineTarget(),style=readTextStyles()[String(name||'')];if(!object||!style)return false;saveTextSelectionBeforeToolbar();pushHistory('Aplicar estilo de texto');Object.assign(object,style);ensureGoogleTextFont(object.textFontFamily);repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});fitTextObjectToContent(object);render();updateSelection();syncInlineTextControls();setProjectDirty(true);setStatus('Estilo de texto aplicado: '+name);return true}
function saveCurrentTextStyle(){const object=textInlineTarget();if(!object){setStatus('Selecione uma caixa de texto para salvar um estilo.');return}const name=String(window.prompt('Nome do estilo de texto:','Meu estilo')||'').trim();if(!name)return;const styles=readTextStyles();styles[name]=textStyleValues(object);writeTextStyles(styles);syncTextStyleOptions();if(ui.textStyleInline)ui.textStyleInline.value=name;setStatus('Estilo de texto salvo: '+name)}
function deleteCurrentTextStyle(){const select=ui.textStyleInline,name=select&&select.value;if(!name){setStatus('Escolha um estilo salvo para excluir.');return}const styles=readTextStyles();delete styles[name];writeTextStyles(styles);syncTextStyleOptions();setStatus('Estilo excluído: '+name)}
function transformTextRunsText(runs,start,end,transform){const from=Math.max(0,Math.min(start,end)),to=Math.max(from,Math.max(start,end));let offset=0,result=[];normalizeTextRuns({textRuns:runs}).forEach(run=>{const next=offset+run.text.length,localStart=Math.max(0,from-offset),localEnd=Math.min(run.text.length,to-offset);if(localStart>=localEnd)result.push({...run});else{if(localStart>0)result.push({...run,text:run.text.slice(0,localStart)});result.push({...run,text:transform(run.text.slice(localStart,localEnd))});if(localEnd<run.text.length)result.push({...run,text:run.text.slice(localEnd)})}offset=next});return mergeTextRuns(result)}
function applyInlineTextCase(mode){const object=textInlineTarget();if(!object)return false;let range=textContentFromRuns(normalizeTextRuns(object)).length?formattingRange(object):{start:0,end:0,editing:false};if(range.editing&&range.start===range.end)range={start:0,end:textContentFromRuns(normalizeTextRuns(object)).length,editing:false};const transform=value=>textCaseValue(mode)==='upper'?value.toUpperCase():textCaseValue(mode)==='lower'?value.toLowerCase():textCaseValue(mode)==='title'?value.toLowerCase().replace(/(^|[\s\n])([a-záàâãéêíóôõúç])/gi,(all,prefix,letter)=>prefix+letter.toUpperCase()):value;if(!textInlineHistoryArmed){pushHistory('Alterar maiúsculas e minúsculas');textInlineHistoryArmed=true}object.textRuns=transformTextRunsText(normalizeTextRuns(object),range.start,range.end,transform);syncTextDefaultsFromRuns(object);repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});fitTextObjectToContent(object);render();updateSelection();if(textDirectEditSession&&textDirectEditSession.objectId===object.id)renderDirectTextEditor(object,range);setProjectDirty(true);return true}
function convertSelectedTextToCurves(){const object=textInlineTarget();if(!object)return false;if(textDirectEditSession)finishDirectTextEdit(true);pushHistory('Converter texto em curvas');object.sourceType='shape-text-curves';object.name=(object.name||'Texto').replace(/^Texto\b/,'Texto em curvas');object.textRuns=null;object.textContent='';render();updateSelection();syncInlineTextControls();setProjectDirty(true);setStatus('Texto convertido em curvas. Ele não será mais editável como texto.');return true}
function restoreTextChoicePreview(){
 const preview=textChoicePreview;if(!preview)return;textChoicePreview=null;const object=state.objects.find(item=>item.id===preview.objectId);if(!object)return;
 Object.assign(object,{...preview.snapshot,textRuns:cloneTextRuns(preview.snapshot.textRuns)});repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});syncTextBoxCanvasSize(object,object.w,object.h);render();updateSelection();
 if(textDirectEditSession&&textDirectEditSession.objectId===object.id)renderDirectTextEditor(object,preview.range);syncInlineTextControls()
}
function previewInlineTextProperty(property,value){
 saveTextSelectionBeforeToolbar();restoreTextChoicePreview();const object=textInlineTarget();if(!object)return;const range=formattingRange(object);if(range.editing&&range.start===range.end)return;
 textChoicePreview={objectId:object.id,snapshot:snapshotTextObjectProps(object),range:{...range}};applyInlineTextProperty(property,value,{preview:true})
}
function closeTextChoiceMenu(restorePreview=true){
 if(restorePreview)restoreTextChoicePreview();if(textChoiceMenu){textChoiceMenu.classList.remove('show');textChoiceMenu.replaceChildren()}if(ui.textSizeOpen)ui.textSizeOpen.setAttribute('aria-expanded','false')
}
function positionTextChoiceMenu(anchor){
 if(!textChoiceMenu||!anchor)return;const rect=anchor.getBoundingClientRect(),width=textChoiceMenu.offsetWidth||190,height=textChoiceMenu.offsetHeight||300;
 textChoiceMenu.style.left=Math.max(6,Math.min(rect.left,window.innerWidth-width-6))+'px';textChoiceMenu.style.top=Math.max(6,Math.min(rect.bottom+4,window.innerHeight-height-6))+'px'
}
function openTextChoiceMenu(kind,anchor){
 if(!textChoiceMenu){textChoiceMenu=document.createElement('div');textChoiceMenu.className='dtf-text-choice-menu';document.body.appendChild(textChoiceMenu)}
 saveTextSelectionBeforeToolbar();closeTextChoiceMenu(true);saveTextSelectionBeforeToolbar();const isFont=kind==='font',object=textInlineTarget();if(!object)return;
 const range=formattingRange(object),current=textRunSelectionStyle(object,range.start,range.end).first;
 textChoiceMenu.className='dtf-text-choice-menu show'+(isFont?' dtf-font-choice-menu':' dtf-text-size-menu');
 if(isFont){
  const currentFont=GOOGLE_TEXT_FONTS.find(font=>font.value===current.fontFamily),hasFavorites=readTextFontFavorites().length>0,initialCategory=hasFavorites?'favorites':(currentFont?currentFont.category:'sans');
  const tabs=document.createElement('nav'),list=document.createElement('div');tabs.className='dtf-font-category-tabs';tabs.setAttribute('aria-label','Categorias de fontes');list.className='dtf-font-choice-list';
  const normalizeFontSearch=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const renderCategory=categoryId=>{
   restoreTextChoicePreview();list.replaceChildren();const baseCategory=TEXT_FONT_CATEGORIES.find(item=>item.id===categoryId)||TEXT_FONT_CATEGORIES[0],category={...baseCategory,fonts:baseCategory.id==='favorites'?readTextFontFavorites():baseCategory.fonts},choices=document.createElement('div');choices.className='dtf-font-choice-options';ensureGoogleTextFontsStyles(category.fonts);Array.from(tabs.children).forEach(tab=>tab.classList.toggle('is-active',tab.dataset.category===category.id));
   const drawChoices=term=>{choices.replaceChildren();const typed=String(term||'').trim(),values=textFontMatches(typed,category),addChoice=(value,hintText='')=>{const button=document.createElement('button'),name=document.createElement('span'),hint=hintText||THEME_FONT_HINTS[value],groupLabel=typed?textFontGroupLabel(value):'';button.type='button';button.className='dtf-font-choice'+(category.id==='themes'?' dtf-theme-font-choice':'');button.dataset.value=value;button.style.fontFamily='"'+value.replace(/"/g,'')+'", sans-serif';name.textContent=value+(groupLabel?' '+groupLabel:'');button.appendChild(name);if(hint){const subtitle=document.createElement('small');subtitle.textContent=hint;button.appendChild(subtitle)}if(value===current.fontFamily)button.classList.add('is-current');button.addEventListener('pointerdown',event=>{event.preventDefault();saveTextSelectionBeforeToolbar()});button.addEventListener('mouseenter',()=>ensureGoogleTextFont(value).then(()=>previewInlineTextProperty('fontFamily',value)));button.addEventListener('mouseleave',restoreTextChoicePreview);button.addEventListener('click',()=>{restoreTextChoicePreview();ensureGoogleTextFont(value);applyInlineTextProperty('fontFamily',value);closeTextChoiceMenu(false);if(ui.textFontInline&&!Array.from(ui.textFontInline.options).some(option=>option.value===value)){const option=document.createElement('option');option.value=value;option.textContent=value;ui.textFontInline.appendChild(option)}if(ui.textFontInline)ui.textFontInline.value=value});choices.appendChild(button)};if(values.length)values.forEach(value=>addChoice(value));else if(!typed){const empty=document.createElement('div');empty.className='dtf-font-theme-empty';empty.textContent=category.id==='favorites'?'Nenhuma favorita ainda. Use a estrela ao lado de uma fonte para adicioná-la.':'Nenhuma fonte nesta categoria.';choices.appendChild(empty)}else{const empty=document.createElement('div');empty.className='dtf-font-theme-empty';empty.textContent='Nenhuma fonte correspondente encontrada. Tente outro nome ou confira as categorias.';choices.appendChild(empty)}decorateTextFontFavorites(choices,()=>drawChoices(typed),typed,category)};
   const search=document.createElement('input');search.type='search';search.className='dtf-font-theme-search';search.placeholder='Buscar ou digitar uma fonte';search.setAttribute('aria-label','Buscar ou digitar fonte');search.addEventListener('input',()=>drawChoices(search.value));list.append(search,choices);drawChoices('');if(category.id==='favorites'&&!readTextFontFavorites().length)window.setTimeout(()=>search.focus(),0)
  };
  TEXT_FONT_CATEGORIES.forEach(category=>{const tab=document.createElement('button'),label=document.createElement('span');tab.type='button';tab.className='dtf-font-category-tab';tab.dataset.category=category.id;tab.setAttribute('aria-label',category.label);label.textContent=category.label;tab.appendChild(label);tab.addEventListener('pointerdown',event=>event.preventDefault());tab.addEventListener('mouseenter',()=>renderCategory(category.id));tab.addEventListener('focus',()=>renderCategory(category.id));tab.addEventListener('click',()=>renderCategory(category.id));tabs.appendChild(tab)});
  textChoiceMenu.append(tabs,list);renderCategory(initialCategory);
 }else{
  TEXT_SIZE_CHOICES.forEach(size=>{const choice={value:size,label:size+' pt'},button=document.createElement('button');button.type='button';button.textContent=choice.label;button.dataset.value=String(choice.value);if(Number(choice.value)===Number(current.fontSize))button.classList.add('is-current');button.addEventListener('pointerdown',event=>{event.preventDefault();saveTextSelectionBeforeToolbar()});button.addEventListener('mouseenter',()=>previewInlineTextProperty('fontSize',choice.value));button.addEventListener('mouseleave',restoreTextChoicePreview);button.addEventListener('click',()=>{restoreTextChoicePreview();applyInlineTextProperty('fontSize',choice.value);closeTextChoiceMenu(false);if(ui.textSizeInline)ui.textSizeInline.value=String(choice.value)});textChoiceMenu.appendChild(button)})
 }
 if(ui.textSizeOpen)ui.textSizeOpen.setAttribute('aria-expanded',String(!isFont));positionTextChoiceMenu(anchor)
}
function applyInlineTextSizeFromField(){
 if(!ui.textSizeInline||ui.textSizeInline.disabled)return false;
 const raw=String(ui.textSizeInline.value||'').trim().replace(',','.');
 if(!raw||raw==='-'||raw==='.')return false;
 const value=Number(raw);if(!Number.isFinite(value))return false;
 const size=clamp(value,MIN_TEXT_FONT_SIZE,400);
 ui.textSizeInline.value=String(Math.round(size*100)/100);
 return applyInlineTextProperty('fontSize',size)
}
function attachInlineTextEvents(){
 ensureInlineFontOptions();
 if(ui.textFontInline){
  ui.textFontInline.addEventListener('pointerdown',event=>{if(ui.textFontInline.disabled)return;event.preventDefault();saveTextSelectionBeforeToolbar();openTextChoiceMenu('font',ui.textFontInline)});
  ui.textFontInline.addEventListener('click',event=>event.preventDefault());ui.textFontInline.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '||event.key==='ArrowDown'){event.preventDefault();openTextChoiceMenu('font',ui.textFontInline)}});ui.textFontInline.addEventListener('change',()=>applyInlineTextProperty('fontFamily',ui.textFontInline.value))
 }
 if(ui.textSizeOpen)ui.textSizeOpen.addEventListener('click',event=>{event.preventDefault();saveTextSelectionBeforeToolbar();openTextChoiceMenu('size',ui.textSizeOpen)});
 if(ui.textSizeInline){
  const queueSizeCommit=()=>{if(textInlineSizeCommitTimer)clearTimeout(textInlineSizeCommitTimer);textInlineSizeCommitTimer=window.setTimeout(()=>{textInlineSizeCommitTimer=0;applyInlineTextSizeFromField()},420)};
  const commitSizeNow=()=>{if(textInlineSizeCommitTimer){clearTimeout(textInlineSizeCommitTimer);textInlineSizeCommitTimer=0}applyInlineTextSizeFromField()};
  ui.textSizeInline.addEventListener('pointerdown',saveTextSelectionBeforeToolbar);ui.textSizeInline.addEventListener('focus',saveTextSelectionBeforeToolbar);
  ui.textSizeInline.addEventListener('input',queueSizeCommit);ui.textSizeInline.addEventListener('change',commitSizeNow);
  ui.textSizeInline.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();commitSizeNow();ui.textSizeInline.select()}});
  ui.textSizeInline.addEventListener('blur',commitSizeNow)
 }
 if(ui.textColorInline){ui.textColorInline.addEventListener('pointerdown',saveTextSelectionBeforeToolbar);ui.textColorInline.addEventListener('input',()=>applyInlineTextProperty('color',ui.textColorInline.value))}
 if(ui.textAlignInline)ui.textAlignInline.addEventListener('change',()=>applyInlineTextProperty('textAlign',ui.textAlignInline.value));if(ui.textPaddingInline)ui.textPaddingInline.addEventListener('change',()=>applyInlineTextProperty('textPadding',ui.textPaddingInline.value));
 [[ui.textBoldInline,'bold'],[ui.textItalicInline,'italic'],[ui.textUnderlineInline,'underline']].forEach(([control,property])=>{if(!control)return;const label=control.closest('label');if(!label){control.addEventListener('pointerdown',saveTextSelectionBeforeToolbar);control.addEventListener('change',()=>applyInlineTextProperty(property,control.checked));return}label.setAttribute('role','button');label.setAttribute('aria-pressed',String(control.checked));label.addEventListener('pointerdown',event=>{if(control.disabled)return;event.preventDefault();saveTextSelectionBeforeToolbar()});label.addEventListener('click',event=>{if(control.disabled)return;event.preventDefault();saveTextSelectionBeforeToolbar();control.indeterminate=false;control.checked=!control.checked;label.setAttribute('aria-pressed',String(control.checked));applyInlineTextProperty(property,control.checked)});label.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();label.click()}})});
 [[ui.textLineHeightInline,'textLineHeight'],[ui.textLetterSpacingInline,'textLetterSpacing'],[ui.textVerticalAlignInline,'textVerticalAlign'],[ui.textDirectionInline,'textDirection'],[ui.textIndentInline,'textIndent'],[ui.textListInline,'textList'],[ui.textStrokeColorInline,'textStrokeColor'],[ui.textStrokeWidthInline,'textStrokeWidth'],[ui.textBackgroundColorInline,'textBackgroundColor'],[ui.textBackgroundOpacityInline,'textBackgroundOpacity'],[ui.textBackgroundRadiusInline,'textBackgroundRadius'],[ui.textAutoFitInline,'textAutoFit'],[ui.textShadowInline,'textShadow']].forEach(([control,property])=>{if(!control)return;control.addEventListener('pointerdown',saveTextSelectionBeforeToolbar);control.addEventListener('change',()=>applyInlineTextProperty(property,control.type==='checkbox'?control.checked:control.value))});
 if(ui.textCaseInline)ui.textCaseInline.addEventListener('change',()=>{if(ui.textCaseInline.value!=='normal')applyInlineTextCase(ui.textCaseInline.value);ui.textCaseInline.value='normal'});
 if(ui.textStyleInline){syncTextStyleOptions();ui.textStyleInline.addEventListener('change',()=>{if(ui.textStyleInline.value)applySavedTextStyle(ui.textStyleInline.value)})}
 if(ui.textSaveStyle)ui.textSaveStyle.addEventListener('click',saveCurrentTextStyle);if(ui.textDeleteStyle)ui.textDeleteStyle.addEventListener('click',deleteCurrentTextStyle);if(ui.textConvertCurves)ui.textConvertCurves.addEventListener('click',convertSelectedTextToCurves);
 if(ui.textInlineControls)ui.textInlineControls.addEventListener('focusout',()=>window.setTimeout(()=>{if(!ui.textInlineControls.contains(document.activeElement)&&!(textChoiceMenu&&textChoiceMenu.classList.contains('show')))textInlineHistoryArmed=false},0));
 document.addEventListener('selectionchange',()=>{if(!textDirectEditSession)return;const range=saveTextSelectionBeforeToolbar();if(range)syncInlineTextControls()});
 document.addEventListener('pointerdown',event=>{if(textDirectEditSession&&ui.textInlineControls&&ui.textInlineControls.contains(event.target))saveTextSelectionBeforeToolbar();if(textChoiceMenu&&textChoiceMenu.classList.contains('show')&&!textChoiceMenu.contains(event.target)&&event.target!==ui.textFontInline&&event.target!==ui.textSizeOpen)closeTextChoiceMenu(true)},{capture:true});
 window.addEventListener('resize',()=>{if(textChoiceMenu&&textChoiceMenu.classList.contains('show'))closeTextChoiceMenu(true)})
}
function styleDirectTextEditor(){
 if(!textDirectEditSession)return;const object=state.objects.find(item=>item.id===textDirectEditSession.objectId),editor=textDirectEditSession.editor;if(!object||!editor)return;
 const tx=directTextTransform(object),first=normalizeTextRuns(object)[0]||normalizeTextRun({text:''},object);
 editor.style.left=object.x+'px';editor.style.top=object.y+'px';editor.style.width=object.w+'px';editor.style.height=object.h+'px';editor.style.fontFamily='"'+String(first.fontFamily||'Roboto').replace(/"/g,'')+'", sans-serif';editor.style.fontSize=first.fontSize+'px';editor.style.fontWeight=first.bold?'700':'400';editor.style.fontStyle=first.italic?'italic':'normal';editor.style.textDecoration=first.underline?'underline':'none';editor.style.color=first.color;editor.style.textAlign=textAlignValue(object.textAlign||'left');editor.style.padding=clamp(Number(object.textPadding)||0,0,120)+'px';editor.style.transform=tx.transform;editor.style.transformOrigin=tx.origin
}
function finishDirectTextEdit(commit=true){
 const session=textDirectEditSession;if(!session)return;const object=state.objects.find(item=>item.id===session.objectId);textDirectEditSession=null;closeTextChoiceMenu(true);
 if(object&&!commit&&session.original){Object.assign(object,{...session.original,textRuns:cloneTextRuns(session.original.textRuns)});repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h})}
 if(session.editor&&session.editor.isConnected)session.editor.remove();if(selection)selection.classList.remove('dtf-text-direct-editing');
 if(object){syncTextBoxCanvasSize(object,object.w,object.h);render();updateSelection();syncInlineTextControls();setProjectDirty(true)}
}
function insertPlainTextAtSelection(editor,text){
 editor.focus();if(document.queryCommandSupported&&document.queryCommandSupported('insertText')){document.execCommand('insertText',false,text);return}
 const selectionNow=window.getSelection();if(!selectionNow||!selectionNow.rangeCount)return;const range=selectionNow.getRangeAt(0);range.deleteContents();const node=document.createTextNode(text);range.insertNode(node);range.setStartAfter(node);range.collapse(true);selectionNow.removeAllRanges();selectionNow.addRange(range);editor.dispatchEvent(new Event('input',{bubbles:true}))
}
function startDirectTextEdit(targetObject=null,selectAll=false){
 const object=targetObject&&isTextShape(targetObject)?targetObject:(isTextShape(selected())?selected():null);if(!object||object.locked)return false;
 if(textDirectEditSession&&textDirectEditSession.objectId===object.id){const current=textDirectEditSession.editor;styleDirectTextEditor();current.focus();if(selectAll)restoreEditorSelection(current,0,textContentFromRuns(normalizeTextRuns(object)).length);return true}
 if(textDirectEditSession)finishDirectTextEdit(true);if(selected()!==object)selectObject(object,false);if(typeof showEditorTab==='function'&&activeTab!=='desenhar')showEditorTab('desenhar');
 deactivateDrawTool(true);state.tool='select';setCursorSelectButtonActive(true);
 const editor=document.createElement('div');editor.className='dtf-direct-text-editor';editor.contentEditable='true';editor.spellcheck=true;editor.setAttribute('role','textbox');editor.setAttribute('aria-multiline','true');editor.setAttribute('aria-label','Editar texto diretamente no objeto');
 inner.appendChild(editor);textDirectEditSession={objectId:object.id,editor,original:snapshotTextObjectProps(object),historySaved:false,selection:{start:0,end:0},formatSelection:null};renderDirectTextEditor(object);if(selection)selection.classList.add('dtf-text-direct-editing');render();updateSelection();
 editor.addEventListener('pointerdown',event=>event.stopPropagation());editor.addEventListener('dblclick',event=>event.stopPropagation());
 editor.addEventListener('input',()=>{const session=textDirectEditSession;if(!session||session.objectId!==object.id)return;const caret=selectionOffsetsInEditor(editor)||session.selection;if(!session.historySaved){pushHistory('Editar texto');session.historySaved=true}object.textRuns=editorRunsFromDom(editor,object);syncTextDefaultsFromRuns(object);repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});fitTextObjectToContent(object);session.selection=caret;session.formatSelection=caret&&caret.start!==caret.end?{...caret}:null;styleDirectTextEditor();render();updateSelection();syncInlineTextControls();setProjectDirty(true)});
 editor.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();event.stopPropagation();finishDirectTextEdit(true);return}if((event.ctrlKey||event.metaKey)&&event.key==='Enter'){event.preventDefault();finishDirectTextEdit(true);return}if(event.key==='Enter'){event.preventDefault();insertPlainTextAtSelection(editor,'\n')}});
 editor.addEventListener('paste',event=>{event.preventDefault();insertPlainTextAtSelection(editor,event.clipboardData?event.clipboardData.getData('text/plain'):'')});
 editor.addEventListener('keyup',()=>{rememberDirectTextSelection();syncInlineTextControls()});editor.addEventListener('pointerup',()=>{rememberDirectTextSelection();syncInlineTextControls()});
 editor.addEventListener('blur',()=>window.setTimeout(()=>{if(!textDirectEditSession||textDirectEditSession.editor!==editor)return;if(ui.textInlineControls&&ui.textInlineControls.contains(document.activeElement))return;if(textChoiceMenu&&textChoiceMenu.classList.contains('show'))return;if(mainColorPalette&&mainColorPalette.contains(document.activeElement))return;if(mainColorButton&&mainColorButton.contains(document.activeElement))return;finishDirectTextEdit(true)},0));
 requestAnimationFrame(()=>{editor.focus();const length=textContentFromRuns(normalizeTextRuns(object)).length;restoreEditorSelection(editor,selectAll?0:length,length)});
 setStatus('Editando o texto no objeto. Selecione uma palavra ou trecho para formatar somente essa parte.');return true
}
function focusInlineTextControls(selectAll=false){syncInlineTextControls();const object=textInlineTarget();if(object)startDirectTextEdit(object,selectAll)}
function openTextEditorModal(targetObject=null){const object=targetObject&&isTextShape(targetObject)?targetObject:(isTextShape(selected())?selected():null);if(!object){setStatus('Selecione uma caixa de texto para editar.');return}startDirectTextEdit(object,true)}
attachInlineTextEvents();

const projectSnapshotWithoutRichText=projectSnapshot;
projectSnapshot=function(){
 const snapshot=projectSnapshotWithoutRichText();
 snapshot.objects.forEach((item,index)=>{const source=state.objects[index];if(!source||!isTextShape(source))return;item.textRuns=cloneTextRuns(normalizeTextRuns(source));item.textUnderline=source.textUnderline===true;item.textAutoFit=source.textAutoFit!==false;Object.assign(item,textStyleValues(source),{textContent:source.textContent||''})});
 return snapshot
};
const projectDataWithoutRichText=projectData;
projectData=function(){
 const data=projectDataWithoutRichText();
 data.objects.forEach((item,index)=>{const source=state.objects[index];if(!source||!isTextShape(source))return;item.textRuns=cloneTextRuns(normalizeTextRuns(source));item.textUnderline=source.textUnderline===true;item.textAutoFit=source.textAutoFit!==false;Object.assign(item,textStyleValues(source),{textContent:source.textContent||''})});
 return data
};
const autosaveSignatureWithoutRichText=autosaveSignature;
autosaveSignature=function(){const rich=(state.objects||[]).filter(isTextShape).map(object=>[object.id,object.textUnderline===true,cloneTextRuns(normalizeTextRuns(object)),textStyleValues(object)]);return autosaveSignatureWithoutRichText()+'|'+JSON.stringify(rich)};
const openEditorProjectWithoutRichText=openEditorProject;
openEditorProject=async function(file,handle=null){
 let richById=new Map();
 try{const raw=await file.text(),parsed=JSON.parse(raw);(parsed&&Array.isArray(parsed.objects)?parsed.objects:[]).forEach(item=>richById.set(String(item.id||''),{textRuns:cloneTextRuns(item.textRuns||[]),textUnderline:item.textUnderline===true,textAutoFit:item.textAutoFit!==false}))}catch(_){}
 const opened=await openEditorProjectWithoutRichText(file,handle);if(!opened)return false;
 state.objects.forEach(object=>{if(!isTextShape(object))return;const rich=richById.get(String(object.id));object.textRuns=rich&&rich.textRuns.length?cloneTextRuns(rich.textRuns):normalizeTextRuns(object);object.textUnderline=rich?rich.textUnderline:object.textUnderline===true;object.textAutoFit=rich?rich.textAutoFit:object.textAutoFit!==false;syncTextDefaultsFromRuns(object);repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});syncTextBoxCanvasSize(object,object.w,object.h)});
 render();updateSelection();syncInlineTextControls();return true
};
const projectDataWithLineNodes=projectData;
projectData=function(){const data=projectDataWithLineNodes();(data.objects||[]).forEach((item,index)=>{const object=state.objects[index];if(object&&Array.isArray(object.lineNodes)){item.lineNodes=object.lineNodes.map(point=>({x:Number(point.x)||0,y:Number(point.y)||0}));item.lineClosed=object.lineClosed===true}});return data};
const openEditorProjectWithLineNodes=openEditorProject;
openEditorProject=async function(file,handle=null){let lineDataById=new Map();try{const raw=await file.text(),parsed=JSON.parse(raw);(parsed&&Array.isArray(parsed.objects)?parsed.objects:[]).forEach(item=>lineDataById.set(String(item.id||''),{nodes:Array.isArray(item.lineNodes)?item.lineNodes.map(point=>({x:Number(point.x)||0,y:Number(point.y)||0})):null,closed:item.lineClosed===true}))}catch(_){}const opened=await openEditorProjectWithLineNodes(file,handle);if(opened){normalizeObjectNames();state.objects.forEach(object=>{const data=lineDataById.get(String(object.id));if(isLineShape(object)&&data&&data.nodes&&data.nodes.length>1){object.lineNodes=data.nodes;object.lineClosed=data.closed;repaintLineObject(object)}})}if(opened){render();updateSelection()}return opened};
const openEditorProjectWithTextFeatures=openEditorProject;
openEditorProject=async function(file,handle=null){let featureById=new Map();try{const raw=await file.text(),parsed=JSON.parse(raw);(parsed&&Array.isArray(parsed.objects)?parsed.objects:[]).forEach(item=>featureById.set(String(item.id||''),item))}catch(_){}const opened=await openEditorProjectWithTextFeatures(file,handle);if(opened){state.objects.forEach(object=>{if(!isTextShape(object))return;const item=featureById.get(String(object.id));if(!item)return;Object.assign(object,{textLineHeight:Number(item.textLineHeight)||1.22,textLetterSpacing:Number(item.textLetterSpacing)||0,textVerticalAlign:textVerticalAlignValue(item.textVerticalAlign||'top'),textDirection:textDirectionValue(item.textDirection||'horizontal'),textIndent:Number(item.textIndent)||0,textList:textListValue(item.textList||'none'),textListStart:Math.max(1,Number(item.textListStart)||1),textStrokeColor:normalizeHexColor(item.textStrokeColor||'#000000'),textStrokeWidth:Number(item.textStrokeWidth)||0,textBackgroundColor:normalizeHexColor(item.textBackgroundColor||'#ffffff'),textBackgroundOpacity:Number(item.textBackgroundOpacity||0),textBackgroundRadius:Number(item.textBackgroundRadius)||0,textShadow:item.textShadow===true,textShadowColor:normalizeHexColor(item.textShadowColor||'#000000'),textShadowBlur:Number(item.textShadowBlur)||6,textShadowX:Number(item.textShadowX)||3,textShadowY:Number(item.textShadowY)||3,textAutoFit:item.textAutoFit!==false});repaintTextObject(object,{textRuns:object.textRuns,originalW:object.w,originalH:object.h});fitTextObjectToContent(object)});render();updateSelection();syncInlineTextControls()}return opened};
const openEditorProjectWithAllTextState=openEditorProject;
openEditorProject=async function(file,handle=null){const result=await openEditorProjectWithAllTextState(file,handle);if(result)syncMainColorScope(currentProjectName||'novo');return result};
const newEditorProjectWithTextState=newEditorProject;
newEditorProject=async function(){const result=await newEditorProjectWithTextState();if(result)syncMainColorScope('novo');return result};
if(typeof inheritCopy!=='undefined'&&typeof inheritPaste!=='undefined'){inheritCopy.onclick=()=>{const source=selected();if(!source)return;propertyClipboard={w:source.w,h:source.h,opacity:source.opacity,locked:source.locked,text:isTextShape(source)?textStyleValues(source):null};inheritPaste.disabled=false;canvasMenu.classList.remove('show');setStatus('Herança copiada. Selecione outro objeto para colar.')};inheritPaste.onclick=()=>{const target=selected();if(!target||!propertyClipboard)return;pushHistory('Colar herança');target.w=Math.min(canvas.width,propertyClipboard.w);target.h=Math.min(canvas.height,propertyClipboard.h);target.opacity=propertyClipboard.opacity;target.locked=propertyClipboard.locked;if(isTextShape(target)&&propertyClipboard.text){Object.assign(target,propertyClipboard.text);repaintTextObject(target,{textRuns:target.textRuns,originalW:target.w,originalH:target.h});fitTextObjectToContent(target)}render();updateSelection();canvasMenu.classList.remove('show');setStatus('Herança aplicada')};}
function deactivateDrawTool(silent){
 if(state.tool==='drawRect'||state.tool==='drawText'||state.tool==='drawLine')state.tool='select';
 cancelLineDraft();
 editorRoot.classList.remove('dtf-draw-rect-cursor','dtf-draw-text-cursor','dtf-draw-line-cursor');
 Object.values(drawShapeButtons).forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});
 if(drawMoreShapesButton){drawMoreShapesButton.classList.remove('active');drawMoreShapesButton.setAttribute('aria-pressed','false')}closeDrawShapePicker();
 if(drawTextButton){drawTextButton.classList.remove('active');drawTextButton.setAttribute('aria-pressed','false')}
 if(drawLineButton){drawLineButton.classList.remove('active');drawLineButton.setAttribute('aria-pressed','false')}
 drawRectDrag=null;drawTextDrag=null;drawRectPreview.classList.remove('show');hideDrawRectMeasure();
 if(!silent)setStatus('Desenho desativado');
}
function drawShapeLabel(type){return drawShapeNames[type]||'Forma'}
function activateDrawRectTool(shapeType='rectangle'){
 setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();
 activeDrawShape=Object.prototype.hasOwnProperty.call(drawShapeNames,shapeType)?shapeType:'rectangle';state.tool='drawRect';
 Object.entries(drawShapeButtons).forEach(([type,button])=>{if(button){const active=type===activeDrawShape;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active))}});
 if(drawMoreShapesButton){const active=moreDrawShapes.includes(activeDrawShape);drawMoreShapesButton.classList.toggle('active',active);drawMoreShapesButton.setAttribute('aria-pressed',String(active))}closeDrawShapePicker();
 if(drawTextButton){drawTextButton.classList.remove('active');drawTextButton.setAttribute('aria-pressed','false')}
 if(drawLineButton){drawLineButton.classList.remove('active');drawLineButton.setAttribute('aria-pressed','false')}
 editorRoot.classList.add('dtf-draw-rect-cursor');
 editorRoot.classList.remove('dtf-draw-text-cursor','dtf-draw-line-cursor');
 showToolHint('Clique e arraste. CTRL mantém proporção.');
 setStatus('Ferramenta '+drawShapeLabel(activeDrawShape)+' ativa');
}
function activateDrawTextTool(){
 setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();
 state.tool='drawText';
 Object.values(drawShapeButtons).forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});if(drawMoreShapesButton){drawMoreShapesButton.classList.remove('active');drawMoreShapesButton.setAttribute('aria-pressed','false')}closeDrawShapePicker();
 if(drawTextButton){drawTextButton.classList.add('active');drawTextButton.setAttribute('aria-pressed','true')}
 if(drawLineButton){drawLineButton.classList.remove('active');drawLineButton.setAttribute('aria-pressed','false')}
 editorRoot.classList.add('dtf-draw-text-cursor');
 editorRoot.classList.remove('dtf-draw-rect-cursor','dtf-draw-line-cursor');
 showToolHint('Clique para inserir rápido ou arraste para definir a caixa de texto.');
 setStatus('Ferramenta Texto ativa');
}
function drawRectFromPoints(start,current,locked){
 let dx=current.x-start.x,dy=current.y-start.y,x,y,w,h;
 if(locked){
  const side=Math.max(Math.abs(dx),Math.abs(dy));
  x=dx<0?start.x-side:start.x;y=dy<0?start.y-side:start.y;w=side;h=side;
 }else{
  x=Math.min(start.x,current.x);y=Math.min(start.y,current.y);w=Math.abs(dx);h=Math.abs(dy);
 }
 if(x<0){w+=x;x=0}if(y<0){h+=y;y=0}
 if(x+w>canvas.width)w=canvas.width-x;if(y+h>canvas.height)h=canvas.height-y;
 return {x:Math.max(0,x),y:Math.max(0,y),w:Math.max(0,w),h:Math.max(0,h)};
}
function hideDrawRectMeasure(){
 if(typeof drawRectMeasureLabel!=='undefined')drawRectMeasureLabel.classList.remove('show');
}
function updateDrawRectPreview(rect,shapeType=activeDrawShape){
 if(!rect||rect.w<1||rect.h<1){drawRectPreview.classList.remove('show','dtf-shape-preview-rendered');drawRectPreview.removeAttribute('data-measure');drawRectPreview.style.clipPath='none';drawRectPreview.style.backgroundImage='none';hideDrawRectMeasure();return}
 const color=currentPaletteHex(),wmm=pxToMm(rect.w),hmm=pxToMm(rect.h),measureText=Number(wmm.toFixed(1))+' × '+Number(hmm.toFixed(1))+' mm';
 drawRectPreview.style.left=rect.x+'px';drawRectPreview.style.top=rect.y+'px';drawRectPreview.style.width=rect.w+'px';drawRectPreview.style.height=rect.h+'px';
 const isTextPreview=shapeType==='text';
 if(isTextPreview){drawRectPreview.classList.remove('dtf-shape-preview-rendered');drawRectPreview.style.backgroundImage='none';drawRectPreview.style.backgroundColor=color+'55';drawRectPreview.style.clipPath='none'}
 else{const strokeWidth=Math.max(2,Math.round(Math.min(rect.w,rect.h)*.02)),previewCanvas=shapeCanvas(shapeType,rect.w,rect.h,color,'#000000',strokeWidth,{tl:0,tr:0,br:0,bl:0});drawRectPreview.classList.add('dtf-shape-preview-rendered');drawRectPreview.style.backgroundColor='transparent';drawRectPreview.style.backgroundImage='url("'+previewCanvas.toDataURL('image/png')+'")';drawRectPreview.style.clipPath='none'}
 drawRectPreview.setAttribute('data-measure',measureText);
 drawRectPreview.classList.add('show');
 if(typeof drawRectMeasureLabel!=='undefined'){
  const r=canvas.getBoundingClientRect(),scaleX=r.width/Math.max(1,canvas.width),scaleY=r.height/Math.max(1,canvas.height);
  let left=r.left+rect.x*scaleX,top=r.top+rect.y*scaleY-34;
  drawRectMeasureLabel.textContent=measureText;
  drawRectMeasureLabel.classList.add('show');
  const box=drawRectMeasureLabel.getBoundingClientRect();
  left=clamp(left,8,Math.max(8,window.innerWidth-box.width-8));
  if(top<8)top=Math.min(window.innerHeight-box.height-8,r.top+(rect.y+rect.h)*scaleY+8);
  drawRectMeasureLabel.style.left=left+'px';drawRectMeasureLabel.style.top=top+'px';
 }
}
function rectangleCanvas(width,height,fill,stroke,strokeWidth=null,cornerRadii=null){
 const c=document.createElement('canvas'),w=Math.max(1,Math.round(width)),h=Math.max(1,Math.round(height));
 c.width=w;c.height=h;
 const cx=c.getContext('2d');
 const fillValue=shapeColorValue(fill,'#8c3f00'),strokeValue=shapeColorValue(stroke,'#000000'),hasLineWidth=strokeWidth!=null&&strokeWidth!=='',rawLineWidth=Number(strokeWidth),lineWidth=hasLineWidth&&Number.isFinite(rawLineWidth)?clamp(rawLineWidth,0,Math.max(.5,Math.min(w,h)/2)):Math.max(2,Math.round(Math.min(w,h)*0.02)),radii=normalizeShapeCornerRadii(cornerRadii,w,h);
 if(fillValue!=='none'){cx.fillStyle=fillValue;roundedRectPath(cx,0,0,w,h,radii);cx.fill()}
 if(strokeValue!=='none'&&lineWidth>0){
  const inset=lineWidth/2,sw=Math.max(.01,w-lineWidth),sh=Math.max(.01,h-lineWidth),strokeRadii={tl:Math.max(0,radii.tl-inset),tr:Math.max(0,radii.tr-inset),br:Math.max(0,radii.br-inset),bl:Math.max(0,radii.bl-inset)};
  cx.strokeStyle=strokeValue;cx.lineWidth=lineWidth;roundedRectPath(cx,inset,inset,sw,sh,strokeRadii);cx.stroke()
 }
 return c;
}
function normalizedPolygonPoints(type){
 const points={
  triangle:[[.5,0],[1,1],[0,1]],
  pentagon:[[.5,0],[1,.38],[.81,1],[.19,1],[0,.38]],
  star:[[.5,0],[.61,.35],[.98,.35],[.68,.57],[.79,.94],[.5,.72],[.21,.94],[.32,.57],[.02,.35],[.39,.35]],
  diamond:[[.5,0],[1,.5],[.5,1],[0,.5]],
  arrow:[[0,.3],[.55,.3],[.55,0],[1,.5],[.55,1],[.55,.7],[0,.7]],
  speech:[[0,0],[1,0],[1,.78],[.5,.78],[.33,1],[.33,.78],[0,.78]]
 };
 return points[type]||points.pentagon
}
function polygonShapeCanvas(type,width,height,fill,stroke,strokeWidth=null){
 const c=document.createElement('canvas'),w=Math.max(1,Math.round(width)),h=Math.max(1,Math.round(height));c.width=w;c.height=h;
 const cx=c.getContext('2d'),fillValue=shapeColorValue(fill,'#8c3f00'),strokeValue=shapeColorValue(stroke,'#000000'),rawWidth=Number(strokeWidth),lineWidth=Number.isFinite(rawWidth)?clamp(rawWidth,0,Math.max(.5,Math.min(w,h)/2)):Math.max(2,Math.round(Math.min(w,h)*.02)),inset=strokeValue!=='none'&&lineWidth>0?lineWidth/2:0,cx0=w/2,cy0=h/2,rx=Math.max(.5,w/2-inset),ry=Math.max(.5,h/2-inset);
 cx.beginPath();
 if(type==='circle')cx.ellipse(cx0,cy0,rx,ry,0,0,Math.PI*2);
 else if(type==='heart'){cx.moveTo(cx0,cy0+ry);cx.bezierCurveTo(cx0-rx*1.2,cy0+ry*.3,cx0-rx,cy0-ry*.45,cx0-rx*.48,cy0-ry*.48);cx.bezierCurveTo(cx0-rx*.16,cy0-ry*.48,cx0,cy0-ry*.12,cx0,cy0+ry*.02);cx.bezierCurveTo(cx0,cy0-ry*.12,cx0+rx*.16,cy0-ry*.48,cx0+rx*.48,cy0-ry*.48);cx.bezierCurveTo(cx0+rx,cy0-ry*.45,cx0+rx*1.2,cy0+ry*.3,cx0,cy0+ry);cx.closePath()}
 else{
  normalizedPolygonPoints(type).forEach(([x,y],index)=>{const px=inset+x*(w-inset*2),py=inset+y*(h-inset*2);if(index)cx.lineTo(px,py);else cx.moveTo(px,py)});
  cx.closePath();
 }
 if(fillValue!=='none'){cx.fillStyle=fillValue;cx.fill()}
 if(strokeValue!=='none'&&lineWidth>0){cx.strokeStyle=strokeValue;cx.lineWidth=lineWidth;cx.stroke()}
 return c
}
function shapeCanvas(type,width,height,fill,stroke,strokeWidth=null,cornerRadii=null){return type==='rectangle'?rectangleCanvas(width,height,fill,stroke,strokeWidth,cornerRadii):polygonShapeCanvas(type,width,height,fill,stroke,strokeWidth)}
function repaintPolygonShapeColor(object,fillHex,strokeHex){
 const type=String(object&&object.sourceType||'').replace(/^shape-/,'');if(!['circle','triangle','pentagon','star','diamond','heart','arrow','speech'].includes(type))return false;
 const source=object.baseCanvas||object.canvas||object.restoreCanvas,w=(source&&source.width)||Math.max(1,Math.round(object.originalW||object.w||1)),h=(source&&source.height)||Math.max(1,Math.round(object.originalH||object.h||1)),fill=shapeColorValue(fillHex!=null?fillHex:object.shapeFillColor,'#8c3f00'),stroke=shapeColorValue(strokeHex!=null?strokeHex:object.shapeStrokeColor,'#000000'),lineWidth=effectiveShapeStrokeWidth(object),fresh=shapeCanvas(type,w,h,fill,stroke,lineWidth);
 object.canvas=fresh;object.baseCanvas=canvasFromData(cloneCanvasData(fresh));object.restoreCanvas=canvasFromData(cloneCanvasData(fresh));object.shapeFillColor=fill;object.shapeStrokeColor=stroke;object.shapeStrokeWidth=lineWidth;object.originalW=fresh.width;object.originalH=fresh.height;return true
}
function finishDrawRectDrag(){
 if(!drawRectDrag)return;
 const type=drawRectDrag.shapeType||activeDrawShape,rect=drawRectFromPoints(drawRectDrag.start,drawRectDrag.current,drawRectDrag.locked);
 drawRectPreview.classList.remove('show');drawRectPreview.removeAttribute('data-measure');hideDrawRectMeasure();
 drawRectDrag=null;
 if(rect.w<4||rect.h<4){setStatus('Forma muito pequena. Arraste uma área maior.');return}
 const fill=currentPaletteHex(),shapeStrokeWidth=Math.max(2,Math.round(Math.min(rect.w,rect.h)*0.02)),shapeCornerRadii={tl:0,tr:0,br:0,bl:0},createdCanvas=shapeCanvas(type,rect.w,rect.h,fill,'#000000',shapeStrokeWidth,shapeCornerRadii),base=canvasFromData(cloneCanvasData(createdCanvas)),names={rectangle:Math.abs(rect.w-rect.h)<1?'Quadrado':'Retângulo',circle:Math.abs(rect.w-rect.h)<1?'Círculo':'Elipse',triangle:'Triângulo',pentagon:'Pentágono',star:'Estrela',diamond:'Losango',heart:'Coração',arrow:'Seta',speech:'Balão de fala'},name=names[type]||'Forma';
 pushHistory('Inserir '+name.toLowerCase());
 const n=state.objects.filter(o=>String(o.sourceType||'')==='shape-'+type).length+1;
 const object={id:uid(),name:name+' '+n,x:rect.x,y:rect.y,w:rect.w,h:rect.h,visible:true,opacity:1,locked:false,groupId:null,sourceType:'shape-'+type,shapeFillColor:fill,shapeStrokeColor:'#000000',shapeStrokeWidth,shapeCornerRadii,rotation:0,skewX:0,skewY:0,originalW:createdCanvas.width,originalH:createdCanvas.height,canvas:createdCanvas,baseCanvas:base,restoreCanvas:canvasFromData(cloneCanvasData(createdCanvas)),aiOriginalCanvas:null};
 state.objects.push(object);normalizeObjectNames();
 selectObject(object,false);
 render();
 setStatus(object.name+' inserido com borda preta e preenchimento da paleta');
}
let drawTextDrag=null;

function finishDrawTextDrag(){
 if(!drawTextDrag)return;
 const start=drawTextDrag.start,current=drawTextDrag.current;
 let rect=drawRectFromPoints(start,current,false);
 drawRectPreview.classList.remove('show');drawRectPreview.removeAttribute('data-measure');hideDrawRectMeasure();
 drawTextDrag=null;
 const moved=Math.abs(current.x-start.x)>=4||Math.abs(current.y-start.y)>=4;
 if(!moved||rect.w<24||rect.h<24){
  const defaultW=Math.min(220,canvas.width),defaultH=Math.min(80,canvas.height);
  rect={x:clamp(start.x,0,Math.max(0,canvas.width-defaultW)),y:clamp(start.y,0,Math.max(0,canvas.height-defaultH)),w:defaultW,h:defaultH};
 }
 pushHistory('Inserir caixa de texto');
 const count=state.objects.filter(o=>String(o.sourceType||'')==='shape-text').length+1;
 const object={id:uid(),name:'Texto '+count,x:rect.x,y:rect.y,w:rect.w,h:rect.h,visible:true,opacity:1,locked:false,groupId:null,sourceType:'shape-text',rotation:0,skewX:0,skewY:0,textContent:'Digite aqui',textFontFamily:'Roboto',textFontSize:32,textColor:currentPaletteHex(),textAlign:'left',textBold:false,textItalic:false,textUnderline:false,textRuns:[{text:'Digite aqui',fontFamily:'Roboto',fontSize:32,color:currentPaletteHex(),bold:false,italic:false,underline:false}],textPadding:8,textAutoFit:!moved,originalW:Math.max(24,Math.round(rect.w)),originalH:Math.max(24,Math.round(rect.h)),shapeFillColor:null,shapeStrokeColor:null,shapeStrokeWidth:null,shapeCornerRadii:{tl:0,tr:0,br:0,bl:0},canvas:document.createElement('canvas'),baseCanvas:document.createElement('canvas'),restoreCanvas:document.createElement('canvas'),aiOriginalCanvas:null};
 Object.assign(object,{textLineHeight:1.22,textLetterSpacing:0,textVerticalAlign:'top',textDirection:'horizontal',textIndent:0,textList:'none',textListStart:1,textStrokeColor:'#000000',textStrokeWidth:0,textBackgroundColor:'#ffffff',textBackgroundOpacity:0,textBackgroundRadius:0,textShadow:false,textShadowColor:'#000000',textShadowBlur:6,textShadowX:3,textShadowY:3});repaintTextObject(object);fitTextObjectToContent(object);
 state.objects.push(object);normalizeObjectNames();selectObject(object,false);render();setStatus('Caixa de texto inserida. Edite diretamente no objeto.');startDirectTextEdit(object,true);
}
let lineDraft=null,linePointerTrace=null,lineNodeDrag=null;
const lineDraftPreview=document.createElementNS('http://www.w3.org/2000/svg','svg');
lineDraftPreview.classList.add('dtf-line-draft-preview');lineDraftPreview.setAttribute('aria-hidden','true');lineDraftPreview.setAttribute('viewBox','0 0 '+canvas.width+' '+canvas.height);lineDraftPreview.setAttribute('preserveAspectRatio','none');
const lineDraftPath=document.createElementNS('http://www.w3.org/2000/svg','polyline'),lineDraftGuideHalo=document.createElementNS('http://www.w3.org/2000/svg','line'),lineDraftGuide=document.createElementNS('http://www.w3.org/2000/svg','line'),lineDraftCloseMarker=document.createElementNS('http://www.w3.org/2000/svg','rect'),lineDraftCursorMarker=document.createElementNS('http://www.w3.org/2000/svg','circle');lineDraftPath.setAttribute('fill','none');lineDraftPath.setAttribute('stroke-linecap','round');lineDraftPath.setAttribute('stroke-linejoin','round');lineDraftGuideHalo.setAttribute('stroke','#ffffff');lineDraftGuideHalo.setAttribute('stroke-opacity','.9');lineDraftGuideHalo.setAttribute('stroke-linecap','round');lineDraftGuide.setAttribute('stroke-linecap','round');lineDraftGuide.setAttribute('stroke','#087eae');lineDraftGuide.setAttribute('stroke-opacity','.95');lineDraftGuide.setAttribute('stroke-dasharray','6 5');lineDraftCloseMarker.setAttribute('fill','none');lineDraftCloseMarker.setAttribute('stroke','#087eae');lineDraftCloseMarker.setAttribute('stroke-width','1.8');lineDraftCloseMarker.setAttribute('rx','1');lineDraftCloseMarker.style.display='none';lineDraftCursorMarker.setAttribute('r','4');lineDraftCursorMarker.setAttribute('fill','#087eae');lineDraftCursorMarker.setAttribute('stroke','#ffffff');lineDraftCursorMarker.setAttribute('stroke-width','1.5');lineDraftCursorMarker.style.display='none';lineDraftPreview.append(lineDraftPath,lineDraftGuideHalo,lineDraftGuide,lineDraftCloseMarker,lineDraftCursorMarker);inner.appendChild(lineDraftPreview);
function linePointDistance(a,b){return Math.hypot((a.x||0)-(b.x||0),(a.y||0)-(b.y||0))}
function linePointNearFirst(point){return !!(lineDraft&&lineDraft.nodes.length>1&&linePointDistance(point,lineDraft.nodes[0])<=Math.max(10,12/(state.zoom||1)))}
function drawLinePreview(point=null){if(!lineDraft){lineDraftPreview.classList.remove('show');lineDraftCloseMarker.style.display='none';lineDraftCursorMarker.style.display='none';editorRoot.classList.remove('dtf-line-close-hover');return}const nodes=lineDraft.nodes,stroke=shapeColorValue(currentPaletteHex(),'#000000'),width=Math.max(1,Number(lineDraft.strokeWidth)||3);lineDraftPreview.setAttribute('viewBox','0 0 '+canvas.width+' '+canvas.height);lineDraftPath.setAttribute('points',nodes.map(node=>node.x+','+node.y).join(' '));lineDraftPath.setAttribute('stroke',stroke);lineDraftPath.setAttribute('stroke-width',String(width));if(point&&nodes.length){const last=nodes[nodes.length-1],nearFirst=linePointNearFirst(point);editorRoot.classList.toggle('dtf-line-close-hover',nearFirst);[lineDraftGuideHalo,lineDraftGuide].forEach(guide=>{guide.setAttribute('x1',String(last.x));guide.setAttribute('y1',String(last.y));guide.setAttribute('x2',String(point.x));guide.setAttribute('y2',String(point.y));guide.style.display='block'});lineDraftGuideHalo.setAttribute('stroke-width',String(width+4));lineDraftGuide.setAttribute('stroke-width',String(Math.max(1.5,width+1)));lineDraftCursorMarker.setAttribute('cx',String(point.x));lineDraftCursorMarker.setAttribute('cy',String(point.y));lineDraftCursorMarker.setAttribute('fill',nearFirst?'#ffd43b':'#087eae');lineDraftCursorMarker.setAttribute('stroke',nearFirst?'#8b6500':'#ffffff');lineDraftCursorMarker.style.display='block';const first=nodes[0],closeSize=9;if(nearFirst){lineDraftCloseMarker.setAttribute('x',String(first.x-closeSize/2));lineDraftCloseMarker.setAttribute('y',String(first.y-closeSize/2));lineDraftCloseMarker.setAttribute('width',String(closeSize));lineDraftCloseMarker.setAttribute('height',String(closeSize));lineDraftCloseMarker.setAttribute('stroke','#ffd43b');lineDraftCloseMarker.style.display='block'}else lineDraftCloseMarker.style.display='none'}else{editorRoot.classList.remove('dtf-line-close-hover');lineDraftGuideHalo.style.display='none';lineDraftGuide.style.display='none';lineDraftCloseMarker.style.display='none';lineDraftCursorMarker.style.display='none'}lineDraftPreview.classList.add('show')}
function clearLineFinishTimer(){if(lineDraft&&lineDraft.finishTimer){clearTimeout(lineDraft.finishTimer);lineDraft.finishTimer=0}if(lineDraft)lineDraft.pendingPoint=null}
function cancelLineDraft(){if(lineDraft)clearLineFinishTimer();lineDraft=null;linePointerTrace=null;lineDraftPreview.classList.remove('show');lineDraftPath.removeAttribute('points');lineDraftGuideHalo.style.display='none';lineDraftGuide.style.display='none';lineDraftCloseMarker.style.display='none';lineDraftCursorMarker.style.display='none'}
function startLineDraft(point){lineDraft={nodes:[point],strokeWidth:Math.max(2,Math.round(Math.min(canvas.width,canvas.height)*.004)),continuous:false,finishTimer:0,pendingPoint:null};drawLinePreview(point);setStatus('Primeiro nó da linha definido. Clique no próximo ponto ou arraste para criar uma curva.')}
function createLineObject(nodes,closed=false){if(!nodes||nodes.length<2)return null;const strokeWidth=Math.max(2,Math.round(Math.min(canvas.width,canvas.height)*.004)),object={id:uid(),name:'Linha '+(state.objects.filter(isLineShape).length+1),x:0,y:0,w:1,h:1,visible:true,opacity:1,locked:false,groupId:null,sourceType:'shape-line',shapeFillColor:'none',shapeStrokeColor:currentPaletteHex(),shapeStrokeWidth:strokeWidth,lineNodes:[],lineClosed:!!closed,shapeCornerRadii:{tl:0,tr:0,br:0,bl:0},rotation:0,skewX:0,skewY:0,canvas:document.createElement('canvas'),baseCanvas:document.createElement('canvas'),restoreCanvas:document.createElement('canvas'),aiOriginalCanvas:null};rebuildLineObject(object,nodes);return object}
function finishLineDraft(){if(!lineDraft)return false;clearLineFinishTimer();const nodes=lineDraft.nodes.slice(),closed=lineDraft.closed===true;cancelLineDraft();if(nodes.length<2){setStatus('Linha cancelada.');return false}pushHistory(closed?'Fechar linha':'Inserir linha');const object=createLineObject(nodes,closed);state.objects.push(object);normalizeObjectNames();selectObject(object,false);render();setStatus(closed?object.name+' fechada no primeiro nó. Arraste os nós azuis para ajustar o traço.':object.name+' inserida. Arraste os nós azuis para ajustar o traço.');return true}
function scheduleLineSingleClickFinish(point,closeLoop=false){if(!lineDraft||!lineDraft.continuous)return;clearLineFinishTimer();const pending={x:point.x,y:point.y,closeLoop:!!closeLoop};lineDraft.pendingPoint=pending;lineDraft.finishTimer=window.setTimeout(()=>{if(!lineDraft||lineDraft.pendingPoint!==pending||!lineDraft.continuous)return;lineDraft.pendingPoint=null;lineDraft.finishTimer=0;const first=lineDraft.nodes[0],last=lineDraft.nodes[lineDraft.nodes.length-1];if(pending.closeLoop){lineDraft.closed=true}else if(linePointDistance(pending,last)>=.5)lineDraft.nodes.push(pending);finishLineDraft()},550)}
function scheduleLineFinish(){if(!lineDraft)return;clearLineFinishTimer();lineDraft.finishTimer=setTimeout(()=>{if(lineDraft&&!lineDraft.continuous)finishLineDraft()},520)}
function undoLineDraftNode(){if(!lineDraft)return false;clearLineFinishTimer();if(lineDraft.nodes.length>1){lineDraft.nodes.pop();drawLinePreview();setStatus('Último nó removido.');return true}cancelLineDraft();setStatus('Linha em criação cancelada.');return true}
function activateDrawLineTool(){setCursorSelectButtonActive(false);deactivateSpecialTool(true);deactivateBrush();cancelLineDraft();state.tool='drawLine';Object.values(drawShapeButtons).forEach(button=>{if(button){button.classList.remove('active');button.setAttribute('aria-pressed','false')}});if(drawMoreShapesButton){drawMoreShapesButton.classList.remove('active');drawMoreShapesButton.setAttribute('aria-pressed','false')}closeDrawShapePicker();if(drawTextButton){drawTextButton.classList.remove('active');drawTextButton.setAttribute('aria-pressed','false')}if(drawLineButton){drawLineButton.classList.add('active');drawLineButton.setAttribute('aria-pressed','true')}editorRoot.classList.add('dtf-draw-line-cursor');editorRoot.classList.remove('dtf-draw-rect-cursor','dtf-draw-text-cursor');showToolHint('Clique para criar nós. Dê duplo clique no segundo nó para continuar. ENTER finaliza; arraste para desenhar curva.');setStatus('Ferramenta Linha ativa')}
if(drawLineButton)drawLineButton.addEventListener('click',event=>{event.preventDefault();if(state.tool==='drawLine')deactivateDrawTool(false);else activateDrawLineTool()});
viewport.addEventListener('pointerdown',event=>{if(state.tool!=='drawLine'||event.button!==0)return;const raw=clientToWorkspace(event);if(!raw)return;const point={x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)};event.preventDefault();event.stopImmediatePropagation();
 if(!lineDraft){
  startLineDraft(point);
 }else{
  const lastNode=lineDraft.nodes[lineDraft.nodes.length-1],clickedLast=lineDraft.nodes.length>1&&linePointDistance(point,lastNode)<8;
  /* O segundo clique de um duplo clique nunca conclui a linha. O evento
     dblclick logo depois ativa o traço contínuo; assim não há disputa entre
     finalizar, selecionar o objeto e abrir Propriedades. */
  if(lineDraft.continuous){drawLinePreview(point)}else if(!clickedLast){
   lineDraft.nodes.push(point);drawLinePreview(point);setStatus('Segundo nó inserido. Dê duplo clique nele para continuar o traço; ENTER finaliza.');
  }
 }
 linePointerTrace={pointerId:event.pointerId,start:point,last:point,moved:false};try{viewport.setPointerCapture(event.pointerId)}catch(_){}}
,{capture:true});
document.addEventListener('pointermove',event=>{if(!linePointerTrace||event.pointerId!==linePointerTrace.pointerId||!lineDraft)return;const raw=clientToWorkspace(event);if(!raw)return;const point={x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)};if(!linePointerTrace.moved&&linePointDistance(point,linePointerTrace.start)<3){drawLinePreview(point);return}linePointerTrace.moved=true;clearLineFinishTimer();if(linePointDistance(point,linePointerTrace.last)>=2){lineDraft.nodes.push(point);linePointerTrace.last=point;drawLinePreview(point)}event.preventDefault()},{capture:true});
viewport.addEventListener('pointermove',event=>{if(state.tool!=='drawLine'||!lineDraft||linePointerTrace)return;const raw=clientToWorkspace(event);if(!raw)return;drawLinePreview({x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)})},{capture:true});
document.addEventListener('pointerup',event=>{if(!linePointerTrace||event.pointerId!==linePointerTrace.pointerId)return;const trace=linePointerTrace;linePointerTrace=null;if(trace.moved){const raw=clientToWorkspace(event);if(raw&&lineDraft){const point={x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)};if(linePointDistance(point,lineDraft.nodes[lineDraft.nodes.length-1])>=.5)lineDraft.nodes.push(point);if(lineDraft.continuous){scheduleLineSingleClickFinish(point,linePointNearFirst(point));return}}finishLineDraft()}else if(lineDraft&&lineDraft.continuous){scheduleLineSingleClickFinish(trace.start,linePointNearFirst(trace.start))}},{capture:true});
viewport.addEventListener('click',event=>{if(state.tool!=='drawLine'||!lineDraft||!lineDraft.continuous||event.detail!==1)return;const raw=clientToWorkspace(event);if(!raw)return;const point={x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)};scheduleLineSingleClickFinish(point,linePointNearFirst(point));event.preventDefault();event.stopImmediatePropagation()},{capture:true});
document.addEventListener('pointercancel',event=>{if(linePointerTrace&&event.pointerId===linePointerTrace.pointerId)linePointerTrace=null},{capture:true});
viewport.addEventListener('dblclick',event=>{
 if(state.tool!=='drawLine'||!lineDraft)return;
 event.preventDefault();event.stopImmediatePropagation();
 if(!lineDraft.continuous&&lineDraft.nodes.length>1){
  lineDraft.continuous=true;
  }
  clearLineFinishTimer();
  const raw=clientToWorkspace(event),point=raw?{x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)}:lineDraft.nodes[lineDraft.nodes.length-1],last=lineDraft.nodes[lineDraft.nodes.length-1],nearFirst=linePointNearFirst(point);
  /* O quadrado do primeiro nó é reservado ao clique simples de fechamento.
     Portanto, um duplo clique sobre ele jamais fecha a linha. */
  if(lineDraft.continuous&&!nearFirst&&linePointDistance(point,last)>=.5)lineDraft.nodes.push(point);
  drawLinePreview(point);
  setStatus('Traço contínuo ativo. Duplo clique adiciona outro nó e mantém a ferramenta; um clique simples finaliza após a confirmação do gesto. ENTER também finaliza.');
},{capture:true});
if(ui.undo)ui.undo.addEventListener('click',event=>{if(!undoLineDraftNode())return;event.preventDefault();event.stopImmediatePropagation()},{capture:true});
document.addEventListener('keydown',event=>{if(state.tool!=='drawLine')return;if(event.key==='Enter'&&lineDraft){event.preventDefault();event.stopImmediatePropagation();finishLineDraft()}else if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='z'&&lineDraft){event.preventDefault();event.stopImmediatePropagation();undoLineDraftNode()}else if(event.key==='Escape'&&lineDraft){event.preventDefault();cancelLineDraft();setStatus('Linha em criação cancelada.')}},{capture:true});
Object.entries(drawShapeButtons).forEach(([type,button])=>{if(button)button.addEventListener('click',e=>{e.preventDefault();if(state.tool==='drawRect'&&activeDrawShape===type)deactivateDrawTool(false);else activateDrawRectTool(type)})});
if(drawMoreShapesButton)drawMoreShapesButton.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();drawShapePicker.classList.contains('show')?closeDrawShapePicker():openDrawShapePicker()});
if(drawTextButton)drawTextButton.addEventListener('click',e=>{
 e.preventDefault();
 if(state.tool==='drawText')deactivateDrawTool(false);else activateDrawTextTool();
});
viewport.addEventListener('pointerdown',e=>{
 if(state.tool!=='drawRect'&&state.tool!=='drawText')return;
 const p=clientToWorkspace(e);if(!p)return;
 e.preventDefault();e.stopImmediatePropagation();
 const start={x:clamp(p.x,0,canvas.width),y:clamp(p.y,0,canvas.height)};
 if(state.tool==='drawRect'){
  drawRectDrag={start,current:start,locked:!!e.ctrlKey,pointerId:e.pointerId,shapeType:activeDrawShape};
  updateDrawRectPreview(drawRectFromPoints(start,start,drawRectDrag.locked));
 }else{
  drawTextDrag={start,current:start,pointerId:e.pointerId};
  updateDrawRectPreview(drawRectFromPoints(start,start,false),'text');
 }
 try{viewport.setPointerCapture(e.pointerId)}catch(_){}
},{capture:true});
document.addEventListener('pointermove',e=>{
 if((!drawRectDrag||e.pointerId!==drawRectDrag.pointerId)&&(!drawTextDrag||e.pointerId!==drawTextDrag.pointerId))return;
 const p=clientToWorkspace(e);if(!p)return;
 e.preventDefault();
 if(drawRectDrag&&e.pointerId===drawRectDrag.pointerId){
  drawRectDrag.current={x:clamp(p.x,0,canvas.width),y:clamp(p.y,0,canvas.height)};
  drawRectDrag.locked=!!e.ctrlKey;
  updateDrawRectPreview(drawRectFromPoints(drawRectDrag.start,drawRectDrag.current,drawRectDrag.locked));
 }else if(drawTextDrag&&e.pointerId===drawTextDrag.pointerId){
  drawTextDrag.current={x:clamp(p.x,0,canvas.width),y:clamp(p.y,0,canvas.height)};
  updateDrawRectPreview(drawRectFromPoints(drawTextDrag.start,drawTextDrag.current,false),'text');
 }
},{capture:true});
document.addEventListener('pointerup',e=>{
 if(drawRectDrag&&e.pointerId===drawRectDrag.pointerId){e.preventDefault();finishDrawRectDrag()}
 if(drawTextDrag&&e.pointerId===drawTextDrag.pointerId){e.preventDefault();finishDrawTextDrag()}
},{capture:true});
document.addEventListener('pointercancel',e=>{
 if((drawRectDrag&&e.pointerId===drawRectDrag.pointerId)||(drawTextDrag&&e.pointerId===drawTextDrag.pointerId)){
  drawRectDrag=null;drawTextDrag=null;drawRectPreview.classList.remove('show');drawRectPreview.removeAttribute('data-measure');hideDrawRectMeasure()
 }
},{capture:true});


let activeTab='arquivo',helpOpen=false,editDividerController=null;
function storePropertyHistory(snapshot,label){if(state.historyLock)return;snapshot.label=label||'Alterar propriedades';state.undo.push(snapshot);if(state.undo.length>state.historyLimit)state.undo.shift();state.redo=[];setProjectDirty(true);updateHistoryUI()}
function clonePropertyObject(source,copyNumber){
 const clone={...source,id:uid(),name:(source.name||'Objeto')+' — cópia '+copyNumber,x:0,y:0,locked:false,groupId:null,canvas:canvasFromData(cloneCanvasData(source.canvas)),baseCanvas:canvasFromData(cloneCanvasData(source.baseCanvas||source.canvas)),restoreCanvas:canvasFromData(cloneCanvasData(source.restoreCanvas||source.baseCanvas||source.canvas)),aiOriginalCanvas:source.aiOriginalCanvas?canvasFromData(cloneCanvasData(source.aiOriginalCanvas)):null,lineNodes:Array.isArray(source.lineNodes)?source.lineNodes.map(point=>({...point})):source.lineNodes,shapeCornerRadii:source.shapeCornerRadii?{...source.shapeCornerRadii}:source.shapeCornerRadii};
 assignAutoDisplayMode(clone);return clone;
}
function propertyClonePlan(sources,newCopies,allowHeightAdjustment){
 const quantityPerSource=newCopies+1,total=sources.length*quantityPerSource,projectTotal=state.objects.length+sources.length*newCopies;if(projectTotal>MAGIC_OBJECT_LIMIT)return {error:'O limite de segurança é '+MAGIC_OBJECT_LIMIT+' objetos no projeto.'};
 const gap=MAGIC_GAP_MM/25.4*state.dpi,items=[];sources.forEach((source,sourceIndex)=>{for(let count=0;count<quantityPerSource;count++)items.push({sourceIndex,w:source.w,h:source.h})});
 const fixedCandidates=[false,true].map(compact=>tryMagicShelfPacking(items,canvas.width,canvas.height,gap,false,compact)).filter(Boolean);if(fixedCandidates.length){fixedCandidates.sort((a,b)=>a.usedHeight-b.usedHeight);return {packing:fixedCandidates[0],expanded:false,total}}
 if(!allowHeightAdjustment)return {error:'A quantidade não cabe na página. Ative o ajuste automático da altura ou reduza a quantidade.'};
 const generousHeight=Math.max(canvas.height,items.reduce((sum,item)=>sum+item.h+gap,0)),expandedCandidates=[false,true].map(compact=>tryMagicShelfPacking(items,canvas.width,generousHeight,gap,false,compact)).filter(Boolean);if(!expandedCandidates.length)return {error:'Uma ou mais imagens são mais largas que a página. Reduza a largura antes de clonar.'};
 const normalized=expandedCandidates.map(result=>{const minY=Math.min(...result.placements.map(item=>item.y)),placements=result.placements.map(item=>({...item,y:item.y-minY})),requiredHeight=Math.ceil(Math.max(...placements.map(item=>item.y+item.h)));return {...result,placements,requiredHeight}}).sort((a,b)=>a.requiredHeight-b.requiredHeight)[0],maxHeight=physicalMmToPx(10000);
 if(normalized.requiredHeight>maxHeight)return {error:'A altura necessária ultrapassa o limite de 10.000 mm. Reduza a quantidade.'};
 return {packing:normalized,expanded:normalized.requiredHeight>canvas.height+.5,total,requiredHeight:normalized.requiredHeight};
}
function applyPropertyClones(sources,plan){
 if(plan.expanded){state.workHmm=Math.max(10,plan.requiredHeight*25.4/state.dpi);syncWorkspaceUI()}
 const counts=new Array(sources.length).fill(0),placed=[],created=[];
 plan.packing.placements.forEach(place=>{const source=sources[place.sourceIndex],number=++counts[place.sourceIndex],object=number===1?source:clonePropertyObject(source,number-1);object.x=place.x;object.y=place.y;object.w=place.w;object.h=place.h;if(number>1){state.objects.push(object);created.push(object)}placed.push(object)});normalizeObjectNames();
 state.selectedIds=placed.map(object=>object.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;render();updateSelection();updateObjectUI();return {created,placed};
}
openPropertiesModal=function(o){
 const targets=selectedObjects().length?selectedObjects():[o],oldX=o.x,oldY=o.y,oldW=o.w,oldH=o.h,ratio=o.w/o.h,beforeSnapshot=projectSnapshot(),maxNewCopies=Math.max(0,Math.floor((MAGIC_OBJECT_LIMIT-state.objects.length)/Math.max(1,targets.length))),cloningAvailable=maxNewCopies>0,m=document.createElement('div');
 m.className='dtf-properties-modal dtf-object-properties-modal';
 m.innerHTML='<div class="dtf-properties-box dtf-object-properties-box"><h3>Propriedades</h3><div class="dtf-properties-fields"><label>Largura (mm)<input id="propW" type="number" min="0.1" step="0.1"></label><label>Altura (mm)<input id="propH" type="number" min="0.1" step="0.1"></label></div><label><input id="propLock" type="checkbox" checked> Manter proporção</label><small>Original: '+pxToMm(oldW).toFixed(1)+' × '+pxToMm(oldH).toFixed(1)+' mm</small><section class="dtf-properties-clone"><label class="dtf-properties-clone-toggle"><input id="propCloneEnabled" type="checkbox"'+(cloningAvailable?'':' disabled')+'> <span><b>Clonagem</b><small>Manter os originais, criar novas cópias das imagens selecionadas e organizar tudo na página.</small></span></label><div class="dtf-properties-clone-options" data-clone-options><label>Número de novas cópias<input id="propCloneQuantity" type="number" min="1" max="'+Math.max(1,maxNewCopies)+'" step="1" value="1" disabled></label><label class="dtf-properties-clone-height"><input id="propCloneAutoHeight" type="checkbox" checked disabled> Ajustar a altura da página se necessário</label></div><small class="dtf-properties-clone-summary" data-clone-summary>'+targets.length+' objeto'+(targets.length===1?' selecionado':'s selecionados')+'.</small><small class="dtf-properties-clone-error" data-clone-error>'+(cloningAvailable?'':'O limite de '+MAGIC_OBJECT_LIMIT+' objetos já foi atingido para esta seleção.')+'</small></section><div class="dtf-properties-actions"><button type="button" data-preview>Prévia</button><button type="button" data-cancel>Cancelar</button><button type="button" data-confirm>Confirmar</button></div><small class="dtf-preview-hint">Pressione e mantenha o botão Prévia para visualizar.</small></div>';
 document.body.appendChild(m);
 const w=m.querySelector('#propW'),h=m.querySelector('#propH'),lock=m.querySelector('#propLock'),cloneEnabled=m.querySelector('#propCloneEnabled'),cloneQuantity=m.querySelector('#propCloneQuantity'),cloneAutoHeight=m.querySelector('#propCloneAutoHeight'),cloneSummary=m.querySelector('[data-clone-summary]'),cloneError=m.querySelector('[data-clone-error]');w.value=pxToMm(o.w).toFixed(1);h.value=pxToMm(o.h).toFixed(1);
 const quantityValue=()=>clamp(parseInt(cloneQuantity.value,10)||1,1,Math.max(1,maxNewCopies)),syncCloneFields=()=>{const enabled=cloningAvailable&&cloneEnabled.checked;cloneQuantity.disabled=!enabled;cloneAutoHeight.disabled=!enabled;if(cloningAvailable)cloneError.textContent='';if(enabled){const copies=quantityValue(),newTotal=targets.length*copies,total=targets.length+newTotal;cloneQuantity.value=String(copies);cloneSummary.textContent=targets.length+' original'+(targets.length===1?'':'is')+' + '+newTotal+' nova'+(newTotal===1?' cópia':'s cópias')+' = '+total+' objetos na página.'}else cloneSummary.textContent=targets.length+' objeto'+(targets.length===1?' selecionado.':'s selecionados.')};
 cloneEnabled.addEventListener('change',()=>{syncCloneFields();if(cloneEnabled.checked)cloneQuantity.focus()});cloneQuantity.addEventListener('input',syncCloneFields);cloneAutoHeight.addEventListener('change',()=>cloneError.textContent='');syncCloneFields();
 const live=()=>{let nw=physicalMmToPx(parseFloat(w.value)||1),nh=physicalMmToPx(parseFloat(h.value)||1);if(lock.checked){if(document.activeElement===w)nh=nw/ratio;else nw=nh*ratio;w.value=pxToMm(nw).toFixed(1);h.value=pxToMm(nh).toFixed(1)}o.w=Math.min(canvas.width,nw);o.h=Math.min(canvas.height,nh);o.x=clamp(o.x,0,Math.max(0,canvas.width-o.w));o.y=clamp(o.y,0,Math.max(0,canvas.height-o.h));if(isTextShape(o))syncTextBoxCanvasSize(o,o.w,o.h);render()};w.oninput=live;h.oninput=live;
 const pv=m.querySelector('[data-preview]'),box=m.querySelector('.dtf-properties-box');let previewStarted=0,previewTimer=0;const showPreview=()=>{clearTimeout(previewTimer);previewStarted=Date.now();box.style.opacity='.1'};const restorePreview=()=>{clearTimeout(previewTimer);previewTimer=setTimeout(()=>{box.style.opacity='1';previewStarted=0},Math.max(0,2000-(Date.now()-previewStarted)))};pv.onpointerdown=showPreview;pv.onpointerup=pv.onpointerleave=pv.onpointercancel=restorePreview;pv.onclick=()=>{if(!previewStarted){showPreview();restorePreview()}};
 m.querySelector('[data-confirm]').onclick=()=>{cloneError.textContent='';let plan=null;if(cloneEnabled.checked){plan=propertyClonePlan(targets,quantityValue(),cloneAutoHeight.checked);if(plan.error){cloneError.textContent=plan.error;cloneQuantity.focus();return}}const changed=Math.abs(o.x-oldX)>.01||Math.abs(o.y-oldY)>.01||Math.abs(o.w-oldW)>.01||Math.abs(o.h-oldH)>.01;if(changed||plan)storePropertyHistory(beforeSnapshot,plan?'Alterar propriedades e clonar seleção':'Alterar propriedades');clearTimeout(previewTimer);if(plan){const result=applyPropertyClones(targets,plan);m.remove();setStatus(plan.total+' objetos organizados na página · '+result.created.length+' cópia'+(result.created.length===1?' criada':'s criadas')+(plan.expanded?' · altura da página ajustada':'')+'.')}else{render();updateSelection();updateObjectUI();m.remove();setStatus(changed?'Propriedades atualizadas.':'Propriedades mantidas.')}};
 m.querySelector('[data-cancel]').onclick=()=>{clearTimeout(previewTimer);o.x=oldX;o.y=oldY;o.w=oldW;o.h=oldH;if(isTextShape(o))syncTextBoxCanvasSize(o,o.w,o.h);render();updateSelection();m.remove();setStatus('Alterações das propriedades canceladas.')};
 m.addEventListener('pointerdown',event=>{if(event.target===m)m.querySelector('[data-cancel]').click()});m.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();m.querySelector('[data-cancel]').click()}});return m;
}
function showEditorTab(tab){
 if(typeof deactivateDrawTool==='function')deactivateDrawTool(true);
 deactivateSpecialTool(true);deactivateBrush();deactivateMagnifier(true);
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
 if(!helpOpen&&editDividerController)requestAnimationFrame(()=>editDividerController.ensure(activeTab));
}
const showEditorTabBeforeTextPanel=showEditorTab;
showEditorTab=function(tab){if(tab==='desenhar'&&typeof isTextShape==='function'&&isTextShape(selected()))tab='texto';showEditorTabBeforeTextPanel(tab)};
QA('[data-tab]').forEach(b=>b.addEventListener('click',()=>{showEditorTab(b.dataset.tab)}));
const selectAllMenu=$id('dtfSelectAll');if(selectAllMenu)selectAllMenu.addEventListener('click',()=>{state.selectedIds=state.objects.filter(o=>o.visible).map(o=>o.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;updateObjectUI();updateSelection();$id('dtfCanvasMenu').classList.remove('show');setStatus(state.selectedIds.length+' objetos selecionados')});
const copyMenu=$id('dtfCopyObjects'),pasteMenu=$id('dtfPasteObjects');if(copyMenu)copyMenu.addEventListener('click',()=>{copySelected();$id('dtfCanvasMenu').classList.remove('show')});if(pasteMenu)pasteMenu.addEventListener('click',()=>{pasteClipboard();$id('dtfCanvasMenu').classList.remove('show')});
const duplicateMenu=$id('dtfDuplicateObjects'),deleteMenu=$id('dtfDeleteObjects');if(duplicateMenu)duplicateMenu.addEventListener('click',()=>{duplicateSelected();$id('dtfCanvasMenu').classList.remove('show')});if(deleteMenu)deleteMenu.addEventListener('click',()=>{ui.del.click();$id('dtfCanvasMenu').classList.remove('show')});
if(guideDeleteMenu)guideDeleteMenu.addEventListener('click',()=>{const id=state.selectedGuideId;if(id)removeCustomGuide(id);if(canvasMenu){canvasMenu.classList.remove('show');canvasMenu.classList.remove('guide-context')}guideDeleteMenu.style.display='none'});
function flipSelected(horizontal){const items=selectedObjects();if(!items.length)return;pushHistory(horizontal?'Inverter horizontal':'Inverter vertical');items.forEach(o=>{const c=document.createElement('canvas');c.width=o.canvas.width;c.height=o.canvas.height;const cc=c.getContext('2d');cc.translate(horizontal?c.width:0,horizontal?0:c.height);cc.scale(horizontal?-1:1,horizontal?1:-1);cc.drawImage(o.canvas,0,0);o.canvas=c});render();setStatus(horizontal?'Objetos invertidos horizontalmente':'Objetos invertidos verticalmente')}
const flipH=$id('dtfFlipH'),flipV=$id('dtfFlipV');if(flipH)flipH.addEventListener('click',()=>{flipSelected(true);$id('dtfCanvasMenu').classList.remove('show')});if(flipV)flipV.addEventListener('click',()=>{flipSelected(false);$id('dtfCanvasMenu').classList.remove('show')});
const groupMenu=$id('dtfGroupObjects'),ungroupMenu=$id('dtfUngroupObjects');function updateGroupMenu(){updateContextTools()}
if(groupMenu)groupMenu.addEventListener('click',()=>{groupSelected();updateGroupMenu();$id('dtfCanvasMenu').classList.remove('show')});if(ungroupMenu)ungroupMenu.addEventListener('click',()=>{ungroupSelected();updateGroupMenu();$id('dtfCanvasMenu').classList.remove('show')});
root.addEventListener('click',e=>{
 const button=e.target.closest('button');
 if(button&&button!==magnifierButton&&(button.classList.contains('dtf-tab')||button.closest('.dtf-panel')||button.closest('.dtf-global-history')||button.closest('.dtf-canvas-menu')))deactivateMagnifier(true);
 if(button&&button.id!=='dtfReset'&&button.id!=='dtfRemovalApply'&&button.id!=='dtfUndo'&&button.id!=='dtfRedo'&&button.id!=='dtfOriginal'&&button.id!=='dtfUndoMenuBtn'&&button.id!=='dtfRedoMenuBtn'&&button.id!=='dtfMainColorButton'&&button.id!=='dtfSelectCursorTool'&&button.id!=='dtfDrawRectangle'&&button.id!=='dtfDrawCircle'&&button.id!=='dtfDrawTriangle'&&button.id!=='dtfDrawLine'&&button.id!=='dtfDrawMoreShapes'&&button.id!=='dtfDrawText'&&button.id!=='dtfEyedropperTool'&&button.id!=='dtfReplaceColorTool'&&button.id!=='dtfSharpenTool'&&button.id!=='dtfVisualHistory'&&button.id!=='dtfMagnifierTool'&&button.id!=='dtfPreflightTool','dtfExportPreflight'&&button.id!=='dtfExportPreflight'&&!button.closest('.dtf-removal-settings')&&ui.reset&&ui.reset.classList.contains('active'))deactivateAutoRemoveOption();
 if(button&&!button.closest('.dtf-removal-settings')&&!['dtfEraser','dtfRestoreBrush','dtfClickRemove','dtfAreaRemove','dtfColorRemove','dtfColorAreaRemove','dtfFillTool','dtfReset','dtfRemovalApply','dtfUndo','dtfRedo','dtfOriginal','dtfUndoMenuBtn','dtfRedoMenuBtn','dtfMainColorButton','dtfSelectCursorTool','dtfDrawRectangle','dtfDrawCircle','dtfDrawTriangle','dtfDrawLine','dtfDrawMoreShapes','dtfDrawText','dtfEyedropperTool','dtfReplaceColorTool','dtfSharpenTool','dtfVisualHistory','dtfMagnifierTool','dtfPreflightTool','dtfExportPreflight'].includes(button.id)){deactivateSpecialTool(true);deactivateBrush()}
},true);

// Estado de saída.
function updateOutputInfo(){const enhanced=exportEnhancementEnabled(),size=exportPixelSize(exportScaleForFormat()),q=Math.max(1,Math.min(100,Number(ui.quality&&ui.quality.value)||100)),lossy=exportFormat==='jpeg'||exportFormat==='webp',measure=Number(state.workWmm.toFixed(2))+' × '+Number(state.workHmm.toFixed(2))+' mm',pdfModeLabel=exportFormat==='pdf'?(pdfExportMode==='objects'?' · objetos separados':' · imagem única'):'';if(ui.outputInfo)ui.outputInfo.textContent=measure+' · '+size.width+' × '+size.height+' px · '+size.dpiLabel+pdfModeLabel+(lossy?' · qualidade '+q+'%'+(q===100?' (máxima)':'')+(enhanced?' · melhoria de IA ativa':''):' · sem perda de qualidade')}
if(ui.quality)ui.quality.addEventListener('input',updateOutputInfo);if(ui.dpi)ui.dpi.addEventListener('change',()=>{state.dpi=Number(ui.dpi.value)||state.dpi;setProjectDirty(true);updateOutputInfo();syncWorkspaceUI();render()});updateOutputInfo();
viewport.addEventListener('dblclick',e=>{if(e.target.closest&&e.target.closest('.dtf-guide-cut-line'))return;if(state.tool!=='select'||lineDraft||linePointerTrace)return;const p=clientToWorkspace(e),o=p&&hitTest(p.x,p.y);if(o){e.preventDefault();e.stopPropagation();if(isTextShape(o)){selectObject(o,false);startDirectTextEdit(o,false)}else{if(!state.selectedIds.includes(o.id))selectObject(o,false);openPropertiesModal(o)}}},{capture:true});
const updateSelectionWithLocks=updateSelection;updateSelection=function(){updateSelectionWithLocks();inner.querySelectorAll('.dtf-lock-badge').forEach(el=>el.remove());state.objects.filter(o=>o.visible&&o.locked).forEach(o=>{const lb=document.createElement('div');lb.className='dtf-lock-badge';lb.textContent='🔒';lb.style.left=o.x+'px';lb.style.top=o.y+'px';inner.appendChild(lb)})};
const lineNodeSelection={objectId:null,indices:new Set()};
let lineNodeHover=null,lineNodeObjectHover=false,lineNodeCtrlDown=false,lineNodePrecisionPinned=false;
function syncLineNodePrecisionMode(){const nodeMode=state.tool==='select'&&state.selectedIds.length===1&&isLineShape(selected()),active=nodeMode&&(lineNodePrecisionPinned||!!(lineNodeCtrlDown&&(lineNodeObjectHover||lineNodeHover&&lineNodeHover.isConnected)));editorRoot.classList.toggle('dtf-line-node-precision',active);editorRoot.classList.toggle('dtf-line-node-hover',!!(lineNodeHover&&lineNodeHover.isConnected));if(lineNodeHover&&lineNodeHover.isConnected)lineNodeHover.classList.toggle('is-ctrl-hover',active)}
function updateLineNodeObjectHover(event){const object=selected(),raw=event&&clientToWorkspace(event),over=!!(state.tool==='select'&&state.selectedIds.length===1&&isLineShape(object)&&raw&&raw.x>=object.x&&raw.x<=object.x+object.w&&raw.y>=object.y&&raw.y<=object.y+object.h);if(lineNodeObjectHover!==over){lineNodeObjectHover=over;syncLineNodePrecisionMode()}}
viewport.addEventListener('pointermove',updateLineNodeObjectHover,{capture:true});
viewport.addEventListener('pointerleave',()=>{if(lineNodeObjectHover){lineNodeObjectHover=false;syncLineNodePrecisionMode()}},{capture:true});
function clearLineNodeSelection(){lineNodeSelection.objectId=null;lineNodeSelection.indices.clear()}
function syncLineNodeSelectionUI(){inner.querySelectorAll('.dtf-line-node').forEach(node=>node.classList.toggle('is-selected',lineNodeSelection.objectId===node.dataset.objectId&&lineNodeSelection.indices.has(Number(node.dataset.nodeIndex))))}
function selectLineNode(objectId,index,additive=false){if(lineNodeSelection.objectId!==objectId){lineNodeSelection.objectId=objectId;lineNodeSelection.indices.clear()}if(additive){if(lineNodeSelection.indices.has(index))lineNodeSelection.indices.delete(index);else lineNodeSelection.indices.add(index)}else{lineNodeSelection.indices.clear();lineNodeSelection.indices.add(index)}syncLineNodeSelectionUI()}
function deleteSelectedLineNodes(){const object=state.objects.find(item=>item.id===lineNodeSelection.objectId),indices=lineNodeSelection.indices;if(!object||!isLineShape(object)||!indices.size)return false;const nodes=lineGlobalNodes(object),remaining=nodes.filter((_,index)=>!indices.has(index));pushHistory(indices.size===1?'Excluir nó da linha':'Excluir '+indices.size+' nós da linha');if(remaining.length<2){state.objects=state.objects.filter(item=>item.id!==object.id);state.selectedId=null;state.selectedIds=[];clearLineNodeSelection();render();setStatus('Linha excluída, pois não restaram nós suficientes.');return true}rebuildLineObject(object,remaining);clearLineNodeSelection();render();setStatus(indices.size+' nó'+(indices.size===1?'':'s')+' excluído'+(indices.size===1?'':'s')+' da linha.');return true}
function renderLineNodeHandles(){lineNodeHover=null;syncLineNodePrecisionMode();inner.querySelectorAll('.dtf-line-node').forEach(node=>node.remove());const object=selected();if(!isLineShape(object)||object.locked||state.selectedIds.length!==1){clearLineNodeSelection();return}if(lineNodeSelection.objectId!==object.id)clearLineNodeSelection();lineGlobalNodes(object).forEach((point,index)=>{const node=document.createElement('button');node.type='button';node.className='dtf-line-node';node.dataset.objectId=object.id;node.dataset.nodeIndex=String(index);node.title='Clique para selecionar; CTRL + clique seleciona mais de um; arraste para mover.';node.setAttribute('aria-label',node.title);node.style.left=point.x+'px';node.style.top=point.y+'px';node.classList.toggle('is-selected',lineNodeSelection.objectId===object.id&&lineNodeSelection.indices.has(index));node.addEventListener('pointerenter',()=>{lineNodeHover=node;node.classList.add('is-hovered');syncLineNodePrecisionMode()});node.addEventListener('pointerleave',()=>{node.classList.remove('is-hovered');if(lineNodeHover===node){lineNodeHover=null;syncLineNodePrecisionMode()}});node.addEventListener('pointerdown',event=>{if(event.button!==0)return;event.preventDefault();event.stopPropagation();lineNodeCtrlDown=!!(event.ctrlKey||event.metaKey);lineNodeHover=node;node.classList.add('is-hovered');lineNodePrecisionPinned=lineNodeCtrlDown;syncLineNodePrecisionMode();selectLineNode(object.id,index,lineNodeCtrlDown);lineNodeDrag={pointerId:event.pointerId,objectId:object.id,index,historySaved:false,start:{x:event.clientX,y:event.clientY},moved:false};try{node.setPointerCapture(event.pointerId)}catch(_){}});inner.appendChild(node)})}
const updateSelectionWithLineNodes=updateSelection;updateSelection=function(){updateSelectionWithLineNodes();renderLineNodeHandles()};
document.addEventListener('pointermove',event=>{if(!lineNodeDrag||event.pointerId!==lineNodeDrag.pointerId)return;const object=state.objects.find(item=>item.id===lineNodeDrag.objectId),raw=clientToWorkspace(event);if(!object||!raw){lineNodeDrag=null;return}const point={x:clamp(raw.x,0,canvas.width),y:clamp(raw.y,0,canvas.height)};if(!lineNodeDrag.moved&&Math.hypot(event.clientX-lineNodeDrag.start.x,event.clientY-lineNodeDrag.start.y)<2)return;lineNodeDrag.moved=true;const nodes=lineGlobalNodes(object);if(!lineNodeDrag.historySaved){pushHistory('Mover nó da linha');lineNodeDrag.historySaved=true}nodes[lineNodeDrag.index]=point;rebuildLineObject(object,nodes);render();event.preventDefault()},{capture:true});
document.addEventListener('pointerup',event=>{if(!lineNodeDrag||event.pointerId!==lineNodeDrag.pointerId)return;const moved=lineNodeDrag.moved;lineNodeDrag=null;lineNodePrecisionPinned=false;syncLineNodePrecisionMode();if(moved){render();setStatus('Nó da linha ajustado.')}},{capture:true});
document.addEventListener('pointercancel',()=>{if(lineNodeDrag){lineNodeDrag=null;lineNodePrecisionPinned=false;syncLineNodePrecisionMode();render()}},{capture:true});
document.addEventListener('keydown',event=>{if(event.key==='Control'){lineNodeCtrlDown=true;syncLineNodePrecisionMode()}},{capture:true});
document.addEventListener('keyup',event=>{if(event.key==='Control'){lineNodeCtrlDown=false;syncLineNodePrecisionMode()}},{capture:true});
window.addEventListener('blur',()=>{lineNodeCtrlDown=false;lineNodeHover=null;lineNodeObjectHover=false;lineNodePrecisionPinned=false;syncLineNodePrecisionMode()});
document.addEventListener('keydown',event=>{const editable=document.activeElement&&['INPUT','SELECT','TEXTAREA'].includes(document.activeElement.tagName);if(activeEditorRoot!==editorRoot||editable||event.key!=='Delete'||!lineNodeSelection.indices.size)return;if(deleteSelectedLineNodes()){event.preventDefault();event.stopImmediatePropagation()}},{capture:true});
function keepContextMenuVisible(){const menu=$id('dtfCanvasMenu');if(!menu||!menu.classList.contains('show'))return;const r=menu.getBoundingClientRect();menu.style.left=Math.max(8,Math.min(parseFloat(menu.style.left)||8,window.innerWidth-r.width-8))+'px';menu.style.top=Math.max(8,Math.min(parseFloat(menu.style.top)||8,window.innerHeight-r.height-8))+'px'}window.addEventListener('resize',()=>{keepContextMenuVisible();updateScrollSpace();updateRulers();positionMagicLayoutPreview()});window.addEventListener('scroll',keepContextMenuVisible,true);if(typeof ResizeObserver==='function'){const rulerObserver=new ResizeObserver(()=>updateRulers());rulerObserver.observe(inner);rulerObserver.observe(viewport)}
// Permite digitação natural nos campos (50, 50.5 etc.) sem formatar a cada tecla.
const openPropertiesModalStable=openPropertiesModal;
openPropertiesModal=function(o){
 const m=openPropertiesModalStable(o);
 if(!m)return;
 let w=m.querySelector('#propW'),h=m.querySelector('#propH'),lock=m.querySelector('#propLock');
 // Substitui os inputs originais para remover listeners/máscaras aplicados pelo tema.
 const unmask=el=>{if(!el)return el;const copy=el.cloneNode(true);copy.type='text';copy.inputMode='decimal';copy.removeAttribute('min');copy.removeAttribute('max');copy.removeAttribute('step');el.replaceWith(copy);return copy};
 w=unmask(w);h=unmask(h);lock=m.querySelector('#propLock');
 [w,h].forEach(el=>el&&el.addEventListener('focus',()=>el.select(),{once:false}));
 const ratio=o.w/o.h;
 const numberValue=el=>{const value=String(el&&el.value||'').trim().replace(',','.');return Number(value)};
 const applyValue=(source)=>{let nw=physicalMmToPx(numberValue(w)),nh=physicalMmToPx(numberValue(h));if(!Number.isFinite(nw)||!Number.isFinite(nh))return;if(lock&&lock.checked){if(source==='w'){nh=nw/ratio;h.value=pxToMm(nh).toFixed(1)}else{nw=nh*ratio;w.value=pxToMm(nw).toFixed(1)}}o.w=Math.min(canvas.width,nw);o.h=Math.min(canvas.height,nh);o.x=clamp(o.x,0,canvas.width-o.w);o.y=clamp(o.y,0,canvas.height-o.h);if(isTextShape(o))syncTextBoxCanvasSize(o,o.w,o.h);render()};
 if(w){w.oninput=e=>{e.stopPropagation();applyValue('w')};w.onblur=()=>{const n=numberValue(w);if(Number.isFinite(n))w.value=String(n).replace('.',',')}}
 if(h){h.oninput=e=>{e.stopPropagation();applyValue('h')};h.onblur=()=>{const n=numberValue(h);if(Number.isFinite(n))h.value=String(n).replace('.',',')}}
};
// Propriedades é uma ação direta; Herança permanece responsável por Copiar/Colar.
if(canvasMenu){Array.from(canvasMenu.querySelectorAll('.dtf-properties-menu')).forEach(pm=>{const b=pm.querySelector(':scope > button'),sub=pm.querySelector('.dtf-properties-submenu');if(!b||b.textContent.trim()!=='Propriedades ▸')return;b.textContent='Propriedades';if(sub)sub.remove();b.onclick=()=>{const o=selected();if(o)openPropertiesModal(o);canvasMenu.classList.remove('show')}})}
const paperPresetSelect=$id('dtfPaperPreset'),customWorkspaceRow=$id('dtfCustomWorkspaceRow');
const paperPickerButton=$id('dtfPaperPickerButton'),paperPickerLabel=$id('dtfPaperPickerLabel');
const PAPER_PRESETS=[
 {id:'a4',name:'A4',w:210,h:297},{id:'a5',name:'A5',w:148,h:210},{id:'a6',name:'A6',w:105,h:148},{id:'a3',name:'A3',w:297,h:420},{id:'a2',name:'A2',w:420,h:594},
 {id:'a1',name:'A1',w:594,h:841},{id:'a0',name:'A0',w:841,h:1189},{id:'a7',name:'A7',w:74,h:105},{id:'a8',name:'A8',w:52,h:74},{id:'a9',name:'A9',w:37,h:52},{id:'a10',name:'A10',w:26,h:37},
 {id:'b4',name:'B4',w:250,h:353},{id:'b5',name:'B5',w:176,h:250},{id:'b6',name:'B6',w:125,h:176},{id:'c4',name:'C4 (envelope)',w:229,h:324},{id:'c5',name:'C5 (envelope)',w:162,h:229},{id:'c6',name:'C6 (envelope)',w:114,h:162},
 {id:'carta',name:'Carta',w:216,h:279},{id:'oficio',name:'Ofício',w:216,h:356},{id:'legal',name:'Legal',w:216,h:356},{id:'tabloide',name:'Tabloide',w:279,h:432}
];
const PAPER_USAGE_KEY='printway_dtf_paper_usage_'+autosaveUserKey;
function readPaperUsage(){try{const data=JSON.parse(localStorage.getItem(PAPER_USAGE_KEY)||'{}');return data&&typeof data==='object'?data:{}}catch(_){return {}}}
function writePaperUsage(data){try{localStorage.setItem(PAPER_USAGE_KEY,JSON.stringify(data))}catch(_){}}
function frequentPaperPresets(){const usage=readPaperUsage();return PAPER_PRESETS.filter(item=>(Number(usage[item.id])||0)>0).sort((a,b)=>(Number(usage[b.id])||0)-(Number(usage[a.id])||0)||PAPER_PRESETS.indexOf(a)-PAPER_PRESETS.indexOf(b)).slice(0,5)}
function paperOption(item){const option=document.createElement('option');option.value=item.id;option.textContent=item.name+' — '+item.w+' × '+item.h+' mm';return option}
function paperPickerText(value){const item=PAPER_PRESETS.find(entry=>entry.id===value);return item?item.name+' — '+item.w+' × '+item.h+' mm':'Personalizado'}
function syncCustomWorkspaceFields(value){if(customWorkspaceRow)customWorkspaceRow.hidden=!!value}
function syncPaperPickerControl(value){const selected=value==null?(paperPresetSelect?paperPresetSelect.value:''):value;if(paperPickerLabel)paperPickerLabel.textContent=paperPickerText(selected);syncCustomWorkspaceFields(selected)}
function renderPaperPresetList(selectedValue=''){
 if(!paperPresetSelect)return;const current=selectedValue||paperPresetSelect.value||'',frequent=frequentPaperPresets();paperPresetSelect.innerHTML='';const custom=document.createElement('option');custom.value='';custom.textContent='Personalizado';paperPresetSelect.appendChild(custom);
 if(frequent.length){const title=document.createElement('option');title.disabled=true;title.textContent='— Mais usados por você —';paperPresetSelect.appendChild(title);frequent.forEach(item=>paperPresetSelect.appendChild(paperOption(item)));const divider=document.createElement('option');divider.disabled=true;divider.textContent='────────────────────';paperPresetSelect.appendChild(divider)}
 PAPER_PRESETS.forEach(item=>paperPresetSelect.appendChild(paperOption(item)));paperPresetSelect.value=current;syncPaperPickerControl(current);
}
let paperPickerMenu=null;
function paperPreviewReferencePresets(preset){
 if(!preset)return[];
 const seriesMatch=String(preset.id||'').match(/^a(\d+)$/);
 if(seriesMatch){
  const index=Number(seriesMatch[1]),ids=[index+1,index,index-1].filter(value=>value>=0&&value<=10).map(value=>'a'+value);
  return ids.map(id=>PAPER_PRESETS.find(item=>item.id===id)).filter(Boolean)
 }
 const reference=PAPER_PRESETS.find(item=>item.id==='a4');
 return [preset,reference].filter((item,index,array)=>item&&array.findIndex(candidate=>candidate.id===item.id)===index)
}
function updatePaperPickerPreview(panel,preset){
 panel.replaceChildren();const title=document.createElement('strong'),measure=document.createElement('span');title.className='dtf-paper-preview-name';measure.className='dtf-paper-preview-measure';if(!preset){title.textContent='Papel personalizado';measure.textContent='Informe largura e altura';panel.append(title,measure);return}title.textContent=preset.name;measure.textContent='L '+preset.w+' × A '+preset.h+' mm';const references=paperPreviewReferencePresets(preset),comparison=document.createElement('div');comparison.className='dtf-paper-preview-comparison';comparison.setAttribute('aria-label','Comparação proporcional entre formatos de papel');const maxW=Math.max(...references.map(item=>item.w)),maxH=Math.max(...references.map(item=>item.h)),scale=Math.min(196/maxW,112/maxH);references.forEach(reference=>{const item=document.createElement('div'),label=document.createElement('b'),sheet=document.createElement('i');item.className='dtf-paper-preview-item'+(reference.id===preset.id?' is-selected':'');item.title=reference.name+' — '+reference.w+' × '+reference.h+' mm';label.textContent=reference.name;sheet.className='dtf-paper-preview-sheet';sheet.style.width=Math.max(10,Math.round(reference.w*scale))+'px';sheet.style.height=Math.max(10,Math.round(reference.h*scale))+'px';sheet.setAttribute('aria-hidden','true');item.append(label,sheet);comparison.appendChild(item)});const orientation=document.createElement('small');orientation.textContent=(preset.w>preset.h?'Paisagem':'Retrato')+' · escala proporcional';panel.append(title,comparison,measure,orientation)
}
function positionPaperPickerMenu(){if(!paperPickerMenu||!paperPickerButton)return;const rect=paperPickerButton.getBoundingClientRect(),width=paperPickerMenu.offsetWidth||456,height=paperPickerMenu.offsetHeight||392;paperPickerMenu.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-width-8))+'px';paperPickerMenu.style.top=Math.max(8,Math.min(rect.bottom+5,window.innerHeight-height-8))+'px'}
function closePaperPicker(){if(!paperPickerMenu)return;paperPickerMenu.classList.remove('show');if(paperPickerButton)paperPickerButton.setAttribute('aria-expanded','false')}
function choosePaperPreset(value){if(!paperPresetSelect)return;paperPresetSelect.value=value;paperPresetSelect.dispatchEvent(new Event('change',{bubbles:true}));closePaperPicker()}
function openPaperPicker(){
 if(!paperPickerButton||!paperPresetSelect)return;if(!paperPickerMenu){paperPickerMenu=document.createElement('div');paperPickerMenu.id='dtfPaperPickerMenu';paperPickerMenu.className='dtf-paper-picker-menu';paperPickerMenu.setAttribute('role','dialog');paperPickerMenu.setAttribute('aria-label','Escolher formato de papel');document.body.appendChild(paperPickerMenu)}
 renderPaperPresetList(paperPresetSelect.value);paperPickerMenu.replaceChildren();const list=document.createElement('div'),preview=document.createElement('aside');list.className='dtf-paper-picker-list';preview.className='dtf-paper-picker-preview';
 const addEntry=(preset,label,kind='paper')=>{const entry=document.createElement('button');entry.type='button';entry.className='dtf-paper-picker-entry dtf-paper-picker-'+kind;entry.dataset.value=preset?preset.id:'';const name=document.createElement('b'),measure=document.createElement('small');name.textContent=label||preset.name;measure.textContent=preset?'L '+preset.w+' × A '+preset.h+' mm':'Definir manualmente';entry.append(name,measure);entry.addEventListener('mouseenter',()=>updatePaperPickerPreview(preview,preset));entry.addEventListener('focus',()=>updatePaperPickerPreview(preview,preset));entry.addEventListener('click',()=>choosePaperPreset(preset?preset.id:''));list.appendChild(entry)};
 addEntry(null,'Personalizado','custom');const frequent=frequentPaperPresets();if(frequent.length){const caption=document.createElement('div');caption.className='dtf-paper-picker-caption';caption.textContent='Mais usados por você';list.appendChild(caption);frequent.forEach(item=>addEntry(item));const divider=document.createElement('div');divider.className='dtf-paper-picker-divider';list.appendChild(divider)}
 const caption=document.createElement('div');caption.className='dtf-paper-picker-caption';caption.textContent='Formatos de papel';list.appendChild(caption);PAPER_PRESETS.forEach(item=>addEntry(item));paperPickerMenu.append(list,preview);const selected=PAPER_PRESETS.find(item=>item.id===paperPresetSelect.value)||null;updatePaperPickerPreview(preview,selected);paperPickerMenu.classList.add('show');paperPickerButton.setAttribute('aria-expanded','true');positionPaperPickerMenu()
}
if(paperPresetSelect){renderPaperPresetList();paperPresetSelect.addEventListener('change',()=>{const preset=PAPER_PRESETS.find(item=>item.id===paperPresetSelect.value);syncPaperPickerControl(paperPresetSelect.value);if(!preset)return;ui.workW.value=String(Number(mmToUnit(preset.w).toFixed(2)));ui.workH.value=String(Number(mmToUnit(preset.h).toFixed(2)));const usage=readPaperUsage();usage[preset.id]=(Number(usage[preset.id])||0)+1;writePaperUsage(usage);renderPaperPresetList(preset.id);ui.applyWork.click();setStatus('Formato '+preset.name+' aplicado: '+preset.w+' × '+preset.h+' mm')})}
if(paperPickerButton){paperPickerButton.addEventListener('click',()=>{if(paperPickerMenu&&paperPickerMenu.classList.contains('show'))closePaperPicker();else openPaperPicker()});document.addEventListener('pointerdown',event=>{if(paperPickerMenu&&paperPickerMenu.classList.contains('show')&&!paperPickerMenu.contains(event.target)&&event.target!==paperPickerButton&&!paperPickerButton.contains(event.target))closePaperPicker()},{capture:true});window.addEventListener('resize',closePaperPicker)}
// Biblioteca de desenhos em janela própria, no estilo do seletor de fontes.
const drawingOpenButton=$id('dtfDrawingOpenLibrary');
const freeSvg=(body)=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="120" height="120" fill="none" stroke="#087eae" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">'+body+'</svg>';
const FREE_IMAGE_ELEMENTS={
 icons:[['Coração','<path d="M60 101 20 66C2 49 12 20 35 20c13 0 21 8 25 16 4-8 12-16 25-16 23 0 33 29 15 46z"/>'],['Estrela','<path d="m60 13 13 32 35 2-27 22 9 35-30-19-30 19 9-35L12 47l35-2z"/>'],['Casa','<path d="m16 55 44-37 44 37v48H72V72H48v31H16z"/>'],['Câmera','<rect x="15" y="33" width="90" height="62" rx="9"/><path d="M39 33 47 20h26l8 13"/><circle cx="60" cy="64" r="18"/>'],['Telefone','<path d="M33 18h17l9 23-12 9c8 16 14 22 30 30l9-12 23 9v17c0 7-6 12-13 12C53 106 14 67 14 24c0-7 5-12 12-12z"/>'],['Envelope','<rect x="13" y="28" width="94" height="64" rx="7"/><path d="m16 34 44 35 44-35"/>'],['Mapa','<path d="m17 27 28-11 30 11 28-11v77L75 104 45 93l-28 11z"/><path d="M45 16v77M75 27v77"/>'],['Sacola','<path d="M26 41h68l-5 62H31zM43 43v-9a17 17 0 0 1 34 0v9"/>'],['Música','<path d="M77 23v58a14 14 0 1 1-8-13V38l31-8v47a14 14 0 1 1-8-13V20z"/>'],['Calendário','<rect x="18" y="24" width="84" height="78" rx="8"/><path d="M18 48h84M39 15v18M81 15v18M38 67h1M60 67h1M82 67h1M38 85h1M60 85h1"/>'],['Foguete','<path d="M67 15c24 7 35 29 30 56L76 92 51 67l20-21c10-10 6-21-4-31z"/><path d="m52 69-20 6 13-19M76 93l-6 20 19-13M53 91l-15 15"/><circle cx="76" cy="42" r="8"/>'],['Sorriso','<circle cx="60" cy="60" r="43"/><path d="M38 70c12 16 32 16 44 0M43 45h1M77 45h1"/>']],
 cliparts:[['Sol','<circle cx="60" cy="60" r="20" fill="#ffd34d"/><path d="M60 12v18M60 90v18M12 60h18M90 60h18M26 26l13 13M81 81l13 13M94 26 81 39M39 81 26 94"/>'],['Flor','<circle cx="60" cy="60" r="12" fill="#ffd34d"/><circle cx="60" cy="32" r="17" fill="#f582b0"/><circle cx="84" cy="48" r="17" fill="#f582b0"/><circle cx="75" cy="76" r="17" fill="#f582b0"/><circle cx="45" cy="76" r="17" fill="#f582b0"/><circle cx="36" cy="48" r="17" fill="#f582b0"/><path d="M60 72v36" stroke="#469b54"/>'],['Balão','<path d="M60 13c24 0 38 17 38 38 0 27-24 42-38 56-14-14-38-29-38-56 0-21 14-38 38-38z" fill="#ff7c8c"/><path d="M51 105h18l-9 10z"/>'],['Coroa','<path d="m18 91 8-55 47 57l13-35 13 35 21-21 8 55z" fill="#ffd34d"/><path d="M18 91h84v16H18z"/>'],['Presente','<rect x="20" y="48" width="80" height="56" rx="4" fill="#8fcdff"/><path d="M60 48v56M16 36h88v17H16zM60 36c-16-24-35-18-28-5 6 11 28 5 28 5zm0 0c16-24 35-18 28-5-6 11-28 5-28 5z" fill="#ff7c8c"/>'],['Folha','<path d="M98 19C48 22 21 50 22 99c48 0 76-27 76-80z" fill="#77c77a"/><path d="M27 94 90 29M44 76 58 76M62 57 62 70"/>'],['Nuvem','<path d="M30 89h60c23 0 25-33 4-37C86 26 52 26 44 52 23 50 13 82 30 89z" fill="#bce9ff"/>'],['Arco-íris','<path d="M20 98a40 40 0 0 1 80 0" stroke="#ef6b6b" stroke-width="13"/><path d="M33 98a27 27 0 0 1 54 0" stroke="#ffd34d" stroke-width="13"/><path d="M46 98a14 14 0 0 1 28 0" stroke="#72b7ff" stroke-width="13"/>']],
 illustrations:[['Computador','<rect x="20" y="24" width="80" height="56" rx="6" fill="#dff2ff"/><path d="M48 101h24M60 80v21M34 97h52"/><circle cx="45" cy="52" r="10" fill="#ffd34d"/><path d="m68 54 9 9 18-22" stroke="#4ba765"/>'],['Equipe','<circle cx="42" cy="38" r="13" fill="#ffd34d"/><circle cx="79" cy="38" r="13" fill="#f582b0"/><path d="M20 95c0-20 10-33 22-33s22 13 22 33M58 95c0-20 9-33 21-33s21 13 21 33" fill="#bce9ff"/>'],['Ideia','<path d="M60 15c-20 0-33 15-33 33 0 12 6 20 14 27 4 4 5 9 5 16h28c0-7 1-12 5-16 8-7 14-15 14-27 0-18-13-33-33-33z" fill="#ffd34d"/><path d="M48 103h24M50 92h20"/>'],['Gráfico','<path d="M20 99h80M30 91V64h16v27M52 91V43h16v48M74 91V27h16v64" fill="#8fcdff"/><path d="m25 55 22-17 18 8 28-25" stroke="#4ba765"/>'],['Entrega','<rect x="27" y="43" width="50" height="43" rx="4" fill="#ffd34d"/><path d="M77 55h17l10 14v17H77z" fill="#8fcdff"/><circle cx="43" cy="92" r="8" fill="#4b6272"/><circle cx="88" cy="92" r="8" fill="#4b6272"/><path d="M35 43v-9h34v9"/>'],['Marketing','<path d="m23 57 48-21v48L23 63z" fill="#f582b0"/><path d="M71 46h17l11 11-11 11H71M36 70l6 26h15l-6-21"/><path d="M94 36l10-10M94 78l10 10M101 57h12"/>']]
};
const FREE_LIBRARY_CATEGORIES={
 photos:[['natureza','Natureza','natureza'],['pessoas','Pessoas','pessoas'],['estilo','Estilo de Vida','estilo de vida'],['tecnologia','Tecnologia','tecnologia'],['paisagens','Paisagens','paisagens'],['comida','Comida','comida'],['fundos','Fundos e Texturas','fundos texturas'],['produtos','Produtos','produtos'],['viagens','Viagens','viagens'],['animais','Animais','animais'],['diversos','Diversos','destaques']],
 icons:[['destaques','Destaques',''],['interface','Interface','interface'],['negocios','Negócios','negócios'],['tecnologia','Tecnologia','tecnologia'],['comunicacao','Comunicação','comunicação'],['midia','Mídia','mídia'],['setas','Setas','setas'],['pessoas','Pessoas','pessoas'],['lugares','Lugares','lugares'],['diversos','Diversos','diversos']],
 cliparts:[['natureza','Natureza','natureza'],['pessoas','Pessoas','pessoas'],['estilo','Estilo de Vida','estilo de vida'],['tecnologia','Tecnologia','tecnologia'],['paisagens','Paisagens','paisagens'],['comida','Comida','comida'],['fundos','Fundos e Texturas','fundos'],['produtos','Produtos','produtos'],['viagens','Viagens','viagens'],['animais','Animais','animais'],['diversos','Diversos','clipart']],
 illustrations:[['destaques','Destaques','ilustração'],['pessoas','Pessoas','pessoas ilustração'],['negocios','Negócios','negócios ilustração'],['tecnologia','Tecnologia','tecnologia ilustração'],['educacao','Educação','educação ilustração'],['saude','Saúde','saúde ilustração'],['comida','Comida','comida ilustração'],['viagens','Viagens','viagem ilustração'],['natureza','Natureza','natureza ilustração'],['diversos','Diversos','ilustração']]
};
let freeElementsModal=null,freeElementsGrid=null,freeElementsInput=null,freeElementsTitle=null,freeElementsStatus=null,freeElementsTabs=null,freeElementsModeTabs=null,freeElementsKind='icons',freeElementsCategory='destaques',freeElementsPage=0,freeElementsHasMore=false,freeElementsLoading=false,freeElementsRequest=0,freeElementsIds=new Set(),freeElementsIconItems=null,freeElementsMatching=[],freeElementsVisible=0,freeElementsInputTimer=0;
const FREE_ELEMENTS_PAGE_SIZE=72;
function freeElementItems(kind){return(FREE_IMAGE_ELEMENTS[kind]||[]).map(([name,body],index)=>({id:'local-'+kind+'-'+index,name,svg:freeSvg(body)}))}
const BUILTIN_ICON_GROUPS={
 interface:['arrow-left','arrow-right','arrow-up','arrow-down','menu','search','settings','home','grid-2x2','filter','check','x','plus','minus'],
 negocios:['briefcase','chart-bar','wallet','receipt','calculator','store','shopping-cart','package','tag','credit-card','megaphone','handshake','trending-up','landmark'],
 tecnologia:['computer','laptop','smartphone','tablet','server','cloud','cpu','database','code','wifi','monitor','printer','keyboard','mouse'],
 comunicacao:['message-circle','mail','phone','video','bell','send','share-2','microphone','headphones','at-sign','radio','rss','messages-square'],
 midia:['music','play','pause','camera','image','film','volume-2','list-video','podcast','clapperboard','palette','pen-tool','mic-2','album'],
 setas:['arrow-left','arrow-right','arrow-up','arrow-down','arrow-up-left','arrow-up-right','arrow-down-left','arrow-down-right','chevron-left','chevron-right','chevron-up','chevron-down','move','corner-up-left','corner-up-right','refresh-cw','download','upload'],
 pessoas:['user','users','user-plus','contact','smile','baby','accessibility','badge-check','id-card','shield-user','hand','heart-handshake','circle-user','user-round'],
 lugares:['map','map-pin','navigation','compass','globe-2','building-2','hotel','store','car','plane','train','bus','bike','ship-wheel'],
 diversos:['heart','star','sun','moon','gift','calendar','clock','bookmark','flag','lightbulb','sparkles','shield','key','lock','folder','file','circle-help','zap']
};
function builtInIconBody(id){
 const arrowBodies={
  'arrow-left':'<path d="M102 60H20M49 28 17 60l32 32"/>','arrow-right':'<path d="M18 60h82M71 28l32 32-32 32"/>','arrow-up':'<path d="M60 102V18M28 49l32-32 32 32"/>','arrow-down':'<path d="M60 18v84M28 71l32 32 32-32"/>',
  'arrow-up-left':'<path d="m95 95-66-66M29 67V29h38"/>','arrow-up-right':'<path d="m25 95 66-66M53 29h38v38"/>','arrow-down-left':'<path d="m95 25-66 66M29 53v38h38"/>','arrow-down-right':'<path d="m25 25 66 66M53 91h38V53"/>',
  'chevron-left':'<path d="m76 20-40 40 40 40"/>','chevron-right':'<path d="m44 20 40 40-40 40"/>','chevron-up':'<path d="m20 76 40-40 40 40"/>','chevron-down':'<path d="m20 44 40 40 40-40"/>',
  'move':'<path d="M60 14v92M14 60h92M43 31l17-17 17 17M43 89l17 17 17-17M31 43 14 60l17 17M89 43l17 17-17 17"/>',
  'corner-up-left':'<path d="M96 94H53a25 25 0 0 1-25-25V28M52 51 28 27 4 51"/>','corner-up-right':'<path d="M24 94h43a25 25 0 0 0 25-25V28M68 51l24-24 24 24"/>',
  'refresh-cw':'<path d="M94 47a38 38 0 1 0 5 26M94 22v27H67"/>','download':'<path d="M60 14v62M36 54l24 24 24-24M22 101h76"/>','upload':'<path d="M60 78V16M36 40l24-24 24 24M22 101h76"/>'
 };
 if(arrowBodies[id])return arrowBodies[id];
 const special={
  'map':'<path d="m16 29 31-13 28 13 29-13v76L75 105 47 92l-31 13zM47 16v76M75 29v76"/>','map-pin':'<path d="M60 108S27 75 27 48a33 33 0 1 1 66 0c0 27-33 60-33 60z"/><circle cx="60" cy="48" r="10"/>','navigation':'<path d="m103 17-27 86-18-36-36-18zM58 67l18-36"/>','compass':'<circle cx="60" cy="60" r="43"/><path d="m78 42-11 29-29 11 11-29z"/>','globe-2':'<circle cx="60" cy="60" r="44"/><path d="M16 60h88M60 16c15 13 22 29 22 44S75 91 60 104C45 91 38 75 38 60s7-31 22-44"/>','building-2':'<path d="M24 105V23h52v82M76 47h20v58M37 39h8M55 39h8M37 58h8M55 58h8M37 77h8M55 77h8M19 105h82"/>','hotel':'<path d="M16 94V44M16 72h88v22M31 72V55h28a14 14 0 0 1 14 14v3M16 101h88"/>','store':'<path d="M18 49h84v56H18zM14 49l8-25h76l8 25M18 49c7 10 17 10 24 0 7 10 17 10 24 0 7 10 17 10 24 0"/><path d="M34 105V72h25v33M75 69h14"/>','car':'<path d="m18 79 10-31h64l10 31v19H18zM31 48l8-17h42l8 17M35 98a8 8 0 1 0 0-16 8 8 0 0 0 0 16m50 0a8 8 0 1 0 0-16 8 8 0 0 0 0 16M18 72h84"/>','plane':'<path d="m14 66 91-35 1 13-39 24 19 25-12 5-28-23-18 11v12l-9 4-3-21z"/>','train':'<rect x="28" y="16" width="64" height="76" rx="12"/><path d="M28 47h64M42 105l10-13m26 13-10-13M43 64h1m33 0h1"/>','bus':'<rect x="24" y="18" width="72" height="78" rx="10"/><path d="M24 49h72M42 105l8-9m28 9-8-9M42 67h1m35 0h1"/>','bike':'<circle cx="31" cy="84" r="18"/><circle cx="89" cy="84" r="18"/><path d="m31 84 23-44 18 44H43m11-44h17m-1 0 11-12M52 56h21"/>','ship-wheel':'<circle cx="60" cy="60" r="20"/><circle cx="60" cy="60" r="43"/><path d="M60 17v26M103 60H77M60 103V77M17 60h26"/>'
 };
 if(special[id])return special[id];
 if(/arrow|chevron|corner/.test(id))return '<path d="M92 60H28M56 32 28 60l28 28"/><path d="M92 25v70"/>';
 if(/user|users|contact|baby|accessibility|hand/.test(id))return '<circle cx="60" cy="42" r="19"/><path d="M25 101c3-23 18-35 35-35s32 12 35 35"/><path d="M90 39h16M98 31v16"/>';
 if(/map|pin|navigation|compass|globe|plane|train|bus|car|bike|ship|building|hotel|store/.test(id))return '<path d="m21 28 30-12 30 12 18-8v72l-18 8-30-12-30 12z"/><path d="M51 16v76M81 28v76"/><circle cx="69" cy="47" r="7"/>';
 if(/message|mail|phone|send|share|microphone|headphones|bell|radio|rss|at-sign/.test(id))return '<rect x="18" y="29" width="84" height="59" rx="10"/><path d="m23 37 37 28 37-28M60 65v27"/><circle cx="60" cy="100" r="3"/>';
 if(/music|play|pause|camera|image|film|volume|video|podcast|clapper|palette|pen|album/.test(id))return '<rect x="16" y="25" width="88" height="70" rx="8"/><path d="m51 44 30 16-30 16z"/><circle cx="35" cy="43" r="5"/>';
 if(/briefcase|wallet|receipt|calculator|cart|package|tag|credit|chart|trending|landmark|handshake|megaphone/.test(id))return '<rect x="18" y="38" width="84" height="58" rx="7"/><path d="M43 38v-9h34v9M18 62h84M55 68h10"/><path d="m71 52 9-9 12 12"/>';
 if(/computer|laptop|smartphone|tablet|server|cloud|cpu|database|code|wifi|monitor|printer|keyboard|mouse/.test(id))return '<rect x="18" y="23" width="84" height="58" rx="7"/><path d="M45 102h30M60 81v21M33 92h54"/><path d="m45 51 10-10m10 0 10 10m-10-10v21"/>';
 if(/heart/.test(id))return '<path d="M60 101 20 66C2 49 12 20 35 20c13 0 21 8 25 16 4-8 12-16 25-16 23 0 33 29 15 46z"/>';
 if(/star|spark|zap|sun|moon|lightbulb/.test(id))return '<circle cx="60" cy="60" r="18"/><path d="M60 10v20M60 90v20M10 60h20M90 60h20M25 25l14 14M81 81 95 95M95 25 81 39M39 81 25 95"/>';
 if(/calendar|clock/.test(id))return '<rect x="20" y="25" width="80" height="76" rx="8"/><path d="M20 49h80M41 14v21M79 14v21M60 65v20l14 8"/>';
 if(/home|folder|file|bookmark|flag|key|lock|shield|help/.test(id))return '<path d="M20 101V48l40-31 40 31v53H72V72H48v29z"/><path d="M83 27h18v26H83z"/>';
 return '<rect x="20" y="20" width="80" height="80" rx="14"/><path d="M39 60h42M60 39v42"/>';
}
function iconLabel(id){return String(id).split('-').map(word=>({arrow:'Seta',left:'Esquerda',right:'Direita',up:'Acima',down:'Abaixo',briefcase:'Maleta',chart:'Gráfico',bar:'Barras',wallet:'Carteira',receipt:'Recibo',calculator:'Calculadora',store:'Loja',shopping:'Compras',cart:'Carrinho',package:'Pacote',credit:'Cartão',card:'Crédito',computer:'Computador',laptop:'Notebook',smartphone:'Celular',tablet:'Tablet',server:'Servidor',cloud:'Nuvem',database:'Banco de dados',code:'Código',message:'Mensagem',circle:'Círculo',mail:'E-mail',phone:'Telefone',video:'Vídeo',bell:'Sino',send:'Enviar',share:'Compartilhar',microphone:'Microfone',headphones:'Fones',music:'Música',play:'Reproduzir',pause:'Pausar',camera:'Câmera',image:'Imagem',film:'Filme',map:'Mapa',pin:'Local',navigation:'Navegação',compass:'Bússola',globe:'Globo',building:'Prédio',plane:'Avião',train:'Trem',user:'Usuário',users:'Pessoas',plus:'Adicionar',heart:'Coração',star:'Estrela',sun:'Sol',moon:'Lua',gift:'Presente',calendar:'Calendário',clock:'Relógio',bookmark:'Marcador',flag:'Bandeira',lightbulb:'Ideia',sparkles:'Brilhos',shield:'Escudo',key:'Chave',lock:'Cadeado',folder:'Pasta',file:'Arquivo',menu:'Menu',search:'Buscar',settings:'Configurações',filter:'Filtro',check:'Confirmar',grid:'Grade',move:'Mover',download:'Baixar',upload:'Enviar'})[word]||word).join(' ')}
function freeIconFallbackCatalog(){return Object.entries(BUILTIN_ICON_GROUPS).flatMap(([group,ids])=>ids.map((id,index)=>({id:'builtin-'+group+'-'+id+'-'+index,name:iconLabel(id),search:[id,iconLabel(id),group,freeIconPortugueseAliases(id)].join(' '),svg:freeSvg(builtInIconBody(id))}))) }
function freeCategoryList(kind){return FREE_LIBRARY_CATEGORIES[kind]||FREE_LIBRARY_CATEGORIES.icons}
function currentFreeCategory(){return freeCategoryList(freeElementsKind).find(item=>item[0]===freeElementsCategory)||freeCategoryList(freeElementsKind)[0]}
function freeElementTitle(){return 'Biblioteca de imagem'}
function setFreeElementsStatus(text){if(freeElementsStatus)freeElementsStatus.textContent=text||''}
function closeFreeElementsLibrary(){if(freeElementsModal)freeElementsModal.classList.remove('show')}
function showFreeElementInsertPeek(card){
 if(!card||!viewport)return;
 card.classList.add('is-inserted');
 const source=card.getBoundingClientRect(),target=viewport.getBoundingClientRect();
 if(!source.width||!source.height||!target.width||!target.height)return;
 const flyer=card.cloneNode(true),destinationX=target.left+target.width*.5,destinationY=target.top+target.height*.5;
 flyer.className='dtf-free-element-flyer';
 flyer.setAttribute('aria-hidden','true');
 flyer.style.left=source.left+'px';flyer.style.top=source.top+'px';flyer.style.width=source.width+'px';flyer.style.height=source.height+'px';
 document.body.appendChild(flyer);
 const translateX=destinationX-(source.left+source.width/2),translateY=destinationY-(source.top+source.height/2);
 requestAnimationFrame(()=>{flyer.style.transform='translate('+translateX+'px,'+translateY+'px) scale(.38) rotate(3deg)';flyer.classList.add('is-flying')});
 window.setTimeout(()=>flyer.remove(),520);
}
function renderFreeElementTabs(){if(!freeElementsTabs)return;freeElementsTabs.replaceChildren();freeCategoryList(freeElementsKind).forEach(category=>{const button=document.createElement('button');button.type='button';button.textContent=category[1];button.classList.toggle('active',category[0]===freeElementsCategory);button.addEventListener('click',()=>{freeElementsCategory=category[0];freeElementsInput.value=category[2];renderFreeElementTabs();searchFreeElements(false)});freeElementsTabs.appendChild(button)})}
function renderFreeElementModeTabs(){if(!freeElementsModeTabs)return;const modes=[['photos','Fotos','<rect x="4" y="5" width="16" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m5.5 17 4.6-4 3.1 2.6 2.4-2 3 3.4"/>'],['illustrations','Ilustrações','<path d="M6 19V9l6-4 6 4v10"/><path d="M9 19v-5h6v5M4 19h16"/><circle cx="18" cy="6" r="2"/>'],['cliparts','Cliparts','<circle cx="12" cy="12" r="3"/><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/>'],['icons','Ícones','<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>']];freeElementsModeTabs.replaceChildren();modes.forEach(([kind,label,body])=>{const button=document.createElement('button');button.type='button';button.classList.toggle('active',kind===freeElementsKind);button.title=label;button.setAttribute('aria-label',label);button.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">'+body+'</svg><span>'+label+'</span>';button.addEventListener('click',()=>openFreeElementsLibrary(kind));freeElementsModeTabs.appendChild(button)})}
function ensureFreeElementModeTabs(){if(freeElementsModeTabs||!freeElementsModal)return;const header=freeElementsModal.querySelector('.dtf-drawing-library-header'),close=header&&header.querySelector('.dtf-drawing-library-close');if(!header||!close)return;freeElementsModeTabs=document.createElement('nav');freeElementsModeTabs.className='dtf-free-elements-mode-tabs';freeElementsModeTabs.setAttribute('aria-label','Tipo de conteúdo');header.insertBefore(freeElementsModeTabs,close)}
function makeFreeElementCard(item){const card=document.createElement('button');card.type='button';card.className='dtf-free-element-card';card.title='Dê dois cliques para inserir '+item.name;card.setAttribute('aria-label','Inserir '+item.name);if(item.svg)card.innerHTML=item.svg;else {const image=document.createElement('img');image.src=item.thumbnail||item.url||'';image.alt='';image.loading='lazy';image.referrerPolicy='no-referrer';card.appendChild(image)}card.addEventListener('dblclick',()=>insertFreeElement(item,card));card.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();insertFreeElement(item,card)}});return card}
function appendFreeElementCards(items){if(!freeElementsGrid)return;items.forEach(item=>{const id=String(item.id||item.url||item.name);if(!id||freeElementsIds.has(id))return;freeElementsIds.add(id);freeElementsGrid.appendChild(makeFreeElementCard(item))})}
function renderIconPage(){const items=freeElementsMatching.slice(freeElementsVisible,freeElementsVisible+FREE_ELEMENTS_PAGE_SIZE);appendFreeElementCards(items);freeElementsVisible+=items.length;freeElementsHasMore=freeElementsVisible<freeElementsMatching.length;const total=freeElementsMatching.length;if(!total)setFreeElementsStatus('Nenhum ícone encontrado.');else if(freeElementsHasMore)setFreeElementsStatus(freeElementsVisible+' de '+total+' ícones carregados. Role para ver mais.');else setFreeElementsStatus(total+' ícones carregados. Você chegou ao fim.')}
function freeIconSearchTerms(value){const translations={coracao:'heart',coração:'heart',estrela:'star',casa:'house home',telefone:'phone',celular:'smartphone mobile',mensagem:'message mail',envelope:'mail',camera:'camera',câmera:'camera',usuario:'user',pessoa:'user',pessoas:'users',configuracao:'settings',configuração:'settings',lupa:'search',seta:'arrow',setas:'arrow',calendario:'calendar',calendário:'calendar',carrinho:'shopping cart',dinheiro:'wallet dollar',negocios:'briefcase',negócios:'briefcase',tecnologia:'computer cpu',musica:'music',música:'music',mapa:'map',localizacao:'map pin',localização:'map pin',aviao:'plane',avião:'plane',foto:'image',imagens:'image'};return String(value||'').toLocaleLowerCase('pt-BR').split(/\s+/).filter(Boolean).flatMap(term=>(translations[term]||term).split(/\s+/))}
async function loadFreeIconCatalog(){if(freeElementsIconItems)return freeElementsIconItems;const fallback=freeIconFallbackCatalog();try{if(!editorRoot.dataset.dtfMediaUrl||!editorRoot.dataset.dtfMediaNonce)throw new Error('Biblioteca indisponível');const request=drawingRequestData('dtf_uv_editor_icon_catalog'),response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:request,credentials:'same-origin'}),json=await response.json();if(!response.ok||!json.success)throw new Error(json&&json.data&&json.data.message?json.data.message:'Catálogo indisponível');const data=json.data||{},icons=data&&data.icons&&typeof data.icons==='object'?data.icons:{};freeElementsIconItems=Object.entries(icons).map(([name,icon])=>{const width=Number(icon.width||data.width||24),height=Number(icon.height||data.height||24),label=name.replace(/-/g,' ').replace(/\b\w/g,letter=>letter.toUpperCase()),body=String(icon.body||'');if(body.includes('</svg>'))return null;return{id:'lucide-'+name,name:label,search:name+' '+label,svg:'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+width+' '+height+'" fill="none" stroke="#087eae" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'+body+'</svg>'}}).filter(Boolean).sort((a,b)=>a.name.localeCompare(b.name,'pt-BR'));if(freeElementsIconItems.length)return freeElementsIconItems;throw new Error('Catálogo vazio')}catch(error){freeElementsIconItems=fallback;return freeElementsIconItems}}
function freeIconPortugueseAliases(name){const aliases={heart:'coracao coração',star:'estrela',house:'casa lar',home:'casa lar',camera:'camera câmera foto',phone:'telefone celular',mail:'envelope email mensagem',message:'mensagem conversa',user:'usuario usuário pessoa',users:'pessoas usuarios usuários',settings:'configuracao configuração',search:'lupa buscar',arrow:'seta setas',calendar:'calendario calendário',shopping:'carrinho compras',briefcase:'negocio negocios negócio negócios',computer:'computador tecnologia',cpu:'tecnologia processador',music:'musica música',map:'mapa localizacao localização',plane:'aviao avião viagem',image:'imagem imagens foto'};return Object.entries(aliases).filter(([term])=>name.includes(term)).map(([,value])=>value).join(' ')}
function freeIconCategoryTags(name){const value=String(name||'').toLowerCase(),tags=[];if(/arrow|chevron|menu|search|filter|settings|home|grid|check|circle-x|plus|minus|move|panel/.test(value))tags.push('interface');if(/arrow|chevron|corner|move|refresh|download|upload/.test(value))tags.push('setas');if(/briefcase|chart|wallet|receipt|calculator|store|shopping|cart|package|tag|credit|bank|landmark|handshake|trending|badge-dollar/.test(value))tags.push('negocios negócios');if(/computer|laptop|smartphone|tablet|server|cloud|cpu|database|code|wifi|monitor|printer|keyboard|mouse|hard-drive|terminal|circuit/.test(value))tags.push('tecnologia');if(/message|mail|phone|video|bell|send|share|microphone|headphone|radio|rss|at-sign|contact/.test(value))tags.push('comunicacao comunicação');if(/music|play|pause|camera|image|film|volume|podcast|clapper|palette|pen|album|mic|tv/.test(value))tags.push('midia mídia');if(/user|users|person|smile|baby|accessibility|hand|contact|id-card/.test(value))tags.push('pessoas');if(/map|pin|navigation|compass|globe|building|hotel|store|car|plane|train|bus|bike|ship|landmark/.test(value))tags.push('lugares');if(!tags.length)tags.push('diversos');return tags.join(' ')}
const loadFreeIconCatalogBase=loadFreeIconCatalog;loadFreeIconCatalog=async function(){const items=await loadFreeIconCatalogBase();items.forEach(item=>{const key=String(item.id||'')+' '+String(item.name||'');item.search=(item.search||item.name||'')+' '+freeIconPortugueseAliases(key)+' '+freeIconCategoryTags(key)});return items}
function renderLocalFallback(query){freeElementsGrid.replaceChildren();freeElementsIds=new Set();freeElementsMatching=freeElementItems(freeElementsKind).filter(item=>!query||item.name.toLocaleLowerCase('pt-BR').includes(query.toLocaleLowerCase('pt-BR')));freeElementsVisible=0;renderIconPage()}
async function insertFreeElement(item,card){if(card){card.classList.add('is-inserting');card.setAttribute('aria-busy','true')}try{let image;if(item.svg)image=await loadBlobImage(new Blob([item.svg],{type:'image/svg+xml'}));else {const data=drawingRequestData('dtf_uv_editor_media_proxy');data.append('url',String(item.url||''));const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'});if(!response.ok)throw new Error('Não foi possível baixar este elemento.');image=await loadBlobImage(await response.blob())}const object=imageToObject(image,item.name,'free-vector-'+freeElementsKind,{x:state.dpi,y:state.dpi});object.w=Math.min(object.w,canvas.width*.38);object.h=Math.min(object.h,canvas.height*.38);object.x=Math.max(0,Math.round((canvas.width-object.w)/2));object.y=Math.max(0,Math.round((canvas.height-object.h)/2));addObject(object);setStatus(item.name+' inserido.');showFreeElementInsertPeek(card)}catch(error){setFreeElementsStatus(error&&error.message?error.message:'Não foi possível inserir este elemento.')}finally{if(card){card.classList.remove('is-inserting');card.removeAttribute('aria-busy')}}}
async function searchFreeElements(append=false){if(!freeElementsGrid||!freeElementsInput)return;const query=freeElementsInput.value.trim(),loadMore=append===true;if(freeElementsLoading)return;if(freeElementsKind==='icons'){if(!loadMore){freeElementsGrid.replaceChildren();freeElementsIds=new Set();freeElementsVisible=0;setFreeElementsStatus('Carregando catálogo de ícones…');const items=await loadFreeIconCatalog();const terms=(query||currentFreeCategory()[2]||'').toLocaleLowerCase('pt-BR').split(/\s+/).filter(Boolean);freeElementsMatching=items.filter(item=>!terms.length||terms.every(term=>(item.search||item.name).toLocaleLowerCase('pt-BR').includes(term)));renderIconPage()}else renderIconPage();return}if(loadMore&&!freeElementsHasMore)return;if(!query){renderLocalFallback('');setFreeElementsStatus('Digite uma busca ou escolha uma categoria para pesquisar no Pixabay.');return}if(!editorRoot.dataset.dtfMediaUrl||!editorRoot.dataset.dtfMediaNonce){setFreeElementsStatus('A biblioteca precisa ser configurada pelo administrador.');return}if(!loadMore){freeElementsPage=0;freeElementsHasMore=true;freeElementsIds=new Set();freeElementsGrid.replaceChildren();freeElementsGrid.scrollTop=0}const request=++freeElementsRequest,page=freeElementsPage+1;freeElementsLoading=true;setFreeElementsStatus(page===1?'Buscando miniaturas…':'Carregando mais miniaturas…');try{const data=drawingRequestData('dtf_uv_editor_media_search');data.append('query',query);data.append('category',freeElementsKind);data.append('transparent','0');data.append('page',String(page));const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'}),json=await response.json();if(request!==freeElementsRequest)return;if(!response.ok||!json.success)throw new Error(json&&json.data&&json.data.message?json.data.message:'Não foi possível pesquisar agora.');const items=Array.isArray(json.data&&json.data.items)?json.data.items:[];appendFreeElementCards(items);freeElementsPage=page;freeElementsHasMore=Boolean(json.data&&json.data.has_more)&&items.length>0;const count=freeElementsIds.size;if(!count)setFreeElementsStatus('Nenhuma miniatura encontrada.');else if(freeElementsHasMore)setFreeElementsStatus(count+' miniaturas carregadas. Role para ver mais.');else setFreeElementsStatus(count+' miniaturas carregadas. Você chegou ao fim.')}catch(error){if(request===freeElementsRequest){renderLocalFallback(query);setFreeElementsStatus((error&&error.message?error.message:'Não foi possível pesquisar agora.')+' Mostrando a coleção inicial.')}}finally{if(request===freeElementsRequest)freeElementsLoading=false}}
const searchFreeElementsBase=searchFreeElements;
searchFreeElements=async function(append=false){
 if(freeElementsKind!=='icons')return searchFreeElementsBase(append);
 if(!freeElementsGrid||!freeElementsInput||freeElementsLoading)return;
 const loadMore=append===true;if(loadMore&&!freeElementsHasMore)return;
 const request=++freeElementsRequest;freeElementsLoading=true;
 if(!loadMore){freeElementsGrid.replaceChildren();freeElementsIds=new Set();freeElementsVisible=0;freeElementsGrid.scrollTop=0;setFreeElementsStatus('Carregando ícones…')}
 try{
  const query=freeElementsInput.value.trim(),items=await loadFreeIconCatalog();if(request!==freeElementsRequest)return;
  const terms=(query||currentFreeCategory()[2]||'').toLocaleLowerCase('pt-BR').split(/\s+/).filter(Boolean);
  freeElementsMatching=items.filter(item=>!terms.length||terms.every(term=>(item.search||item.name||'').toLocaleLowerCase('pt-BR').includes(term)));
  if(!loadMore)freeElementsVisible=0;renderIconPage();
 }catch(error){if(request===freeElementsRequest){freeElementsMatching=freeIconFallbackCatalog();freeElementsVisible=0;freeElementsHasMore=false;renderIconPage();setFreeElementsStatus('Coleção de ícones disponível para uso.')}}finally{if(request===freeElementsRequest)freeElementsLoading=false}
}
function ensureFreeElementsLibrary(){if(freeElementsModal)return;freeElementsModal=document.createElement('div');freeElementsModal.className='dtf-drawing-library-modal dtf-free-elements-modal';freeElementsModal.setAttribute('role','dialog');freeElementsModal.setAttribute('aria-modal','true');const box=document.createElement('section'),header=document.createElement('header'),close=document.createElement('button'),sidebar=document.createElement('aside'),body=document.createElement('div'),main=document.createElement('main'),search=document.createElement('div');box.className='dtf-drawing-library-box dtf-free-elements-box';header.className='dtf-drawing-library-header';freeElementsTitle=document.createElement('h2');close.type='button';close.className='dtf-drawing-library-close';close.textContent='×';close.setAttribute('aria-label','Fechar biblioteca');header.append(freeElementsTitle,close);sidebar.className='dtf-free-elements-sidebar';freeElementsTabs=document.createElement('nav');freeElementsTabs.className='dtf-free-elements-tabs';sidebar.append(freeElementsTabs);body.className='dtf-free-elements-body';search.className='dtf-free-elements-search';freeElementsInput=document.createElement('input');freeElementsInput.type='search';freeElementsInput.placeholder='Buscar em português';freeElementsInput.setAttribute('aria-label','Buscar elementos');const searchButton=document.createElement('button');searchButton.type='button';searchButton.textContent='Buscar';search.append(freeElementsInput,searchButton);freeElementsStatus=document.createElement('div');freeElementsStatus.className='dtf-free-elements-status';freeElementsStatus.setAttribute('role','status');freeElementsGrid=document.createElement('div');freeElementsGrid.className='dtf-free-elements-grid';body.append(freeElementsStatus,freeElementsGrid);main.className='dtf-free-elements-main';main.append(search,body);box.append(header,sidebar,main);freeElementsModal.append(box);document.body.append(freeElementsModal);close.addEventListener('click',closeFreeElementsLibrary);freeElementsModal.addEventListener('pointerdown',event=>{if(event.target===freeElementsModal)closeFreeElementsLibrary()});freeElementsModal.addEventListener('keydown',event=>{if(event.key==='Escape')closeFreeElementsLibrary()});searchButton.addEventListener('click',()=>searchFreeElements(false));freeElementsInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();searchFreeElements(false)}});freeElementsInput.addEventListener('input',()=>{window.clearTimeout(freeElementsInputTimer);freeElementsInputTimer=window.setTimeout(()=>searchFreeElements(false),260)});freeElementsGrid.addEventListener('wheel',event=>{event.stopPropagation();const range=Math.max(0,freeElementsGrid.scrollHeight-freeElementsGrid.clientHeight);if(!range){event.preventDefault();return}const factor=event.deltaMode===1?16:event.deltaMode===2?freeElementsGrid.clientHeight:1;freeElementsGrid.scrollTop=clamp(freeElementsGrid.scrollTop+event.deltaY*factor,0,range);event.preventDefault()},{passive:false});freeElementsGrid.addEventListener('scroll',()=>{const nearEnd=freeElementsGrid.scrollTop+freeElementsGrid.clientHeight>=freeElementsGrid.scrollHeight-180;if(nearEnd&&freeElementsHasMore&&!freeElementsLoading)searchFreeElements(true)})}
function openFreeElementsLibrary(kind){ensureFreeElementsLibrary();freeElementsKind=kind;freeElementsCategory=freeCategoryList(kind)[0][0];freeElementsTitle.textContent=freeElementTitle();ensureFreeElementModeTabs();renderFreeElementModeTabs();freeElementsInput.value=currentFreeCategory()[2];renderFreeElementTabs();freeElementsModal.classList.add('show');searchFreeElements(false);window.setTimeout(()=>freeElementsInput.focus(),0)}
const DRAWING_LIBRARY_CATEGORIES=[
 {id:'nature',label:'Natureza',query:'natureza'},
 {id:'people',label:'Pessoas',query:'pessoas'},
 {id:'lifestyle',label:'Estilo de Vida',query:'estilo de vida'},
 {id:'technology',label:'Tecnologia',query:'tecnologia'},
 {id:'landscapes',label:'Paisagens',query:'paisagens'},
 {id:'food',label:'Comida',query:'comida'},
 {id:'backgrounds',label:'Fundos e Texturas',query:'fundos e texturas'},
 {id:'products',label:'Produtos',query:'produtos'},
 {id:'travel',label:'Viagens',query:'viagem'},
 {id:'animals',label:'Animais',query:'animais'},
 {id:'various',label:'Diversos',query:'arte'}
];
const DRAWING_SEARCH_HISTORY_KEY='dtf-drawing-search-history-v1:'+String(editorRoot.dataset.dtfUserId||'guest');
let activeDrawingCategory='nature',drawingSearchRequest=0,drawingLibraryModal=null,drawingModalInput=null,drawingModalResults=null,drawingModalStatus=null,drawingModalSearchButton=null,drawingModalSettingsLink=null,drawingSearchHistoryPanel=null,drawingSearchHistoryList=null,drawingLibraryPeekTimer=0,drawingLibraryPage=0,drawingLibraryHasMore=true,drawingLibraryLoading=false,drawingLibraryQuery='',drawingLibraryIds=new Set();
function currentDrawingCategory(){return DRAWING_LIBRARY_CATEGORIES.find(item=>item.id===activeDrawingCategory)||DRAWING_LIBRARY_CATEGORIES[0]}
function setDrawingStatus(message){if(drawingModalStatus)drawingModalStatus.textContent=message;if(drawingModalSettingsLink)drawingModalSettingsLink.hidden=!/chave|configuraç/i.test(String(message||''))}
function drawingRequestData(action){const data=new FormData();data.append('action',action);data.append('nonce',editorRoot.dataset.dtfMediaNonce||'');return data}

const galleryUploadButton=$id('dtfGalleryUploadButton'),galleryRefreshButton=$id('dtfGalleryRefresh'),galleryUploadInput=$id('dtfGalleryUpload'),galleryStatus=$id('dtfGalleryStatus'),galleryGrid=$id('dtfGalleryGrid'),galleryQuota=$id('dtfGalleryQuota'),galleryQuotaLabel=$id('dtfGalleryQuotaLabel'),galleryQuotaBar=$id('dtfGalleryQuotaBar'),galleryQuotaFree=$id('dtfGalleryQuotaFree');
let galleryItems=[],galleryBusy=false,galleryLoaded=false;
function galleryRequestData(action){const data=new FormData();data.append('action',action);data.append('nonce',editorRoot.dataset.dtfMediaNonce||'');return data}
function setGalleryStatus(message,error=false){if(galleryStatus){galleryStatus.textContent=message||'';galleryStatus.classList.toggle('is-error',!!error)}}
async function galleryResponse(response){let payload=null;try{payload=await response.json()}catch(_){payload=null}if(!response.ok||!payload||!payload.success)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Não foi possível acessar a Galeria.');return payload.data||{}}
function gallerySizeLabel(bytes){const value=Number(bytes)||0;if(value<1024)return value+' B';if(value<1024*1024)return(Math.round(value/1024*10)/10)+' KB';if(value<1024*1024*1024)return(Math.round(value/1024/1024*10)/10)+' MB';return(Math.round(value/1024/1024/1024*100)/100)+' GB'}
function updateGalleryQuota(usage){if(!galleryQuota)return;const quota=Number(usage&&usage.quotaBytes)||0,used=Number(usage&&usage.usedBytes)||0,free=Number(usage&&usage.freeBytes);if(!quota){galleryQuota.hidden=true;return}const percent=Math.max(0,Math.min(100,Number(usage&&usage.usedPercent)||used/quota*100));galleryQuota.hidden=false;galleryQuotaLabel.textContent=percent.toLocaleString('pt-BR',{maximumFractionDigits:2})+'% usado · '+gallerySizeLabel(used)+' de '+gallerySizeLabel(quota);galleryQuotaBar.style.width=percent+'%';galleryQuotaBar.classList.toggle('is-warning',percent>=70&&percent<90);galleryQuotaBar.classList.toggle('is-full',percent>=90);galleryQuotaFree.textContent=gallerySizeLabel(Number.isFinite(free)?Math.max(0,free):Math.max(0,quota-used))+' livres para este usuário'}
async function insertGalleryImage(item,card){if(!item||galleryBusy)return;galleryBusy=true;if(card){card.classList.add('is-inserting');card.setAttribute('aria-busy','true')}try{const data=galleryRequestData('dtf_uv_editor_b2_download');data.append('fileName',String(item.fileName||''));const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'}),contentType=String(response.headers.get('content-type')||'').split(';')[0].toLowerCase(),raw=await response.blob();if(!response.ok){let detail='Não foi possível baixar a imagem da Galeria.';if(contentType==='application/json'||contentType.indexOf('text/')===0){try{const payload=JSON.parse(await raw.text());detail=payload&&payload.data&&payload.data.message?payload.data.message:detail}catch(_){}}throw new Error(detail)}if(contentType==='application/json'||contentType.indexOf('text/')===0)throw new Error('O servidor devolveu uma mensagem em vez da imagem.');const mime=contentType.indexOf('image/')===0?contentType:String(item.mime||raw.type||'image/png');const blob=raw.type&&raw.type.indexOf('image/')===0?raw:new Blob([raw],{type:mime});const image=await loadBlobImage(blob),object=imageToObject(image,String(item.name||'Imagem da Galeria').replace(/\.[^.]+$/,''),'gallery-b2',{x:state.dpi,y:state.dpi},{allowWorkspaceExpand:true});object.mediaProvider='Backblaze B2';object.mediaSourceUrl=String(item.url||'');object.x=Math.max(0,Math.round((canvas.width-object.w)/2));object.y=Math.max(0,Math.round((canvas.height-object.h)/2));addObject(object);render();fitWidthZoom(false);setGalleryStatus('Imagem inserida da Galeria.');setStatus('Imagem inserida da Galeria')}catch(error){setGalleryStatus(error&&error.message?error.message:'Não foi possível inserir a imagem.',true);recordEditorError(error,'Falha ao inserir imagem da Galeria')}finally{galleryBusy=false;if(card){card.classList.remove('is-inserting');card.removeAttribute('aria-busy')}}}
async function deleteGalleryItem(item,card){if(!item||galleryBusy)return;if(!window.confirm('Excluir “'+String(item.name||'imagem')+'” da sua Galeria?'))return;galleryBusy=true;if(card)card.classList.add('is-deleting');try{const data=galleryRequestData('dtf_uv_editor_b2_delete');data.append('fileId',String(item.id||''));data.append('fileName',String(item.fileName||''));await galleryResponse(await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'}));galleryItems=galleryItems.filter(entry=>entry.fileName!==item.fileName);renderGalleryItems();galleryLoaded=false;galleryBusy=false;await loadGallery(true);setGalleryStatus('Imagem excluída da Galeria.')}catch(error){setGalleryStatus(error&&error.message?error.message:'Não foi possível excluir a imagem.',true);recordEditorError(error,'Falha ao excluir imagem da Galeria')}finally{galleryBusy=false;if(card)card.classList.remove('is-deleting')}}
function renderGalleryItems(){if(!galleryGrid)return;galleryGrid.replaceChildren();if(!galleryItems.length){const empty=document.createElement('div');empty.className='dtf-gallery-empty';empty.textContent='Sua Galeria ainda está vazia. Clique em Enviar para adicionar imagens.';galleryGrid.appendChild(empty);return}galleryItems.forEach(item=>{const card=document.createElement('article'),image=document.createElement('img'),footer=document.createElement('div'),name=document.createElement('strong'),meta=document.createElement('small'),remove=document.createElement('button');card.className='dtf-gallery-card';card.tabIndex=0;card.title='Clique para inserir '+item.name;image.src=String(item.url||'');image.alt=item.name||'Imagem da Galeria';image.loading='lazy';image.referrerPolicy='no-referrer';name.textContent=item.name||'Imagem';meta.textContent=gallerySizeLabel(item.size);remove.type='button';remove.className='dtf-gallery-delete';remove.textContent='×';remove.title='Excluir da Galeria';remove.setAttribute('aria-label','Excluir '+(item.name||'imagem'));footer.className='dtf-gallery-card-footer';footer.append(name,meta,remove);card.append(image,footer);card.addEventListener('click',()=>insertGalleryImage(item,card));card.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();insertGalleryImage(item,card)}});remove.addEventListener('click',event=>{event.stopPropagation();deleteGalleryItem(item,card)});galleryGrid.appendChild(card)})}
async function loadGallery(force=false){if(!galleryGrid||galleryBusy||(!force&&galleryLoaded))return;galleryBusy=true;if(galleryRefreshButton)galleryRefreshButton.disabled=true;setGalleryStatus('Carregando sua Galeria…');try{const data=await galleryResponse(await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:galleryRequestData('dtf_uv_editor_b2_list'),credentials:'same-origin'}));galleryItems=Array.isArray(data.items)?data.items:[];galleryLoaded=true;updateGalleryQuota(data.usage||{});renderGalleryItems();setGalleryStatus(galleryItems.length?galleryItems.length+' imagem'+(galleryItems.length===1?'':'s')+' disponível'+(galleryItems.length===1?'':'eis')+'. Clique para inserir.':'Sua Galeria está vazia. Clique em Enviar para adicionar imagens.')}catch(error){galleryLoaded=false;galleryItems=[];renderGalleryItems();setGalleryStatus(error&&error.message?error.message:'Não foi possível carregar a Galeria.',true);recordEditorError(error,'Falha ao carregar a Galeria')}finally{galleryBusy=false;if(galleryRefreshButton)galleryRefreshButton.disabled=false}}
async function uploadGalleryFiles(files){const selected=Array.from(files||[]);if(!selected.length||galleryBusy)return;galleryBusy=true;if(galleryUploadButton)galleryUploadButton.disabled=true;try{for(let index=0;index<selected.length;index++){const file=selected[index];setGalleryStatus('Enviando '+file.name+' ('+(index+1)+'/'+selected.length+')…');const data=galleryRequestData('dtf_uv_editor_b2_upload');data.append('file',file,file.name);await galleryResponse(await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'}))}galleryLoaded=false;galleryBusy=false;await loadGallery(true)}catch(error){setGalleryStatus(error&&error.message?error.message:'Não foi possível enviar a imagem.',true);recordEditorError(error,'Falha ao enviar imagem para a Galeria')}finally{galleryBusy=false;if(galleryUploadButton)galleryUploadButton.disabled=false;if(galleryUploadInput)galleryUploadInput.value=''}}
if(galleryUploadButton&&galleryUploadInput)galleryUploadButton.addEventListener('click',()=>{galleryUploadInput.value='';galleryUploadInput.click()});if(galleryUploadInput)galleryUploadInput.addEventListener('change',event=>uploadGalleryFiles(event.target.files));if(galleryRefreshButton)galleryRefreshButton.addEventListener('click',()=>{galleryLoaded=false;loadGallery(true)});QA('[data-tab="galeria"]').forEach(button=>button.addEventListener('click',()=>loadGallery(false)));
function drawingTitle(item){return String(item&&item.title||'Desenho').trim()||'Desenho'}
function readDrawingSearchHistory(){try{const value=JSON.parse(localStorage.getItem(DRAWING_SEARCH_HISTORY_KEY)||'[]'),fixedQueries=new Set(DRAWING_LIBRARY_CATEGORIES.map(item=>item.query.toLocaleLowerCase('pt-BR')));return Array.isArray(value)?value.filter(item=>item&&typeof item.query==='string'&&item.query.trim()&&!fixedQueries.has(item.query.trim().toLocaleLowerCase('pt-BR'))):[]}catch(_){return[]}}
function writeDrawingSearchHistory(items){try{localStorage.setItem(DRAWING_SEARCH_HISTORY_KEY,JSON.stringify(items.slice(0,30)))}catch(_){}}
function drawingSearchHistoryRanked(){const now=Date.now();return readDrawingSearchHistory().map(item=>{const age=Math.max(0,(now-Number(item.last||0))/86400000),score=(Number(item.count)||1)*4+Math.max(0,8-age);return{...item,score}}).sort((a,b)=>b.score-a.score||Number(b.last||0)-Number(a.last||0))}
function renderDrawingSearchHistory(){if(!drawingSearchHistoryPanel||!drawingSearchHistoryList)return;const entries=drawingSearchHistoryRanked(),available=Math.max(0,(drawingSearchHistoryList.clientHeight||0)-2),capacity=Math.min(7,Math.floor(available/35));drawingSearchHistoryList.replaceChildren();drawingSearchHistoryPanel.hidden=!entries.length;if(!entries.length||!capacity)return;entries.slice(0,capacity).forEach(item=>{const button=document.createElement('button');button.type='button';button.textContent=item.query;button.title='Buscar novamente: '+item.query;button.addEventListener('click',()=>{drawingModalInput.value=item.query;searchDrawingLibrary(false)});drawingSearchHistoryList.appendChild(button)})}
function rememberDrawingSearch(query){const text=String(query||'').trim();if(!text)return;const normalized=text.toLocaleLowerCase('pt-BR'),fixedQueries=new Set(DRAWING_LIBRARY_CATEGORIES.map(item=>item.query.toLocaleLowerCase('pt-BR')));if(fixedQueries.has(normalized))return;const now=Date.now(),items=readDrawingSearchHistory(),index=items.findIndex(item=>String(item.query).toLocaleLowerCase('pt-BR')===normalized);if(index>=0){items[index].query=text;items[index].count=Math.max(1,Number(items[index].count)||1)+1;items[index].last=now}else items.push({query:text,count:1,last:now});items.sort((a,b)=>Number(b.last||0)-Number(a.last||0));writeDrawingSearchHistory(items);requestAnimationFrame(renderDrawingSearchHistory)}
function syncDrawingCategoryButtons(){if(drawingLibraryModal)drawingLibraryModal.querySelectorAll('[data-modal-drawing-category]').forEach(entry=>{const selected=entry.dataset.modalDrawingCategory===activeDrawingCategory;entry.classList.toggle('is-active',selected);entry.setAttribute('aria-selected',String(selected))})}
function closeDrawingLibraryModal(){if(drawingLibraryModal)drawingLibraryModal.classList.remove('show')}
function showDrawingInsertPeek(){if(!drawingLibraryModal)return;clearTimeout(drawingLibraryPeekTimer);drawingLibraryModal.classList.add('dtf-drawing-library-peek');drawingLibraryPeekTimer=window.setTimeout(()=>drawingLibraryModal&&drawingLibraryModal.classList.remove('dtf-drawing-library-peek'),2000)}
function ensureDrawingLibraryModal(){
 if(drawingLibraryModal)return;
 drawingLibraryModal=document.createElement('div');drawingLibraryModal.className='dtf-drawing-library-modal';drawingLibraryModal.setAttribute('role','dialog');drawingLibraryModal.setAttribute('aria-modal','true');drawingLibraryModal.setAttribute('aria-label','Biblioteca de desenhos');
 const box=document.createElement('section'),header=document.createElement('header'),title=document.createElement('h2'),close=document.createElement('button'),sidebar=document.createElement('aside'),categoryTabs=document.createElement('nav'),main=document.createElement('main'),searchRow=document.createElement('div'),content=document.createElement('div'),footer=document.createElement('footer');
 box.className='dtf-drawing-library-box dtf-drawing-library-box-v2';header.className='dtf-drawing-library-header';title.textContent='Biblioteca de desenhos';close.type='button';close.className='dtf-drawing-library-close';close.textContent='×';close.setAttribute('aria-label','Fechar biblioteca de desenhos');header.append(title,close);
 searchRow.className='dtf-drawing-library-search';drawingModalInput=document.createElement('input');drawingModalInput.type='search';drawingModalInput.lang='pt-BR';drawingModalInput.placeholder='Busque em português: flores, carro, logotipo…';drawingModalInput.setAttribute('aria-label','Buscar imagens');drawingModalSearchButton=document.createElement('button');drawingModalSearchButton.type='button';drawingModalSearchButton.textContent='Buscar';searchRow.append(drawingModalInput,drawingModalSearchButton);
 categoryTabs.className='dtf-drawing-library-category-tabs';categoryTabs.setAttribute('aria-label','Categorias de desenhos');DRAWING_LIBRARY_CATEGORIES.forEach(item=>{const button=document.createElement('button'),label=document.createElement('span');button.type='button';button.dataset.modalDrawingCategory=item.id;label.textContent=item.label;button.append(label);button.addEventListener('click',()=>{activeDrawingCategory=item.id;drawingModalInput.value=item.query;syncDrawingCategoryButtons();searchDrawingLibrary(false,false)});categoryTabs.appendChild(button)});
 sidebar.className='dtf-drawing-library-sidebar';drawingSearchHistoryPanel=document.createElement('section');drawingSearchHistoryPanel.className='dtf-drawing-search-history';const historyTitle=document.createElement('div');historyTitle.className='dtf-drawing-search-history-title';historyTitle.textContent='Pesquisas';drawingSearchHistoryList=document.createElement('div');drawingSearchHistoryList.className='dtf-drawing-search-history-list';drawingSearchHistoryPanel.append(historyTitle,drawingSearchHistoryList);sidebar.append(categoryTabs,drawingSearchHistoryPanel);
 content.className='dtf-drawing-library-content';drawingModalStatus=document.createElement('div');drawingModalStatus.className='dtf-drawing-library-status';drawingModalStatus.setAttribute('role','status');drawingModalResults=document.createElement('div');drawingModalResults.className='dtf-drawing-thumbnail-grid';drawingModalResults.setAttribute('aria-live','polite');content.append(drawingModalStatus,drawingModalResults);
 footer.className='dtf-drawing-library-footer';const providerLink=document.createElement('a');providerLink.href='https://www.pexels.com/';providerLink.target='_blank';providerLink.rel='noopener noreferrer';providerLink.textContent='Fotos fornecidas pelo Pexels';drawingModalSettingsLink=document.createElement('a');drawingModalSettingsLink.href=editorRoot.dataset.dtfMediaSettingsUrl||'#';drawingModalSettingsLink.textContent='Configurar chave do Pexels';drawingModalSettingsLink.hidden=true;footer.append(providerLink,drawingModalSettingsLink);
 main.className='dtf-drawing-library-main';main.append(searchRow,content,footer);box.append(header,sidebar,main);drawingLibraryModal.appendChild(box);document.body.appendChild(drawingLibraryModal);
 close.addEventListener('click',closeDrawingLibraryModal);drawingLibraryModal.addEventListener('pointerdown',event=>{if(event.target===drawingLibraryModal)closeDrawingLibraryModal()});drawingLibraryModal.addEventListener('keydown',event=>{if(event.key==='Escape')closeDrawingLibraryModal()});drawingModalSearchButton.addEventListener('click',()=>searchDrawingLibrary(false));drawingModalInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();searchDrawingLibrary(false)}});drawingModalResults.addEventListener('wheel',event=>{event.stopPropagation();const range=Math.max(0,drawingModalResults.scrollHeight-drawingModalResults.clientHeight);if(!range){event.preventDefault();return}const factor=event.deltaMode===1?16:event.deltaMode===2?drawingModalResults.clientHeight:1;drawingModalResults.scrollTop=clamp(drawingModalResults.scrollTop+event.deltaY*factor,0,range);event.preventDefault()},{passive:false});drawingModalResults.addEventListener('scroll',()=>{const nearEnd=drawingModalResults.scrollTop+drawingModalResults.clientHeight>=drawingModalResults.scrollHeight-180;if(nearEnd&&drawingLibraryHasMore&&!drawingLibraryLoading)searchDrawingLibrary(true)});window.addEventListener('resize',renderDrawingSearchHistory);renderDrawingSearchHistory();
}
function openDrawingLibraryModal(){ensureDrawingLibraryModal();if(!drawingModalInput.value.trim())drawingModalInput.value=currentDrawingCategory().query;syncDrawingCategoryButtons();drawingLibraryModal.classList.add('show');requestAnimationFrame(renderDrawingSearchHistory);searchDrawingLibrary(false,false);if(drawingModalInput)window.setTimeout(()=>drawingModalInput.focus(),0)}
function renderDrawingResults(items,append){
 const container=drawingModalResults;if(!container)return;if(!append){container.replaceChildren();drawingLibraryIds=new Set()}
 items.forEach(item=>{
  const itemId=String(item&&item.id||item&&item.url||'');if(!itemId||drawingLibraryIds.has(itemId))return;drawingLibraryIds.add(itemId);
  const card=document.createElement('article'),thumbnail=document.createElement('img');card.className='dtf-drawing-thumbnail';card.tabIndex=0;card.setAttribute('role','button');card.setAttribute('aria-label','Inserir '+drawingTitle(item));card.title=drawingTitle(item)+'. Dê dois cliques para inserir.';thumbnail.src=String(item.thumbnail||'');thumbnail.alt='';thumbnail.loading='lazy';thumbnail.referrerPolicy='no-referrer';card.append(thumbnail);card.addEventListener('dblclick',()=>insertDrawingItem(item,card));card.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();insertDrawingItem(item,card)}});container.appendChild(card);
 });
}
async function insertDrawingItem(item,card){
 if(!editorRoot.dataset.dtfMediaUrl){setDrawingStatus('A biblioteca não está disponível nesta instalação.');return}card.classList.add('is-inserting');card.setAttribute('aria-busy','true');setDrawingStatus('Baixando a imagem selecionada…');
 try{const data=drawingRequestData('dtf_uv_editor_media_proxy');data.append('url',String(item.url||''));const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'});if(!response.ok)throw new Error('Não foi possível baixar essa imagem.');const blob=await response.blob();if(!/^image\//i.test(blob.type))throw new Error('O banco devolveu um arquivo incompatível.');const image=await loadBlobImage(blob),object=imageToObject(image,drawingTitle(item),'library-'+String(item.provider||'image').toLowerCase(),{x:state.dpi,y:state.dpi});object.x=Math.max(0,Math.round((canvas.width-object.w)/2));object.y=Math.max(0,Math.round((canvas.height-object.h)/2));object.mediaProvider=String(item.provider||'');object.mediaAuthor=String(item.author||'');object.mediaSourceUrl=String(item.url||'');addObject(object);setStatus('Imagem inserida: '+drawingTitle(item));showDrawingInsertPeek();}catch(error){setDrawingStatus(error&&error.message?error.message:'Não foi possível inserir a imagem.')}finally{card.classList.remove('is-inserting');card.removeAttribute('aria-busy')}
}
async function searchDrawingLibrary(append,trackHistory){
 const container=drawingModalResults;if(!drawingModalInput||!container)return;const query=drawingModalInput.value.trim(),loadMore=append===true;if(drawingLibraryLoading){if(loadMore)return;drawingSearchRequest++;drawingLibraryLoading=false}
 if(!query){if(!loadMore)container.replaceChildren();setDrawingStatus('Digite uma busca em português para encontrar imagens.');drawingModalInput.focus();return}
 if(!editorRoot.dataset.dtfMediaUrl||!editorRoot.dataset.dtfMediaNonce){setDrawingStatus('A biblioteca precisa ser configurada pelo administrador.');return}
 if(loadMore&&(drawingLibraryQuery!==query||!drawingLibraryHasMore))return;
 if(!loadMore){drawingLibraryPage=0;drawingLibraryHasMore=true;drawingLibraryQuery=query;drawingLibraryIds=new Set();container.replaceChildren();container.scrollTop=0;if(trackHistory!==false)rememberDrawingSearch(query)}
 const page=drawingLibraryPage+1,request=++drawingSearchRequest;drawingLibraryLoading=true;setDrawingStatus(page===1?'Buscando imagens…':'Carregando mais imagens…');drawingModalSearchButton.disabled=true;
 try{const data=drawingRequestData('dtf_uv_editor_media_search');data.append('query',query);data.append('category','photos');data.append('transparent','0');data.append('page',String(page));const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'}),json=await response.json();if(request!==drawingSearchRequest)return;if(!response.ok||!json.success)throw new Error(json&&json.data&&json.data.message?json.data.message:'Não foi possível pesquisar agora.');const items=Array.isArray(json.data&&json.data.items)?json.data.items:[];renderDrawingResults(items,loadMore);drawingLibraryPage=page;drawingLibraryHasMore=Boolean(json.data&&json.data.has_more)&&items.length>0;const warnings=Array.isArray(json.data&&json.data.warnings)?json.data.warnings.filter(Boolean):[],count=drawingLibraryIds.size;if(!count)setDrawingStatus('Nenhuma imagem encontrada.'+(warnings.length?' '+warnings.join(' '):''));else if(drawingLibraryHasMore)setDrawingStatus(count+' miniaturas carregadas. Role para baixo para ver mais.'+(warnings.length?' '+warnings.join(' '):''));else setDrawingStatus(count+' miniaturas carregadas. Você chegou ao fim dos resultados.'+(warnings.length?' '+warnings.join(' '):''));}catch(error){if(request===drawingSearchRequest)setDrawingStatus(error&&error.message?error.message:'Não foi possível pesquisar agora.')}finally{if(request===drawingSearchRequest){drawingLibraryLoading=false;drawingModalSearchButton.disabled=false}}
}
const openEditorProjectWithImageOptions=openEditorProject;
openEditorProject=async function(file,handle=null){const result=await openEditorProjectWithImageOptions(file,handle);if(result){try{const data=JSON.parse(await file.text()),byId=new Map((Array.isArray(data.objects)?data.objects:[]).map(item=>[String(item.id||''),item]));state.objects.forEach(object=>{const item=byId.get(String(object.id||''));if(!item)return;object.blendMode=['source-over','multiply','screen','overlay','soft-light','difference'].includes(String(item.blendMode))?String(item.blendMode):'source-over';object.imageFilter=String(item.imageFilter||'none');object.imageEffect=String(item.imageEffect||'none');object.imageMask=String(item.imageMask||'none');object.flipX=item.flipX===true;object.flipY=item.flipY===true});render();updateSelection()}catch(_){} }return result};
function imageEditTargets(){return selectedObjects().filter(object=>object&&object.canvas&&!isLineShape(object))}
function imageHistory(label){pushHistory(label)}
function updateImageControlState(){const targets=imageEditTargets(),enabled=targets.length>0;[imageCropButton,imageSeparateButton,imageMaskSelect,imageFilterSelect,imageEffectSelect,imageOpacityRange,imageBlendSelect,rotateLeftButton,rotateRightButton,flipHButton,flipVButton,fitFillButton,fitContainButton,fitCenterButton,...imageVisualSubmenuButtons].forEach(control=>{if(control)control.disabled=!enabled});if(imageAiButton)imageAiButton.disabled=false;if(!enabled)return;const object=targets[targets.length-1];if(imageMaskSelect)imageMaskSelect.value=object.imageMask||'none';if(imageFilterSelect)imageFilterSelect.value=object.imageFilter||'none';if(imageEffectSelect)imageEffectSelect.value=object.imageEffect||'none';if(imageOpacityRange){imageOpacityRange.value=String(Math.round((Number(object.opacity==null?1:object.opacity))*100));imageOpacityValue.textContent=imageOpacityRange.value+'%'}if(imageBlendSelect)imageBlendSelect.value=object.blendMode||'source-over'}
function applyImageProperty(property,value,label){const targets=imageEditTargets();if(!targets.length){setStatus('Selecione uma imagem primeiro');return false}imageHistory(label);targets.forEach(object=>{object[property]=value});render();updateImageControlState();setStatus(label+' aplicado');return true}
function rotateSelectedImages(delta){const targets=imageEditTargets();if(!targets.length){setStatus('Selecione uma imagem primeiro');return}imageHistory('Girar imagem');targets.forEach(object=>{object.rotation=(Number(object.rotation)||0)+delta});render();setStatus('Imagem girada '+(delta<0?'para a esquerda':'para a direita'))}
function flipSelectedImages(axis){const targets=imageEditTargets();if(!targets.length){setStatus('Selecione uma imagem primeiro');return}imageHistory('Espelhar imagem');targets.forEach(object=>{if(axis==='x')object.flipX=!object.flipX;else object.flipY=!object.flipY});render();setStatus(axis==='x'?'Espelhamento horizontal aplicado':'Espelhamento vertical aplicado')}
function fitSelectedImages(mode){const targets=imageEditTargets();if(!targets.length){setStatus('Selecione uma imagem primeiro');return}imageHistory(mode==='fill'?'Preencher página':'Encaixar imagem na página');targets.forEach(object=>{const ratio=object.w/Math.max(1,object.h);if(mode==='center'){object.x=(canvas.width-object.w)/2;object.y=(canvas.height-object.h)/2;return}const scale=mode==='fill'?Math.max(canvas.width/Math.max(1,object.w),canvas.height/Math.max(1,object.h)):Math.min(canvas.width/Math.max(1,object.w),canvas.height/Math.max(1,object.h));object.w=Math.max(1,object.w*scale);object.h=Math.max(1,object.w/ratio);object.x=(canvas.width-object.w)/2;object.y=(canvas.height-object.h)/2});render();setStatus(mode==='fill'?'Imagem preenchendo a página':'Imagem ajustada à página')}
function applyCropFromModal(modal){const targets=imageEditTargets();if(!targets.length)return;const values={top:clamp(Number(String(modal.querySelector('[data-crop="top"]').value).replace(',','.'))||0,0,95),right:clamp(Number(String(modal.querySelector('[data-crop="right"]').value).replace(',','.'))||0,0,95),bottom:clamp(Number(String(modal.querySelector('[data-crop="bottom"]').value).replace(',','.'))||0,0,95),left:clamp(Number(String(modal.querySelector('[data-crop="left"]').value).replace(',','.'))||0,0,95)};if(values.left+values.right>=100||values.top+values.bottom>=100){modal.querySelector('[data-crop-error]').textContent='As margens somadas devem deixar uma área útil.';return}imageHistory('Recortar imagem');targets.forEach(object=>{const source=object.canvas,sw=source.width,sh=source.height,sx=Math.round(sw*values.left/100),sy=Math.round(sh*values.top/100),nw=Math.max(1,Math.round(sw*(1-(values.left+values.right)/100))),nh=Math.max(1,Math.round(sh*(1-(values.top+values.bottom)/100))),cropped=document.createElement('canvas');cropped.width=nw;cropped.height=nh;cropped.getContext('2d').drawImage(source,sx,sy,nw,nh,0,0,nw,nh);object.canvas=cropped;object.baseCanvas=canvasFromData(cloneCanvasData(cropped));object.restoreCanvas=canvasFromData(cloneCanvasData(cropped));object.originalW=nw;object.originalH=nh;object.w=Math.max(1,object.w*nw/sw);object.h=Math.max(1,object.h*nh/sh);object.x=clamp(object.x,0,Math.max(0,canvas.width-object.w));object.y=clamp(object.y,0,Math.max(0,canvas.height-object.h))});modal.remove();render();updateImageControlState();setStatus('Recorte aplicado')}
function openImageCropModal(){const targets=imageEditTargets();if(!targets.length){setStatus('Selecione uma imagem primeiro');return}const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-image-crop-modal';modal.innerHTML='<div class="dtf-properties-box dtf-image-crop-box"><h3>Recortar imagem</h3><p class="dtf-image-modal-help">Informe quanto deseja remover de cada lado, em porcentagem.</p><div class="dtf-image-crop-grid"><label>Superior (%)<input data-crop="top" type="number" min="0" max="95" step="1" value="0"></label><label>Inferior (%)<input data-crop="bottom" type="number" min="0" max="95" step="1" value="0"></label><label>Esquerda (%)<input data-crop="left" type="number" min="0" max="95" step="1" value="0"></label><label>Direita (%)<input data-crop="right" type="number" min="0" max="95" step="1" value="0"></label></div><small data-crop-error></small><div class="dtf-properties-actions"><button type="button" data-crop-cancel>Cancelar</button><button type="button" data-crop-apply>Recortar</button></div></div>';document.body.appendChild(modal);modal.querySelector('[data-crop-cancel]').onclick=()=>modal.remove();modal.querySelector('[data-crop-apply]').onclick=()=>applyCropFromModal(modal);modal.addEventListener('pointerdown',event=>{if(event.target===modal)modal.remove()});modal.querySelector('[data-crop="top"]').focus()}
function openAiImageModal(){const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-image-ai-modal';modal.innerHTML='<div class="dtf-properties-box dtf-image-ai-box"><h3>Gerar imagem com IA</h3><p class="dtf-image-modal-help">Descreva a imagem em português. A geração usa um serviço externo gratuito e pode levar alguns segundos.</p><textarea data-ai-prompt rows="4" placeholder="Ex.: uma paisagem brasileira ao pôr do sol, estilo fotografia profissional"></textarea><div class="dtf-image-ai-row"><label>Formato<select data-ai-format><option value="square">Quadrado</option><option value="landscape">Paisagem</option><option value="portrait">Retrato</option></select></label><label>Estilo<select data-ai-style><option>Realista</option><option>Ilustração</option><option>3D</option><option>Aquarela</option><option>Pixel art</option></select></label></div><small data-ai-status></small><div class="dtf-properties-actions"><button type="button" data-ai-cancel>Cancelar</button><button type="button" data-ai-generate>Gerar e inserir</button></div></div>';document.body.appendChild(modal);const prompt=modal.querySelector('[data-ai-prompt]'),status=modal.querySelector('[data-ai-status]'),generate=modal.querySelector('[data-ai-generate]');modal.querySelector('[data-ai-cancel]').onclick=()=>modal.remove();modal.addEventListener('pointerdown',event=>{if(event.target===modal)modal.remove()});generate.onclick=async()=>{const text=String(prompt.value||'').trim();if(!text){status.textContent='Digite uma descrição para gerar a imagem.';prompt.focus();return}generate.disabled=true;status.textContent='Gerando imagem…';try{const format=modal.querySelector('[data-ai-format]').value,style=modal.querySelector('[data-ai-style]').value,dimensions=format==='landscape'?[1024,768]:format==='portrait'?[768,1024]:[768,768],query=encodeURIComponent(text+', estilo '+style+', sem texto, alta qualidade');const response=await fetch('https://image.pollinations.ai/prompt/'+query+'?width='+dimensions[0]+'&height='+dimensions[1]+'&nologo=true',{cache:'no-store'});if(!response.ok)throw new Error('O serviço de geração não respondeu.');const image=await loadBlobImage(await response.blob()),object=imageToObject(image,'Imagem gerada por IA','ai-generated',{x:state.dpi,y:state.dpi},{allowWorkspaceExpand:true});object.w=Math.min(object.w,canvas.width*.72);object.h=Math.min(object.h,canvas.height*.72);object.x=Math.max(0,(canvas.width-object.w)/2);object.y=Math.max(0,(canvas.height-object.h)/2);addObject(object);modal.remove();setStatus('Imagem gerada e inserida na área de trabalho')}catch(error){status.textContent=error&&error.message?error.message:'Não foi possível gerar a imagem.';generate.disabled=false}};prompt.focus()}
let imageCropToolActive=false,imageCropDrag=null,imageCropResize=null,imageCropRect=null;
function imageCropPrimary(){const targets=imageEditTargets();return targets.length?targets[targets.length-1]:null}
function imageCropShow(rect){if(!rect)return;selection.classList.add('show','dtf-crop-live');selection.style.left=rect.x+'px';selection.style.top=rect.y+'px';selection.style.width=rect.w+'px';selection.style.height=rect.h+'px';selectionLabel.style.top=rect.y<32?(rect.h+6)+'px':'-34px';selectionLabel.textContent='Recorte • '+pxToMm(rect.w).toFixed(1)+' × '+pxToMm(rect.h).toFixed(1)+' mm'}
function imageCropCancel(quiet=false){imageCropToolActive=false;imageCropDrag=null;imageCropResize=null;imageCropRect=null;state.tool='select';selection.classList.remove('dtf-crop-live');if(imageCropButton){imageCropButton.classList.remove('active');imageCropButton.setAttribute('aria-pressed','false')}editorRoot.classList.remove('dtf-image-crop-active');if(!quiet)setStatus('Recorte cancelado')}
function imageCropApply(){const rect=imageCropRect,primary=imageCropPrimary();if(!rect||!primary||rect.w<3||rect.h<3){setStatus('Arraste uma área válida sobre a imagem');return false}const left=clamp((rect.x-primary.x)/Math.max(1,primary.w),0,1),top=clamp((rect.y-primary.y)/Math.max(1,primary.h),0,1),right=clamp((primary.x+primary.w-(rect.x+rect.w))/Math.max(1,primary.w),0,1),bottom=clamp((primary.y+primary.h-(rect.y+rect.h))/Math.max(1,primary.h),0,1);if(left+right>=.999||top+bottom>=.999){setStatus('A área de recorte é pequena demais');return false}const targets=imageEditTargets();imageHistory('Recortar imagem');targets.forEach(object=>{const from={x:object.x,y:object.y,w:object.w,h:object.h},to={x:object.x+left*object.w,y:object.y+top*object.h,w:object.w*(1-left-right),h:object.h*(1-top-bottom)},cropped=cropCanvasByObjectRect(cloneCanvasData(object.canvas),from,to);if(!cropped)return;object.x=to.x;object.y=to.y;object.w=to.w;object.h=to.h;object.canvas=cropped;const base=cropCanvasByObjectRect(cloneCanvasData(object.baseCanvas||object.canvas),from,to),restore=cropCanvasByObjectRect(cloneCanvasData(object.restoreCanvas||object.baseCanvas||object.canvas),from,to);if(base)object.baseCanvas=base;if(restore)object.restoreCanvas=restore;object.originalW=cropped.width;object.originalH=cropped.height});render();updateSelection();imageCropCancel(true);setStatus('Recorte aplicado somente às imagens selecionadas');return true}
function toggleImageCropTool(){if(imageCropToolActive){imageCropCancel();return}const object=imageCropPrimary();if(!object){setStatus('Selecione uma imagem primeiro');return}imageCropToolActive=true;state.tool='crop';imageCropRect=null;imageCropDrag=null;imageCropResize=null;if(imageCropButton){imageCropButton.classList.add('active');imageCropButton.setAttribute('aria-pressed','true')}editorRoot.classList.add('dtf-image-crop-active');setStatus('Arraste sobre a imagem para definir o recorte. Ajuste as bordas e pressione ENTER.');showToolHint('Recorte ativo: arraste, ajuste as bordas e pressione ENTER.')}
function imageCropPoint(e){const p=clientToWorkspace(e),object=imageCropPrimary();if(!p||!object)return null;return {x:clamp(p.x,object.x,object.x+object.w),y:clamp(p.y,object.y,object.y+object.h),object}}
viewport.addEventListener('pointerdown',e=>{if(!imageCropToolActive||e.button!==0||imageCropRect)return;const hit=imageCropPoint(e);if(!hit)return;if(hit.x<=hit.object.x||hit.x>=hit.object.x+hit.object.w||hit.y<=hit.object.y||hit.y>=hit.object.y+hit.object.h)return;e.preventDefault();e.stopImmediatePropagation();imageCropDrag={pointerId:e.pointerId,startX:hit.x,startY:hit.y,lastX:hit.x,lastY:hit.y};try{viewport.setPointerCapture(e.pointerId)}catch(_){}},{capture:true});
viewport.addEventListener('pointermove',e=>{if(!imageCropDrag||e.pointerId!==imageCropDrag.pointerId)return;e.preventDefault();e.stopImmediatePropagation();const hit=imageCropPoint(e);if(!hit)return;imageCropDrag.lastX=hit.x;imageCropDrag.lastY=hit.y;const object=hit.object,x=Math.min(imageCropDrag.startX,hit.x),y=Math.min(imageCropDrag.startY,hit.y),right=Math.max(imageCropDrag.startX,hit.x),bottom=Math.max(imageCropDrag.startY,hit.y);imageCropRect={x:clamp(x,object.x,object.x+object.w),y:clamp(y,object.y,object.y+object.h),w:Math.max(3,Math.min(right,object.x+object.w)-Math.max(x,object.x)),h:Math.max(3,Math.min(bottom,object.y+object.h)-Math.max(y,object.y))};imageCropShow(imageCropRect)},{capture:true});
viewport.addEventListener('pointerup',e=>{if(!imageCropDrag||e.pointerId!==imageCropDrag.pointerId)return;e.preventDefault();e.stopImmediatePropagation();if(Math.abs(imageCropDrag.lastX-imageCropDrag.startX)<3||Math.abs(imageCropDrag.lastY-imageCropDrag.startY)<3){imageCropRect=null;selection.classList.remove('dtf-crop-live');setStatus('Arraste para definir a área de recorte')}imageCropDrag=null},{capture:true});
selection.addEventListener('pointerdown',e=>{if(!imageCropToolActive)return;const handle=e.target.closest&&e.target.closest('.dtf-handle');if(!handle)return;e.preventDefault();e.stopImmediatePropagation();const rect=imageCropRect||{x:selected().x,y:selected().y,w:selected().w,h:selected().h},p=clientToWorkspace(e);if(!p)return;imageCropResize={pointerId:e.pointerId,handle:handle.dataset.handle,sx:p.x,sy:p.y,rect:{...rect}};try{handle.setPointerCapture(e.pointerId)}catch(_){}},{capture:true});
selection.addEventListener('pointermove',e=>{if(!imageCropResize||e.pointerId!==imageCropResize.pointerId)return;e.preventDefault();e.stopImmediatePropagation();const p=clientToWorkspace(e),r=imageCropResize.rect;if(!p)return;let x=r.x,y=r.y,w=r.w,h=r.h,hs=imageCropResize.handle;if(hs.includes('e'))w=clamp(p.x-r.x,3,(imageCropPrimary()?.x||0)+(imageCropPrimary()?.w||w)-r.x);if(hs.includes('s'))h=clamp(p.y-r.y,3,(imageCropPrimary()?.y||0)+(imageCropPrimary()?.h||h)-r.y);if(hs.includes('w')){const nx=clamp(p.x,r.x-(r.w-3),r.x+r.w-3);w=r.x+r.w-nx;x=nx}if(hs.includes('n')){const ny=clamp(p.y,r.y-(r.h-3),r.y+r.h-3);h=r.y+r.h-ny;y=ny}imageCropRect={x,y,w,h};imageCropShow(imageCropRect)},{capture:true});
selection.addEventListener('pointerup',e=>{if(!imageCropResize||e.pointerId!==imageCropResize.pointerId)return;e.preventDefault();e.stopImmediatePropagation();imageCropResize=null},{capture:true});
document.addEventListener('keydown',e=>{if(activeEditorRoot!==editorRoot||!imageCropToolActive)return;if(e.key==='Enter'){e.preventDefault();e.stopImmediatePropagation();imageCropApply()}else if(e.key==='Escape'){e.preventDefault();e.stopImmediatePropagation();imageCropCancel()}},{capture:true});
document.addEventListener('pointerdown',e=>{if(imageCropToolActive&&e.target.closest&&e.target.closest('.dtf-tab'))imageCropCancel(true)},{capture:true});
async function openAiImageModal3(){const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-image-ai-modal';modal.innerHTML='<div class="dtf-properties-box dtf-image-ai-box dtf-image-ai-options-box"><h3>Gerar imagem com IA</h3><p class="dtf-image-modal-help">Descreva a imagem em português. Serão geradas 3 opções para você escolher antes de inserir.</p><textarea data-ai-prompt rows="4" placeholder="Ex.: uma paisagem brasileira ao pôr do sol, estilo fotografia profissional"></textarea><div class="dtf-image-ai-row"><label>Formato<select data-ai-format><option value="square">Quadrado</option><option value="landscape">Paisagem</option><option value="portrait">Retrato</option></select></label><label>Estilo<select data-ai-style><option>Realista</option><option>Ilustração</option><option>3D</option><option>Aquarela</option><option>Pixel art</option></select></label></div><div class="dtf-image-ai-options" data-ai-options aria-live="polite"></div><small data-ai-status></small><div class="dtf-properties-actions"><button type="button" data-ai-cancel>Cancelar</button><button type="button" data-ai-generate>Gerar 3 opções</button><button type="button" data-ai-insert disabled>Inserir selecionada</button></div></div>';document.body.appendChild(modal);const prompt=modal.querySelector('[data-ai-prompt]'),status=modal.querySelector('[data-ai-status]'),generate=modal.querySelector('[data-ai-generate]'),insert=modal.querySelector('[data-ai-insert]'),optionsPanel=modal.querySelector('[data-ai-options]');let options=[],selectedIndex=-1;modal.querySelector('[data-ai-cancel]').onclick=()=>{options.forEach(item=>{if(item.url)URL.revokeObjectURL(item.url)});modal.remove()};modal.addEventListener('pointerdown',event=>{if(event.target===modal)modal.querySelector('[data-ai-cancel]').click()});const selectOption=index=>{selectedIndex=index;optionsPanel.querySelectorAll('.dtf-image-ai-option').forEach((card,i)=>card.classList.toggle('selected',i===index));insert.disabled=index<0};const renderOptions=()=>{optionsPanel.replaceChildren();options.forEach((item,index)=>{const card=document.createElement('button');card.type='button';card.className='dtf-image-ai-option';card.setAttribute('aria-label','Selecionar opção '+(index+1));const image=document.createElement('img');image.src=item.url;image.alt='Opção '+(index+1);const caption=document.createElement('span');caption.textContent='Opção '+(index+1);card.append(image,caption);card.onclick=()=>selectOption(index);optionsPanel.appendChild(card)});if(options.length)selectOption(0)};generate.onclick=async()=>{const text=String(prompt.value||'').trim();if(!text){status.textContent='Digite uma descrição para gerar as imagens.';prompt.focus();return}generate.disabled=true;insert.disabled=true;options=[];selectedIndex=-1;optionsPanel.replaceChildren();status.textContent='Gerando 3 opções…';try{const format=modal.querySelector('[data-ai-format]').value,style=modal.querySelector('[data-ai-style]').value,dimensions=format==='landscape'?[1024,768]:format==='portrait'?[768,1024]:[768,768],base=encodeURIComponent(text+', estilo '+style+', sem texto, alta qualidade'),seedBase=Date.now();const results=await Promise.allSettled([0,1,2].map(async index=>{const response=await fetch('https://image.pollinations.ai/prompt/'+base+'?width='+dimensions[0]+'&height='+dimensions[1]+'&nologo=true&seed='+(seedBase+index*7919),{cache:'no-store'});if(!response.ok)throw new Error('Falha na opção '+(index+1));const blob=await response.blob();return{image:await loadBlobImage(blob),url:URL.createObjectURL(blob)}}));options=results.filter(result=>result.status==='fulfilled').map(result=>result.value);if(!options.length)throw new Error('O serviço de geração não retornou imagens.');renderOptions();status.textContent=options.length+' opções prontas. Escolha uma para inserir.';generate.textContent='Gerar novamente'}catch(error){status.textContent=error&&error.message?error.message:'Não foi possível gerar as opções.'}finally{generate.disabled=false}};insert.onclick=()=>{const item=options[selectedIndex];if(!item)return;const object=imageToObject(item.image,'Imagem gerada por IA','ai-generated',{x:state.dpi,y:state.dpi},{allowWorkspaceExpand:true});object.w=Math.min(object.w,canvas.width*.72);object.h=Math.min(object.h,canvas.height*.72);object.x=Math.max(0,(canvas.width-object.w)/2);object.y=Math.max(0,(canvas.height-object.h)/2);addObject(object);setStatus('Imagem gerada inserida a partir da opção '+(selectedIndex+1));modal.querySelector('[data-ai-cancel]').click()};prompt.focus()}
async function openAiImageModalSingle(){const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-image-ai-modal';modal.innerHTML='<div class="dtf-properties-box dtf-image-ai-box"><h3>Gerar imagem com IA</h3><p class="dtf-image-modal-help">Descreva a imagem em português. A miniatura será mostrada antes da inserção.</p><textarea data-ai-prompt rows="4" placeholder="Ex.: uma paisagem brasileira ao pôr do sol, estilo fotografia profissional"></textarea><div class="dtf-image-ai-row"><label>Formato<select data-ai-format><option value="square">Quadrado</option><option value="landscape">Paisagem</option><option value="portrait">Retrato</option></select></label><label>Estilo<select data-ai-style><option>Realista</option><option>Ilustração</option><option>3D</option><option>Aquarela</option><option>Pixel art</option></select></label></div><div class="dtf-image-ai-single-preview" data-ai-preview hidden><img alt="Prévia da imagem gerada"></div><small data-ai-status></small><div class="dtf-properties-actions"><button type="button" data-ai-cancel>Cancelar</button><button type="button" data-ai-generate>Gerar imagem</button><button type="button" data-ai-insert disabled>Inserir</button></div></div>';document.body.appendChild(modal);const prompt=modal.querySelector('[data-ai-prompt]'),status=modal.querySelector('[data-ai-status]'),generate=modal.querySelector('[data-ai-generate]'),insert=modal.querySelector('[data-ai-insert]'),preview=modal.querySelector('[data-ai-preview]'),previewImage=preview.querySelector('img');let generated=null,previewUrl='';modal.querySelector('[data-ai-cancel]').onclick=()=>{if(previewUrl)URL.revokeObjectURL(previewUrl);modal.remove()};modal.addEventListener('pointerdown',event=>{if(event.target===modal)modal.querySelector('[data-ai-cancel]').click()});generate.onclick=async()=>{const text=String(prompt.value||'').trim();if(!text){status.textContent='Digite uma descrição para gerar a imagem.';prompt.focus();return}generate.disabled=true;insert.disabled=true;preview.hidden=true;status.textContent='Gerando imagem…';try{const format=modal.querySelector('[data-ai-format]').value,style=modal.querySelector('[data-ai-style]').value,dimensions=format==='landscape'?[1024,768]:format==='portrait'?[768,1024]:[768,768],data=drawingRequestData('dtf_uv_editor_ai_generate');data.append('prompt',text+', estilo '+style+', sem texto, alta qualidade');data.append('width',String(dimensions[0]));data.append('height',String(dimensions[1]));data.append('seed',String(Date.now()));const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'});if(!response.ok){const detail=(await response.text()).trim();throw new Error(detail||'O serviço de geração não respondeu.')}const blob=await response.blob();if(!/^image\\//i.test(blob.type))throw new Error('O serviço não devolveu uma imagem. Verifique a chave do Pollinations nas configurações.');generated=await loadBlobImage(blob);if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=URL.createObjectURL(blob);previewImage.src=previewUrl;preview.hidden=false;insert.disabled=false;generate.textContent='Gerar outra';status.textContent='Prévia pronta. Insira somente se gostar.'}catch(error){status.textContent=error&&error.message?error.message:'Não foi possível gerar a imagem.'}finally{generate.disabled=false}};insert.onclick=()=>{if(!generated)return;const object=imageToObject(generated,'Imagem gerada por IA','ai-generated',{x:state.dpi,y:state.dpi},{allowWorkspaceExpand:true});object.w=Math.min(object.w,canvas.width*.72);object.h=Math.min(object.h,canvas.height*.72);object.x=Math.max(0,(canvas.width-object.w)/2);object.y=Math.max(0,(canvas.height-object.h)/2);addObject(object);setStatus('Imagem gerada inserida');modal.querySelector('[data-ai-cancel]').click()};prompt.focus()}
function estimateSeparationBackground(data,width,height){
 const buckets=new Map(),step=Math.max(1,Math.round(Math.min(width,height)/180));
 for(let y=0;y<height;y+=step)for(let x=0;x<width;x+=step)if(x<step||y<step||x>=width-step||y>=height-step){const index=(y*width+x)*4,a=data[index+3];if(a<=10)continue;const key=((data[index]>>4)<<8)|((data[index+1]>>4)<<4)|(data[index+2]>>4),entry=buckets.get(key)||{count:0,r:0,g:0,b:0};entry.count++;entry.r+=data[index];entry.g+=data[index+1];entry.b+=data[index+2];buckets.set(key,entry)}
 let best=null;for(const entry of buckets.values())if(!best||entry.count>best.count)best=entry;
 return best?{r:best.r/best.count,g:best.g/best.count,b:best.b/best.count}:null;
}
function separationColorDistance(data,index,bg){return Math.hypot(data[index]-bg.r,data[index+1]-bg.g,data[index+2]-bg.b)}
async function projectionSplitImageParts(source,desiredCount=2,cancelled=()=>false,onProgress=null){
 const width=source.width,height=source.height,total=width*height,context=source.getContext('2d',{willReadFrequently:true}),pixels=context.getImageData(0,0,width,height).data,count=Math.max(2,Math.min(8,Number(desiredCount)||2));
 let transparent=0;for(let index=0;index<total;index++)if(pixels[index*4+3]<=10)transparent++;
 const bg=transparent<total*.02?estimateSeparationBackground(pixels,width,height):null,threshold=bg?Math.max(24,Math.min(72,Math.round(Math.max(28,Math.min(64,Math.hypot(bg.r,bg.g,bg.b)*.22+22))))):0,foreground=new Uint8Array(total),column=new Int32Array(width);let minX=width,minY=height,maxX=-1,maxY=-1,area=0,work=0;
 const pause=async()=>{if(cancelled())throw new Error('__SEPARATION_CANCELLED__');if((++work%24000)===0){if(onProgress)onProgress(Math.min(92,25+Math.round(work/Math.max(1,total*2)*65)));await nextPaint()}};
 for(let index=0;index<total;index++){const offset=index*4,a=pixels[offset+3];let keep=a>10;if(keep&&bg)keep=separationColorDistance(pixels,offset,bg)>threshold;if(keep){foreground[index]=1;column[index%width]++;area++;const x=index%width,y=Math.floor(index/width);if(x<minX)minX=x;if(x>maxX)maxX=x;if(y<minY)minY=y;if(y>maxY)maxY=y}if((index&16383)===0)await pause()}
 if(area<32||maxX<=minX||maxY<=minY)return [];
 const span=maxX-minX+1,prefix=new Int32Array(width+1);for(let x=0;x<width;x++)prefix[x+1]=prefix[x]+column[x];
 const cuts=[],boundaries=[minX];let previous=minX;for(let part=1;part<count;part++){const expected=minX+Math.round(span*part/count),remaining=count-part,low=previous+2,high=maxX-remaining*2;if(low>high)break;let best=-1,bestScore=Infinity;for(let x=low;x<=high;x++){const local=column[x]+(column[x+1]||0),distance=Math.abs(x-expected),score=local*1000+distance; if(score<bestScore){bestScore=score;best=x}if((x&511)===0)await pause()}if(best<0)break;cuts.push(best);boundaries.push(best+1);previous=best}
 boundaries.push(maxX+1);if(cuts.length!==count-1)return [];
 const result=[];for(let part=0;part<count;part++){const left=boundaries[part],right=boundaries[part+1]-1;let partMinX=width,partMinY=height,partMaxX=-1,partMaxY=-1,partArea=0;for(let y=minY;y<=maxY;y++)for(let x=left;x<=right;x++){const index=y*width+x;if(!foreground[index])continue;partArea++;if(x<partMinX)partMinX=x;if(x>partMaxX)partMaxX=x;if(y<partMinY)partMinY=y;if(y>partMaxY)partMaxY=y}if(partArea<Math.max(32,area*.08))continue;const out=document.createElement('canvas');out.width=partMaxX-partMinX+1;out.height=partMaxY-partMinY+1;const image=out.getContext('2d').createImageData(out.width,out.height);for(let y=partMinY;y<=partMaxY;y++)for(let x=partMinX;x<=partMaxX;x++){const sourceIndex=y*width+x,targetIndex=((y-partMinY)*out.width+(x-partMinX))*4;if(foreground[sourceIndex]){const sourceOffset=sourceIndex*4;image.data[targetIndex]=pixels[sourceOffset];image.data[targetIndex+1]=pixels[sourceOffset+1];image.data[targetIndex+2]=pixels[sourceOffset+2];image.data[targetIndex+3]=pixels[sourceOffset+3]}}out.getContext('2d').putImageData(image,0,0);result.push({canvas:out,x:partMinX,y:partMinY,w:out.width,h:out.height,area:partArea})}
 return result.length===count?result:[];
}
async function detectSeparatedImageParts(source,cancelled=()=>false,onProgress=null){
 const width=source.width,height=source.height,total=width*height,context=source.getContext('2d',{willReadFrequently:true}),pixels=context.getImageData(0,0,width,height).data,bg=estimateSeparationBackground(pixels,width,height),background=new Uint8Array(total),queue=new Int32Array(total);let head=0,tail=0;
 let work=0;const pause=async()=>{if(cancelled())throw new Error('__SEPARATION_CANCELLED__');if((++work%18000)===0){if(onProgress)onProgress(Math.min(88,18+Math.round(work/Math.max(1,total*3)*70)));await nextPaint()}};
 if(!bg){for(let index=0;index<total;index++)if(pixels[index*4+3]<=10)background[index]=1}else{
  const threshold=Math.max(24,Math.min(72,Math.round(Math.max(28,Math.min(64,Math.hypot(bg.r,bg.g,bg.b)*.22+22))))),isLikeBackground=index=>{const offset=index*4,a=pixels[offset+3];return a<=10||(a>10&&separationColorDistance(pixels,offset,bg)<=threshold)};
  const push=index=>{if(index<0||index>=total||background[index]||!isLikeBackground(index))return;background[index]=1;queue[tail++]=index};
  for(let x=0;x<width;x++){push(x);push((height-1)*width+x)}for(let y=0;y<height;y++){push(y*width);push(y*width+width-1)}
  while(head<tail){const index=queue[head++],x=index%width,y=Math.floor(index/width);for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++)if(dx||dy){const nx=x+dx,ny=y+dy;if(nx>=0&&nx<width&&ny>=0&&ny<height)push(ny*width+nx)}await pause()}
 }
 let labels=new Int32Array(total);labels.fill(-1);const components=[],componentQueue=new Int32Array(total);let componentId=0;
 for(let start=0;start<total;start++){if((start&16383)===0)await pause();const startOffset=start*4;if(background[start]||pixels[startOffset+3]<=10||labels[start]!==-1)continue;let cHead=0,cTail=0,minX=width,minY=height,maxX=-1,maxY=-1;labels[start]=componentId;componentQueue[cTail++]=start;
  while(cHead<cTail){const index=componentQueue[cHead++],x=index%width,y=Math.floor(index/width);if(x<minX)minX=x;if(y<minY)minY=y;if(x>maxX)maxX=x;if(y>maxY)maxY=y;for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++)if(dx||dy){const nx=x+dx,ny=y+dy;if(nx<0||nx>=width||ny<0||ny>=height)continue;const next=ny*width+nx,offset=next*4;if(background[next]||pixels[offset+3]<=10||labels[next]!==-1)continue;labels[next]=componentId;componentQueue[cTail++]=next}await pause()}
  components.push({id:componentId,area:cTail,minX,minY,maxX,maxY});componentId++;
 }
 const minArea=Math.max(32,Math.floor(total*.00008)),kept=components.filter(component=>component.area>=minArea);if(!kept.length){const projectionParts=await projectionSplitImageParts(source,2,cancelled,onProgress);return projectionParts.length>1?projectionParts:[]}
 /* Uma borda antialiasada ou um contato diagonal de um único pixel pode unir
  * duas artes em um componente 8-conectado. Nessa situação fazemos uma
  * segunda leitura 4-conectada somente quando houver pelo menos dois blocos
  * grandes; textos e detalhes pequenos continuam pertencendo à arte maior. */
 let groupedComponents=kept;
 if(kept.length===1&&kept[0].area>=minArea*4){
  const sourceId=kept[0].id,splitLabels=new Int32Array(labels),splitVisited=new Uint8Array(total),splitComponents=[];let splitId=componentId+1;
  for(let start=0;start<total;start++){
   if(splitVisited[start]||splitLabels[start]!==sourceId)continue;
   let head=0,tail=0;splitVisited[start]=1;componentQueue[tail++]=start;let minX=width,minY=height,maxX=-1,maxY=-1;
   while(head<tail){const index=componentQueue[head++],x=index%width,y=Math.floor(index/width);splitLabels[index]=splitId;if(x<minX)minX=x;if(y<minY)minY=y;if(x>maxX)maxX=x;if(y>maxY)maxY=y;const neighbors=[index-1,index+1,index-width,index+width];for(const next of neighbors){if(next<0||next>=total||splitVisited[next]||splitLabels[next]!==sourceId)continue;const nx=next%width,ny=Math.floor(next/width);if(Math.abs(nx-x)+Math.abs(ny-y)!==1)continue;splitVisited[next]=1;componentQueue[tail++]=next}}
   splitComponents.push({id:splitId,area:tail,minX,minY,maxX,maxY});splitId++;
  }
  const meaningful=splitComponents.filter(component=>component.area>=Math.max(minArea,Math.floor(kept[0].area*.012))).sort((a,b)=>b.area-a.area),largest=meaningful[0],second=meaningful[1];
  if(meaningful.length>=2&&meaningful.length<=24&&second&&second.area>=kept[0].area*.12&&(largest.area+second.area)>=kept[0].area*.78){labels=splitLabels;groupedComponents=meaningful}
 }
 /* Cada componente principal já representa um desenho independente. A antiga
  * união por proximidade ligava desenhos de linhas/colunas vizinhas em cadeia,
  * recriando um único objeto. Mantemos somente o componente realmente conectado. */
 if(groupedComponents.length===1){const projectionParts=await projectionSplitImageParts(source,2,cancelled,onProgress);if(projectionParts.length>1)return projectionParts}
 const groups=groupedComponents.map(component=>({labels:new Set([component.id]),area:component.area,minX:component.minX,minY:component.minY,maxX:component.maxX,maxY:component.maxY}));
 const rowTolerance=Math.max(8,Math.round(height*.035)),rows=[];groups.sort((a,b)=>a.minY-b.minY||a.minX-b.minX).forEach(group=>{let row=rows.find(item=>Math.abs(item.anchor-group.minY)<=rowTolerance);if(!row){row={anchor:group.minY,items:[]};rows.push(row)}row.items.push(group);row.anchor=row.items.reduce((sum,item)=>sum+item.minY,0)/row.items.length});const orderedGroups=rows.sort((a,b)=>a.anchor-b.anchor).flatMap(row=>row.items.sort((a,b)=>a.minX-b.minX));
 const result=[];for(const group of orderedGroups){const pad=1,x=Math.max(0,group.minX-pad),y=Math.max(0,group.minY-pad),right=Math.min(width-1,group.maxX+pad),bottom=Math.min(height-1,group.maxY+pad),out=document.createElement('canvas');out.width=right-x+1;out.height=bottom-y+1;const output=out.getContext('2d'),image=output.createImageData(out.width,out.height),groupLabels=group.labels;for(let yy=y;yy<=bottom;yy++)for(let xx=x;xx<=right;xx++){const sourceIndex=yy*width+xx,targetIndex=((yy-y)*out.width+(xx-x))*4;if(groupLabels.has(labels[sourceIndex])){const sourceOffset=sourceIndex*4;image.data[targetIndex]=pixels[sourceOffset];image.data[targetIndex+1]=pixels[sourceOffset+1];image.data[targetIndex+2]=pixels[sourceOffset+2];image.data[targetIndex+3]=pixels[sourceOffset+3]}if((targetIndex&8191)===0)await pause()}output.putImageData(image,0,0);result.push({canvas:out,x,y,w:out.width,h:out.height,area:group.area})}return result.filter(part=>part.area>=minArea);
}
function separatedObjectFromPart(source,part,index){
 const scaleX=source.w/Math.max(1,source.canvas.width),scaleY=source.h/Math.max(1,source.canvas.height),w=Math.max(1,part.w*scaleX),h=Math.max(1,part.h*scaleY),localX=(part.x+part.w/2)*scaleX,localY=(part.y+part.h/2)*scaleY,sourceCenterX=source.w/2,sourceCenterY=source.h/2,angle=(Number(source.rotation)||0)*Math.PI/180,centerX=source.x+sourceCenterX+Math.cos(angle)*(localX-sourceCenterX)-Math.sin(angle)*(localY-sourceCenterY),centerY=source.y+sourceCenterY+Math.sin(angle)*(localX-sourceCenterX)+Math.cos(angle)*(localY-sourceCenterY),object={id:uid(),name:(source.name||'Imagem')+' — item '+(index+1),x:clamp(centerX-w/2,0,Math.max(0,canvas.width-w)),y:clamp(centerY-h/2,0,Math.max(0,canvas.height-h)),w,h,visible:source.visible!==false,opacity:source.opacity==null?1:source.opacity,locked:false,groupId:null,canvas:part.canvas,baseCanvas:canvasFromData(cloneCanvasData(part.canvas)),restoreCanvas:canvasFromData(cloneCanvasData(part.canvas)),originalW:part.canvas.width,originalH:part.canvas.height,sourceDpiX:source.sourceDpiX||state.dpi,sourceDpiY:source.sourceDpiY||state.dpi,sourceType:'image-separated',imageFilter:source.imageFilter||'none',imageEffect:source.imageEffect||'none',imageMask:source.imageMask||'none',blendMode:source.blendMode||'source-over',flipX:false,flipY:false,rotation:Number(source.rotation)||0,skewX:Number(source.skewX)||0,skewY:Number(source.skewY)||0,aiOriginalCanvas:null};
 object._separationRawCanvas=cloneCanvasData(part.canvas);object._separationRawW=w;object._separationRawH=h;object._separationRawX=object.x;object._separationRawY=object.y;object._separationNeedsReview=part.separationUncertain===true||(part.canvas.width/Math.max(1,part.canvas.height))>=1.72;return object;
}
function trimSeparationCanvas(sourceCanvas){
 const width=sourceCanvas.width,height=sourceCanvas.height,context=sourceCanvas.getContext('2d',{willReadFrequently:true}),pixels=context.getImageData(0,0,width,height).data;
 let minX=width,minY=height,maxX=-1,maxY=-1;
 for(let y=0;y<height;y++)for(let x=0;x<width;x++){if(pixels[(y*width+x)*4+3]>4){if(x<minX)minX=x;if(y<minY)minY=y;if(x>maxX)maxX=x;if(y>maxY)maxY=y}}
 if(maxX<minX||maxY<minY)return {canvas:sourceCanvas,offsetX:0,offsetY:0};
 const out=document.createElement('canvas');out.width=maxX-minX+1;out.height=maxY-minY+1;out.getContext('2d').putImageData(context.getImageData(minX,minY,out.width,out.height),0,0);return {canvas:out,offsetX:minX,offsetY:minY};
}
function applySeparationCropChoice(objects,fitToContent){
 objects.forEach(object=>{
  if(!object._separationRawCanvas)return;
  const raw=canvasFromData(object._separationRawCanvas),trimmed=fitToContent?trimSeparationCanvas(raw):{canvas:raw};
  object.canvas=trimmed.canvas;object.baseCanvas=canvasFromData(cloneCanvasData(trimmed.canvas));object.restoreCanvas=canvasFromData(cloneCanvasData(trimmed.canvas));
  object.w=object._separationRawW;object.h=object._separationRawH;object.x=object._separationRawX;object.y=object._separationRawY;object.originalW=trimmed.canvas.width;object.originalH=trimmed.canvas.height;
 });
}
let separationJob=null,separationModal=null;
function resolveSeparationReview(accepted){
 const job=separationJob;if(!job||!job.reviewing||typeof job.resolveReview!=='function')return;
 job.reviewing=false;const resolve=job.resolveReview;job.resolveReview=null;resolve(accepted===true);
}
function cancelSeparation(){
 if(!separationJob){closeSeparationProgress();return}
 separationJob.cancelled=true;
 if(separationJob.manualWaiting&&typeof separationJob.resolveManual==='function'){separationJob.manualWaiting=false;const resolve=separationJob.resolveManual;separationJob.resolveManual=null;resolve(false);return}
 if(separationJob.reviewing){resolveSeparationReview(false);return}
 const cancel=separationModal&&separationModal.querySelector('[data-separation-cancel]');if(cancel)cancel.disabled=true;
 const message=separationModal&&separationModal.querySelector('[data-separation-message]');if(message)message.textContent='Cancelando a análise…';
}
function ensureSeparationModal(){
 if(separationModal)return separationModal;
 separationModal=document.createElement('div');separationModal.className='dtf-separation-progress-modal';
 separationModal.innerHTML='<div class="dtf-separation-progress-box" role="dialog" aria-modal="true" aria-labelledby="dtfSeparationTitle" tabindex="-1"><div class="dtf-separation-header"><div><h3 id="dtfSeparationTitle">Separação de imagens</h3><p data-separation-message>Preparando a análise…</p></div><button type="button" class="dtf-separation-close" data-separation-close aria-label="Cancelar e fechar">×</button></div><div data-separation-working><div class="dtf-separation-preview"><canvas data-separation-preview></canvas></div><div class="dtf-separation-track"><i data-separation-bar></i></div><div class="dtf-separation-percent" data-separation-percent>0%</div></div><section class="dtf-separation-review" data-separation-review hidden><div class="dtf-separation-review-summary"><strong data-separation-count></strong><span>Confira cada recorte. A imagem original só será substituída depois de aplicar.</span></div><div class="dtf-separation-grid" data-separation-grid role="list"></div></section><div class="dtf-separation-actions"><button type="button" class="dtf-separation-cancel" data-separation-cancel>Cancelar</button><button type="button" class="dtf-separation-apply" data-separation-apply hidden>Aplicar separação</button></div></div>';
 document.body.appendChild(separationModal);
 separationModal.querySelector('[data-separation-cancel]').addEventListener('click',cancelSeparation);
 separationModal.querySelector('[data-separation-close]').addEventListener('click',cancelSeparation);
 separationModal.querySelector('[data-separation-apply]').addEventListener('click',()=>{const apply=separationModal.querySelector('[data-separation-apply]'),cancel=separationModal.querySelector('[data-separation-cancel]');apply.disabled=true;cancel.disabled=true;resolveSeparationReview(true)});
 separationModal.addEventListener('pointerdown',event=>{if(event.target===separationModal)cancelSeparation()});
 separationModal.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();cancelSeparation()}});
 return separationModal;
}
function showSeparationProgress(target,index,total){
 const modal=ensureSeparationModal(),preview=modal.querySelector('[data-separation-preview]'),source=target&&target.canvas,working=modal.querySelector('[data-separation-working]'),review=modal.querySelector('[data-separation-review]'),apply=modal.querySelector('[data-separation-apply]'),cancel=modal.querySelector('[data-separation-cancel]');
 working.hidden=false;review.hidden=true;apply.hidden=true;apply.disabled=false;cancel.disabled=false;modal.querySelector('[data-separation-grid]').replaceChildren();
 if(source){const scale=Math.min(560/source.width,230/source.height,1),previewContext=preview.getContext('2d');preview.width=Math.max(1,Math.round(source.width*scale));preview.height=Math.max(1,Math.round(source.height*scale));previewContext.clearRect(0,0,preview.width,preview.height);previewContext.drawImage(source,0,0,preview.width,preview.height)}
 modal.classList.add('show');modal.querySelector('[data-separation-message]').textContent='Analisando imagem '+(index+1)+' de '+total+'…';modal.querySelector('[data-separation-bar]').style.width='8%';modal.querySelector('[data-separation-percent]').textContent='8%';requestAnimationFrame(()=>modal.querySelector('.dtf-separation-progress-box').focus());
}
function updateSeparationProgress(value,message){if(!separationModal)return;const percent=clamp(Number(value)||0,0,100);separationModal.querySelector('[data-separation-bar]').style.width=percent+'%';separationModal.querySelector('[data-separation-percent]').textContent=Math.round(percent)+'%';if(message)separationModal.querySelector('[data-separation-message]').textContent=message}
function splitCanvasByManualCuts(source,cuts){
 const width=source.width,height=source.height,ordered=(Array.isArray(cuts)?cuts:[]).filter(c=>c&&Number.isFinite(Number(c.position))).sort((a,b)=>Number(a.position)-Number(b.position));if(!ordered.length)return [];
 const axis=ordered[0].axis==='y'?'y':'x';if(ordered.some(c=>(c.axis==='y'?'y':'x')!==axis))return [];
 const limit=axis==='x'?width:height,boundaries=[0,...ordered.map(c=>Math.round(clamp(Number(c.position),1,limit-1))),limit].filter((value,index,array)=>index===0||value>array[index-1]);if(boundaries.length<3)return [];
 const result=[];for(let index=0;index<boundaries.length-1;index++){const start=boundaries[index],end=boundaries[index+1],out=document.createElement('canvas');if(axis==='x'){out.width=end-start;out.height=height;out.getContext('2d').drawImage(source,start,0,out.width,height,0,0,out.width,height);result.push({canvas:out,x:start,y:0,w:out.width,h:height,area:out.width*height})}else{out.width=width;out.height=end-start;out.getContext('2d').drawImage(source,0,start,width,out.height,0,0,width,out.height);result.push({canvas:out,x:0,y:start,w:width,h:out.height,area:out.width*out.height})}}
 return result;
}
function openSeparationMarkDialog(target,job,onApplied=null){
 const existing=document.querySelector('.dtf-separation-mark-modal');if(existing)existing.remove();const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-separation-mark-modal';modal.innerHTML='<div class="dtf-properties-box dtf-separation-mark-box"><h3>Marcar reta de corte</h3><p>Clique para criar o primeiro nó e clique novamente para criar o segundo. Depois arraste os nós para ajustar a reta.</p><div class="dtf-separation-mark-stage"><canvas data-separation-mark-canvas></canvas></div><small data-separation-mark-status>Escolha o primeiro nó.</small><div class="dtf-properties-actions"><button type="button" data-separation-mark-clear>Limpar</button><button type="button" data-separation-mark-cancel>Cancelar</button><button type="button" data-separation-mark-apply disabled>Separar nesta reta</button></div></div>';document.body.appendChild(modal);const canvas=modal.querySelector('[data-separation-mark-canvas]'),stage=modal.querySelector('.dtf-separation-mark-stage'),status=modal.querySelector('[data-separation-mark-status]'),apply=modal.querySelector('[data-separation-mark-apply]'),clear=modal.querySelector('[data-separation-mark-clear]'),cancel=modal.querySelector('[data-separation-mark-cancel]'),source=target.canvas,maxWidth=760,maxHeight=420,scale=Math.min(maxWidth/source.width,maxHeight/source.height,1),displayWidth=Math.max(1,Math.round(source.width*scale)),displayHeight=Math.max(1,Math.round(source.height*scale));canvas.width=displayWidth;canvas.height=displayHeight;let points=[],dragging=-1;
 let hoverPoint=null;const draw=()=>{const context=canvas.getContext('2d');context.clearRect(0,0,canvas.width,canvas.height);context.drawImage(source,0,0,canvas.width,canvas.height);const end=points.length===1&&dragging<0?hoverPoint:points[1];if(points.length===1&&end){context.save();context.strokeStyle='rgba(209,38,74,.72)';context.lineWidth=2;context.setLineDash([7,5]);context.beginPath();context.moveTo(points[0].x,points[0].y);context.lineTo(end.x,end.y);context.stroke();context.restore()}else if(points.length===2){context.save();context.strokeStyle='#d1264a';context.lineWidth=3;context.setLineDash([8,5]);context.beginPath();context.moveTo(points[0].x,points[0].y);context.lineTo(points[1].x,points[1].y);context.stroke();context.restore()}points.forEach((point,index)=>{context.beginPath();context.fillStyle='#fff';context.strokeStyle='#d1264a';context.lineWidth=3;context.arc(point.x,point.y,8,0,Math.PI*2);context.fill();context.stroke();context.fillStyle='#d1264a';context.font='700 12px system-ui,sans-serif';context.fillText(String(index+1),point.x+11,point.y-10)})};
 const pointFromEvent=event=>{const rect=canvas.getBoundingClientRect();return{x:clamp(event.clientX-rect.left,0,canvas.width),y:clamp(event.clientY-rect.top,0,canvas.height)}};const nearest=point=>{let result=-1,best=16;points.forEach((item,index)=>{const distance=Math.hypot(item.x-point.x,item.y-point.y);if(distance<best){best=distance;result=index}});return result};
 canvas.addEventListener('pointerdown',event=>{event.preventDefault();const point=pointFromEvent(event),hit=nearest(point);if(points.length===2&&hit>=0){dragging=hit;canvas.setPointerCapture(event.pointerId);return}if(points.length<2){points.push(point);hoverPoint=null;draw();if(points.length===1)status.textContent='Mova o mouse para visualizar a reta e clique para criar o segundo nó.';else{status.textContent='Reta pronta. Arraste os nós se precisar ajustar.';apply.disabled=false}}});canvas.addEventListener('pointermove',event=>{const point=pointFromEvent(event);if(dragging<0){if(points.length===1){hoverPoint=point;draw()}return}points[dragging]=point;draw()});canvas.addEventListener('pointerleave',()=>{if(points.length===1&&dragging<0){hoverPoint=null;draw()}});canvas.addEventListener('pointerup',event=>{if(dragging>=0){dragging=-1;try{canvas.releasePointerCapture(event.pointerId)}catch(_){}}});
 clear.onclick=()=>{points=[];dragging=-1;apply.disabled=true;status.textContent='Escolha o primeiro nó.';draw()};cancel.onclick=()=>modal.remove();modal.addEventListener('pointerdown',event=>{if(event.target===modal)modal.remove()});apply.onclick=()=>{if(points.length!==2)return;const vertical=Math.abs(points[1].y-points[0].y)>=Math.abs(points[1].x-points[0].x),position=(vertical?(points[0].x+points[1].x)/2:(points[0].y+points[1].y)/2)/scale;const parts=splitCanvasByManualCuts(source,[{axis:vertical?'x':'y',position}]);if(parts.length<2){status.textContent='A reta precisa cruzar a imagem em uma posição interna.';return}const pieces=parts.map((part,partIndex)=>separatedObjectFromPart(target,part,partIndex));pieces.forEach(assignAutoDisplayMode);modal.remove();if(typeof onApplied==='function'){onApplied(pieces);return}job.replacements.set(target.id,pieces);job.newObjects.push(...pieces);const choice=document.querySelector('.dtf-separation-choice-modal');if(choice)choice.remove();if(job.manualWaiting&&typeof job.resolveManual==='function'){job.manualWaiting=false;const resolve=job.resolveManual;job.resolveManual=null;resolve(true)}};draw();requestAnimationFrame(()=>canvas.focus())}
function openSeparationChoiceDialog(target,index,job){
 const existing=document.querySelector('.dtf-separation-choice-modal');if(existing)existing.remove();const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-separation-choice-modal';modal.innerHTML='<div class="dtf-properties-box dtf-separation-choice-box"><h3>Imagem possivelmente composta</h3><p>Não tenho certeza de quantas artes existem neste recorte. Escolha quantas imagens deseja separar ou mantenha tudo junto.</p><label>Separar em<select data-separation-choice-count><option value="2">2 imagens</option><option value="3">3 imagens</option><option value="4">4 imagens</option><option value="5">5 imagens</option><option value="6">6 imagens</option><option value="7">7 imagens</option><option value="8">8 imagens</option></select></label><small data-separation-choice-status></small><div class="dtf-properties-actions"><button type="button" data-separation-choice-cancel>Cancelar</button><button type="button" data-separation-choice-mark>Marcar reta</button><button type="button" data-separation-choice-keep>Manter juntas</button><button type="button" data-separation-choice-apply>Separar</button></div></div>';document.body.appendChild(modal);const status=modal.querySelector('[data-separation-choice-status]'),separate=modal.querySelector('[data-separation-choice-apply]'),mark=modal.querySelector('[data-separation-choice-mark]'),keep=modal.querySelector('[data-separation-choice-keep]'),cancel=modal.querySelector('[data-separation-choice-cancel]'),close=()=>modal.remove();cancel.onclick=close;mark.onclick=()=>openSeparationMarkDialog(target,job);modal.addEventListener('pointerdown',event=>{if(event.target===modal)close()});keep.onclick=()=>{close();if(job.manualWaiting&&typeof job.resolveManual==='function'){job.manualWaiting=false;const resolve=job.resolveManual;job.resolveManual=null;resolve(false)}};separate.onclick=async()=>{const count=Number(modal.querySelector('[data-separation-choice-count]').value)||2;separate.disabled=true;keep.disabled=true;mark.disabled=true;cancel.disabled=true;status.textContent='Procurando os limites das artes…';try{const parts=await projectionSplitImageParts(target.canvas,count,()=>job.cancelled,value=>updateSeparationProgress(value,'Analisando a imagem escolhida…'));if(job.cancelled)throw new Error('__SEPARATION_CANCELLED__');if(parts.length<2)throw new Error('Não encontrei limites suficientes para essa quantidade. Tente uma quantidade menor.');const pieces=parts.map((part,partIndex)=>separatedObjectFromPart(target,part,partIndex));pieces.forEach(assignAutoDisplayMode);job.replacements.set(target.id,pieces);job.newObjects.push(...pieces);close();if(job.manualWaiting&&typeof job.resolveManual==='function'){job.manualWaiting=false;const resolve=job.resolveManual;job.resolveManual=null;resolve(true)}}catch(error){if(error&&error.message==='__SEPARATION_CANCELLED__'){close();return}status.textContent=error&&error.message?error.message:'Não foi possível separar esta imagem.';separate.disabled=false;keep.disabled=false;mark.disabled=false;cancel.disabled=false}};modal.querySelector('[data-separation-choice-count]').focus()}
function showSeparationNoResults(targets,job){
 const modal=ensureSeparationModal(),working=modal.querySelector('[data-separation-working]'),review=modal.querySelector('[data-separation-review]'),grid=modal.querySelector('[data-separation-grid]'),apply=modal.querySelector('[data-separation-apply]'),cancel=modal.querySelector('[data-separation-cancel]');
 working.hidden=true;review.hidden=false;apply.hidden=true;apply.disabled=true;cancel.disabled=false;grid.replaceChildren();
 const trimOption=review.querySelector('[data-separation-trim-option]');if(trimOption)trimOption.remove();
 modal.querySelector('[data-separation-message]').textContent='Nenhum limite confirmado automaticamente. Use o ícone de corte para marcar a reta.';
 modal.querySelector('[data-separation-count]').textContent='Verificação manual necessária';
 (Array.isArray(targets)?targets:[]).forEach((target,index)=>{const source=target&&target.canvas;if(!source)return;const card=document.createElement('article'),frame=document.createElement('div'),thumbnail=document.createElement('canvas'),badge=document.createElement('button'),label=document.createElement('strong'),size=document.createElement('small'),scale=Math.min(148/Math.max(1,source.width),104/Math.max(1,source.height),1);card.className='dtf-separation-card dtf-separation-card-empty';card.setAttribute('role','listitem');card.title='Marcar uma reta de corte';frame.className='dtf-separation-card-image';thumbnail.width=Math.max(1,Math.round(source.width*scale));thumbnail.height=Math.max(1,Math.round(source.height*scale));thumbnail.getContext('2d').drawImage(source,0,0,thumbnail.width,thumbnail.height);badge.type='button';badge.className='dtf-separation-uncertain-badge';badge.textContent='✂';badge.title='Marcar reta de corte';badge.setAttribute('aria-label','Marcar reta na imagem '+(index+1));badge.addEventListener('click',event=>{event.stopPropagation();if(job)openSeparationMarkDialog(target,job)});frame.append(thumbnail,badge);label.textContent='Imagem original '+(index+1);size.textContent=source.width+' × '+source.height+' px · clique em ✂ para marcar';card.addEventListener('click',()=>{if(job)openSeparationMarkDialog(target,job)});card.append(frame,label,size);grid.appendChild(card)});
 modal.classList.add('show');requestAnimationFrame(()=>modal.querySelector('.dtf-separation-progress-box').focus());
}
function showSeparationReview(objects){
 const modal=ensureSeparationModal(),working=modal.querySelector('[data-separation-working]'),review=modal.querySelector('[data-separation-review]'),grid=modal.querySelector('[data-separation-grid]'),apply=modal.querySelector('[data-separation-apply]'),cancel=modal.querySelector('[data-separation-cancel]');
 working.hidden=true;review.hidden=false;apply.hidden=false;apply.disabled=false;cancel.disabled=false;grid.replaceChildren();
 modal.querySelector('[data-separation-message]').textContent='Prévia pronta — escolha se deseja aplicar ou cancelar.';
 modal.querySelector('[data-separation-count]').textContent=objects.length+' imagem'+(objects.length===1?' separada':'ens separadas');
 const job=separationJob;
 const renderCards=()=>{grid.replaceChildren();objects.forEach((object,index)=>{const card=document.createElement('article'),frame=document.createElement('div'),thumbnail=document.createElement('canvas'),badge=document.createElement('button'),label=document.createElement('strong'),size=document.createElement('small'),source=object.canvas,scale=Math.min(148/Math.max(1,source.width),104/Math.max(1,source.height),1);card.className='dtf-separation-card';card.setAttribute('role','listitem');frame.className='dtf-separation-card-image';thumbnail.width=Math.max(1,Math.round(source.width*scale));thumbnail.height=Math.max(1,Math.round(source.height*scale));thumbnail.getContext('2d').drawImage(source,0,0,thumbnail.width,thumbnail.height);badge.type='button';badge.className='dtf-separation-uncertain-badge';badge.textContent='✂';badge.title='Marcar reta de corte neste recorte';badge.setAttribute('aria-label','Marcar reta na imagem '+(index+1));badge.addEventListener('click',event=>{event.stopPropagation();openSeparationMarkDialog(object,job,pieces=>{const old=objects[index];objects.splice(index,1,...pieces);job.replacements.forEach((value,key)=>{const oldIndex=value.indexOf(old);if(oldIndex>=0)value.splice(oldIndex,1,...pieces)});applySeparationCropChoice(objects,trimOption&&trimOption.querySelector('input')?trimOption.querySelector('input').checked:true);renderCards()})});frame.append(thumbnail,badge);label.textContent='Imagem '+(index+1);size.textContent=source.width+' × '+source.height+' px · corte manual disponível';card.append(frame,label,size);grid.appendChild(card)})};
 let trimOption=review.querySelector('[data-separation-trim-option]');
 if(!trimOption){
  trimOption=document.createElement('label');trimOption.className='dtf-separation-trim-option';trimOption.setAttribute('data-separation-trim-option','1');
  const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=true;const text=document.createElement('span');text.textContent='Ajustar cada recorte aos limites do desenho (não redimensionar a imagem)';trimOption.append(checkbox,text);review.insertBefore(trimOption,grid);
  checkbox.addEventListener('change',()=>{if(job)job.trimToContent=checkbox.checked;applySeparationCropChoice(objects,checkbox.checked);renderCards();modal.querySelector('[data-separation-message]').textContent=checkbox.checked?'Prévia atualizada: recortes ajustados aos limites dos desenhos.':'Prévia atualizada: recortes originais mantidos.'});
 }
 const checkbox=trimOption.querySelector('input');checkbox.checked=!job||job.trimToContent!==false;applySeparationCropChoice(objects,checkbox.checked);
 renderCards();
 requestAnimationFrame(()=>apply.focus());
}
function waitForSeparationReview(job,objects){showSeparationReview(objects);job.reviewing=true;return new Promise(resolve=>{job.resolveReview=resolve})}
function closeSeparationProgress(){const choice=document.querySelector('.dtf-separation-choice-modal');if(choice)choice.remove();if(!separationModal)return;separationModal.classList.remove('show');separationModal.querySelector('[data-separation-cancel]').disabled=false;separationModal.querySelector('[data-separation-apply]').disabled=false}
async function separateSelectedImages(){
 const targets=imageEditTargets();if(!targets.length){setStatus('Selecione uma imagem primeiro');return}if(targets.some(object=>object.locked)){setStatus('Desbloqueie as imagens selecionadas para separá-las.');return}if(separationJob){setStatus('A separação já está em andamento.');return}
 const job={cancelled:false,reviewing:false,manualWaiting:false,resolveManual:null,resolveReview:null,trimToContent:true};separationJob=job;const replacements=new Map(),newObjects=[];job.replacements=replacements;job.newObjects=newObjects;ensureSeparationModal();
 try{
  for(let index=0;index<targets.length;index++){
   if(job.cancelled)throw new Error('__SEPARATION_CANCELLED__');const target=targets[index];showSeparationProgress(target,index,targets.length);await nextPaint();
   const parts=await detectSeparatedImageParts(target.canvas,()=>job.cancelled,value=>updateSeparationProgress(Math.round((index/targets.length)*78+value/targets.length),'Analisando pixels da imagem '+(index+1)+' de '+targets.length+'…'));
   if(job.cancelled)throw new Error('__SEPARATION_CANCELLED__');updateSeparationProgress(Math.round((index+1)/targets.length*88),'Preparando as miniaturas separadas…');
   if(parts.length>1){const pieces=parts.map((part,partIndex)=>separatedObjectFromPart(target,part,partIndex));pieces.forEach(assignAutoDisplayMode);replacements.set(target.id,pieces);newObjects.push(...pieces)}
   await nextPaint();
  }
  if(job.cancelled)throw new Error('__SEPARATION_CANCELLED__');
  if(!newObjects.length){showSeparationNoResults(targets,job);job.manualWaiting=true;const manuallySeparated=await new Promise(resolve=>{job.resolveManual=resolve});if(job.cancelled)throw new Error('__SEPARATION_CANCELLED__');if(!manuallySeparated){closeSeparationProgress();setStatus('Nenhuma separação aplicada. As imagens permaneceram juntas.');return}}
  const accepted=await waitForSeparationReview(job,newObjects);if(!accepted||job.cancelled)throw new Error('__SEPARATION_CANCELLED__');
  updateSeparationProgress(96,'Aplicando os objetos separados…');pushHistory('Antes de separar imagens');
  const next=[];state.objects.forEach(object=>{const pieces=replacements.get(object.id);if(pieces)next.push(...pieces);else next.push(object)});state.objects=next;normalizeObjectNames();state.selectedIds=newObjects.map(object=>object.id);state.selectedId=state.selectedIds[state.selectedIds.length-1]||null;
  render();updateSelection();updateObjectUI();closeSeparationProgress();setStatus(newObjects.length+' objetos separados e selecionados. A imagem original foi removida.');
 }catch(error){if(error&&error.message==='__SEPARATION_CANCELLED__'){closeSeparationProgress();setStatus('Separação cancelada. Nenhuma alteração foi aplicada.')}else{closeSeparationProgress();setStatus('Não foi possível separar a imagem: '+(error&&error.message?error.message:'erro desconhecido'))}}finally{if(job.resolveManual){job.resolveManual(false);job.resolveManual=null}if(job.resolveReview){job.resolveReview(false);job.resolveReview=null}separationJob=null}
}
function createImageTool(id,label,title,icon){const button=document.createElement('button');button.type='button';button.id=id;button.className='dtf-tool';button.title=title||label;button.setAttribute('aria-label',title||label);button.setAttribute('aria-pressed','false');button.innerHTML='<span class="dtf-icon" aria-hidden="true">'+(icon||'✦')+'</span><span>'+label+'</span>';return button}
const imagePanel=Q('[data-panel="desenhos"]'),alignmentPanelForImages=Q('[data-panel="alinhamentos"]');let imageCropButton,imageSeparateButton,imageMaskSelect,imageFilterSelect,imageEffectSelect,imageOpacityRange,imageOpacityValue,imageBlendSelect,rotateLeftButton,rotateRightButton,flipHButton,flipVButton,fitFillButton,fitContainButton,fitCenterButton,imageAiButton,imageVisualSubmenuButtons=[];
if(imagePanel){const makeGroup=(caption,children)=>{const group=document.createElement('div');group.className='dtf-group dtf-image-option-group';const title=document.createElement('div');title.className='dtf-caption';title.textContent=caption;const tools=document.createElement('div');tools.className='dtf-tools dtf-image-option-tools';children.forEach(child=>tools.appendChild(child));group.append(title,tools);imagePanel.appendChild(group);return group};imageCropButton=createImageTool('dtfImageCrop','Recortar','Recortar imagem','✂');makeGroup('RECORTE',[imageCropButton]);imageMaskSelect=document.createElement('select');imageMaskSelect.className='dtf-image-select';imageMaskSelect.setAttribute('aria-label','Moldura da imagem');[['none','Sem moldura'],['circle','Círculo'],['rounded','Cantos arredondados'],['star','Estrela']].forEach(([value,label])=>imageMaskSelect.append(new Option(label,value)));makeGroup('MOLDURA',[imageMaskSelect]);imageFilterSelect=document.createElement('select');imageFilterSelect.className='dtf-image-select';imageFilterSelect.setAttribute('aria-label','Filtro da imagem');[['none','Original'],['grayscale','Preto e branco'],['sepia','Sépia'],['vivid','Vibrante'],['cool','Frio'],['warm','Quente'],['cinema','Cinema'],['faded','Desbotado'],['invert','Inverter cores']].forEach(([value,label])=>imageFilterSelect.append(new Option(label,value)));makeGroup('FILTROS',[imageFilterSelect]);imageEffectSelect=document.createElement('select');imageEffectSelect.className='dtf-image-select';imageEffectSelect.setAttribute('aria-label','Efeito da imagem');[['none','Sem efeito'],['shadow','Sombra'],['glow','Brilho'],['outline','Contorno']].forEach(([value,label])=>imageEffectSelect.append(new Option(label,value)));makeGroup('EFEITOS',[imageEffectSelect]);const opacityWrap=document.createElement('div');opacityWrap.className='dtf-image-opacity-wrap';imageOpacityRange=document.createElement('input');imageOpacityRange.type='range';imageOpacityRange.min='0';imageOpacityRange.max='100';imageOpacityRange.value='100';imageOpacityRange.className='dtf-range';imageOpacityValue=document.createElement('span');imageOpacityValue.className='dtf-value';imageOpacityValue.textContent='100%';opacityWrap.append(imageOpacityRange,imageOpacityValue);imageBlendSelect=document.createElement('select');imageBlendSelect.className='dtf-image-select';[['source-over','Normal'],['multiply','Multiplicar'],['screen','Clarear'],['overlay','Sobrepor'],['soft-light','Luz suave'],['difference','Diferença']].forEach(([value,label])=>imageBlendSelect.append(new Option(label,value)));const appearanceGroup=makeGroup('OPACIDADE / MESCLAGEM',[opacityWrap,imageBlendSelect]);rotateLeftButton=createImageTool('dtfImageRotateLeft','Girar −90°','Girar para a esquerda','↺');rotateRightButton=createImageTool('dtfImageRotateRight','Girar +90°','Girar para a direita','↻');flipHButton=createImageTool('dtfImageFlipH','Espelhar H','Espelhar horizontalmente','⇄');flipVButton=createImageTool('dtfImageFlipV','Espelhar V','Espelhar verticalmente','⇅');makeGroup('TRANSFORMAR',[rotateLeftButton,rotateRightButton,flipHButton,flipVButton]);fitFillButton=createImageTool('dtfImageFitFill','Preencher página','Preencher a página com a imagem','▣');fitContainButton=createImageTool('dtfImageFitContain','Encaixar','Encaixar a imagem na página','□');fitCenterButton=createImageTool('dtfImageFitCenter','Centralizar','Centralizar a imagem','⊙');makeGroup('PÁGINA',[fitFillButton,fitContainButton,fitCenterButton]);imageAiButton=createImageTool('dtfImageGenerateAi','Gerar com IA','Gerar imagem com inteligência artificial','✦');makeGroup('IA',[imageAiButton]);imageCropButton.addEventListener('click',openImageCropModal);imageMaskSelect.addEventListener('change',()=>applyImageProperty('imageMask',imageMaskSelect.value,'Moldura'));imageFilterSelect.addEventListener('change',()=>applyImageProperty('imageFilter',imageFilterSelect.value,'Filtro'));imageEffectSelect.addEventListener('change',()=>applyImageProperty('imageEffect',imageEffectSelect.value,'Efeito'));imageBlendSelect.addEventListener('change',()=>applyImageProperty('blendMode',imageBlendSelect.value,'Modo de mesclagem'));imageOpacityRange.addEventListener('input',()=>{const targets=imageEditTargets();if(!targets.length)return;targets.forEach(object=>object.opacity=Number(imageOpacityRange.value)/100);imageOpacityValue.textContent=imageOpacityRange.value+'%';render()});imageOpacityRange.addEventListener('pointerdown',()=>imageHistory('Alterar opacidade'));rotateLeftButton.addEventListener('click',()=>rotateSelectedImages(-90));rotateRightButton.addEventListener('click',()=>rotateSelectedImages(90));flipHButton.addEventListener('click',()=>flipSelectedImages('x'));flipVButton.addEventListener('click',()=>flipSelectedImages('y'));fitFillButton.addEventListener('click',()=>fitSelectedImages('fill'));fitContainButton.addEventListener('click',()=>fitSelectedImages('contain'));fitCenterButton.addEventListener('click',()=>fitSelectedImages('center'));imageAiButton.addEventListener('click',openAiImageModal);updateImageControlState()}
if(imagePanel&&!imageSeparateButton){imageSeparateButton=createImageTool('dtfImageSeparate','Separar','Separação de imagens','⧉');const separationGroup=document.createElement('div');separationGroup.className='dtf-group dtf-image-option-group';const separationTitle=document.createElement('div');separationTitle.className='dtf-caption';separationTitle.textContent='SEPARAÇÃO';const separationTools=document.createElement('div');separationTools.className='dtf-tools dtf-image-option-tools';separationTools.appendChild(imageSeparateButton);separationGroup.append(separationTitle,separationTools);const firstGroup=imagePanel.querySelector('.dtf-image-option-group');imagePanel.insertBefore(separationGroup,firstGroup?firstGroup.nextSibling:null);imageSeparateButton.addEventListener('click',separateSelectedImages);updateImageControlState()}
const selectObjectWithImageControls=selectObject;selectObject=function(...args){const result=selectObjectWithImageControls(...args);if(typeof updateImageControlState==='function')updateImageControlState();return result};
const updateSelectionWithImageControls=updateSelection;updateSelection=function(...args){const result=updateSelectionWithImageControls(...args);if(typeof updateImageControlState==='function')updateImageControlState();return result};
if(imageCropButton){imageCropButton.removeEventListener('click',openImageCropModal);imageCropButton.addEventListener('click',toggleImageCropTool)}
if(fitCenterButton)fitCenterButton.remove();
const pageImageGroup=fitFillButton&&fitFillButton.closest('.dtf-image-option-group');if(pageImageGroup&&alignmentPanelForImages&&pageImageGroup.parentElement!==alignmentPanelForImages)alignmentPanelForImages.appendChild(pageImageGroup);
/* A montagem inteligente fica na página/alinhamentos. O botão de remoção
 * continua no grupo Remoção de fundo, mas abre o mesmo diálogo em modo
 * separado. Clonar o botão preserva o ícone e mantém a identidade visual. */
if(pageImageGroup&&!document.getElementById('dtfMagicLayout')&&ui.magicFill){
 const pageTools=pageImageGroup.querySelector('.dtf-image-option-tools'),layoutButton=ui.magicFill.cloneNode(true);
 pageImageGroup.classList.add('dtf-magic-page-group');
 layoutButton.id='dtfMagicLayout';layoutButton.title='Montagem inteligente com IA';layoutButton.setAttribute('aria-label','Montagem inteligente com IA');
 const layoutLabel=layoutButton.querySelector('span:not(.dtf-icon)');if(layoutLabel)layoutLabel.textContent='Montagem inteligente com IA';
 if(pageTools)pageTools.appendChild(layoutButton);else pageImageGroup.appendChild(layoutButton);
 ui.magicLayout=layoutButton;layoutButton.addEventListener('click',()=>openMagicDialog('montage'));
}
const appearanceTools=imageBlendSelect&&imageBlendSelect.closest('.dtf-image-option-tools');if(appearanceTools&&imageOpacityRange){appearanceTools.classList.add('dtf-image-appearance-tools');appearanceTools.insertBefore(imageBlendSelect,appearanceTools.firstElementChild);const opacityWrap=imageOpacityRange.closest('.dtf-image-opacity-wrap');if(opacityWrap)appearanceTools.appendChild(opacityWrap)}
/* IA compatível com a versão 1.0.366: tenta primeiro o endpoint direto do
 * Pollinations, que era o fluxo que funcionava, e só usa o proxy atual como
 * reserva. A imagem continua sendo mostrada em miniatura antes da inserção. */
async function openAiImageModalLegacyPreview(){
 const modal=document.createElement('div');modal.className='dtf-properties-modal dtf-image-ai-modal';modal.innerHTML='<div class="dtf-properties-box dtf-image-ai-box"><h3>Gerar imagem com IA</h3><p class="dtf-image-modal-help">Descreva a imagem em português. A miniatura será mostrada antes da inserção.</p><textarea data-ai-prompt rows="4" placeholder="Ex.: uma paisagem brasileira ao pôr do sol, estilo fotografia profissional"></textarea><div class="dtf-image-ai-row"><label>Formato<select data-ai-format><option value="square">Quadrado</option><option value="landscape">Paisagem</option><option value="portrait">Retrato</option></select></label><label>Estilo<select data-ai-style><option>Realista</option><option>Ilustração</option><option>3D</option><option>Aquarela</option><option>Pixel art</option></select></label></div><div class="dtf-image-ai-single-preview" data-ai-preview hidden><img alt="Prévia da imagem gerada"></div><small data-ai-status></small><div class="dtf-properties-actions"><button type="button" data-ai-cancel>Cancelar</button><button type="button" data-ai-generate>Gerar imagem</button><button type="button" data-ai-insert disabled>Inserir</button></div></div>';
 document.body.appendChild(modal);
 const prompt=modal.querySelector('[data-ai-prompt]'),status=modal.querySelector('[data-ai-status]'),generate=modal.querySelector('[data-ai-generate]'),insert=modal.querySelector('[data-ai-insert]'),preview=modal.querySelector('[data-ai-preview]'),previewImage=preview.querySelector('img');
 let generated=null,previewUrl='';
 const close=()=>{if(previewUrl)URL.revokeObjectURL(previewUrl);modal.remove()};
 modal.querySelector('[data-ai-cancel]').onclick=close;modal.addEventListener('pointerdown',event=>{if(event.target===modal)close()});
 const requestImage=async(text,style,dimensions)=>{
  const phrase=text+', estilo '+style+', sem texto, alta qualidade',encoded=encodeURIComponent(phrase),seed=Date.now();
  let lastError=null;
  try{
   const direct='https://image.pollinations.ai/prompt/'+encoded+'?width='+dimensions[0]+'&height='+dimensions[1]+'&nologo=true&seed='+seed;
   const response=await fetch(direct,{cache:'no-store',credentials:'omit'});
   if(response.ok){const blob=await response.blob();if(/^image\\//i.test(blob.type))return blob}
   lastError=new Error('O serviço direto do Pollinations não respondeu.');
  }catch(error){lastError=error}
  try{
   const data=drawingRequestData('dtf_uv_editor_ai_generate');data.append('prompt',phrase);data.append('width',String(dimensions[0]));data.append('height',String(dimensions[1]));data.append('seed',String(seed));
   const response=await fetch(editorRoot.dataset.dtfMediaUrl,{method:'POST',body:data,credentials:'same-origin'});if(!response.ok){const detail=(await response.text()).trim();throw new Error(detail||'O proxy do Pollinations não respondeu.')}
   const blob=await response.blob();if(!/^image\\//i.test(blob.type))throw new Error('O serviço não devolveu uma imagem.');return blob;
  }catch(error){throw new Error((error&&error.message)||((lastError&&lastError.message)||'Não foi possível gerar a imagem.'))}
 };
 generate.onclick=async()=>{
  const text=String(prompt.value||'').trim();if(!text){status.textContent='Digite uma descrição para gerar a imagem.';prompt.focus();return}
  generate.disabled=true;insert.disabled=true;preview.hidden=true;status.textContent='Gerando imagem…';
  try{
   const format=modal.querySelector('[data-ai-format]').value,style=modal.querySelector('[data-ai-style]').value,dimensions=format==='landscape'?[1024,768]:format==='portrait'?[768,1024]:[768,768],blob=await requestImage(text,style,dimensions);
   generated=await loadBlobImage(blob);if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=URL.createObjectURL(blob);previewImage.src=previewUrl;preview.hidden=false;insert.disabled=false;generate.textContent='Gerar outra';status.textContent='Prévia pronta. Insira somente se gostar.';
  }catch(error){status.textContent=error&&error.message?error.message:'Não foi possível gerar a imagem.'}
  finally{generate.disabled=false}
 };
 insert.onclick=()=>{if(!generated)return;const object=imageToObject(generated,'Imagem gerada por IA','ai-generated',{x:state.dpi,y:state.dpi},{allowWorkspaceExpand:true});object.w=Math.min(object.w,canvas.width*.72);object.h=Math.min(object.h,canvas.height*.72);object.x=Math.max(0,(canvas.width-object.w)/2);object.y=Math.max(0,(canvas.height-object.h)/2);addObject(object);setStatus('Imagem gerada inserida');close()};prompt.focus();
}
if(imageAiButton){imageAiButton.removeEventListener('click',openAiImageModal);imageAiButton.removeEventListener('click',openAiImageModal3);imageAiButton.removeEventListener('click',openAiImageModalSingle);imageAiButton.addEventListener('click',openAiImageModalLegacyPreview)}
/* A geração externa de imagens por IA foi removida: o editor continua com as
 * ferramentas locais de remoção/montagem, mas não exibe nem chama Pollinations. */
if(imageAiButton){const aiGroup=imageAiButton.closest('.dtf-image-option-group');if(aiGroup)aiGroup.remove();imageAiButton=null}
const imageVisualGroup=imageMaskSelect&&imageMaskSelect.closest('.dtf-image-option-group'),imageFilterGroup=imageFilterSelect&&imageFilterSelect.closest('.dtf-image-option-group'),imageEffectGroup=imageEffectSelect&&imageEffectSelect.closest('.dtf-image-option-group');
if(imageVisualGroup&&imageFilterGroup&&imageEffectGroup&&imageVisualGroup!==imageFilterGroup&&imageVisualGroup!==imageEffectGroup){const visualTools=imageVisualGroup.querySelector('.dtf-image-option-tools'),visualCaption=imageVisualGroup.querySelector('.dtf-caption');if(visualTools){if(visualCaption)visualCaption.textContent='MOLDURA • FILTROS • EFEITOS';visualTools.append(imageFilterSelect,imageEffectSelect);imageFilterGroup.remove();imageEffectGroup.remove();imageVisualGroup.classList.add('dtf-image-visual-group');imageVisualGroup.style.setProperty('width','194px','important');imageVisualGroup.style.setProperty('min-width','194px','important');imageVisualGroup.style.setProperty('max-width','194px','important');visualTools.style.setProperty('display','flex','important');visualTools.style.setProperty('flex-direction','column','important');visualTools.style.setProperty('align-items','stretch','important');visualTools.style.setProperty('flex-wrap','nowrap','important');[imageMaskSelect,imageFilterSelect,imageEffectSelect].forEach(select=>{select.style.setProperty('display','block','important');select.style.setProperty('width','100%','important');select.style.setProperty('min-width','0','important');select.style.setProperty('max-width','none','important')})}}
const compactVisualTools=imageVisualGroup&&imageVisualGroup.querySelector('.dtf-image-option-tools');
if(compactVisualTools&&imageMaskSelect&&imageFilterSelect&&imageEffectSelect){const entries=[['Moldura',imageMaskSelect],['Filtros',imageFilterSelect],['Efeitos',imageEffectSelect]],buttonBar=document.createElement('div');buttonBar.className='dtf-image-visual-buttons';imageVisualSubmenuButtons=[];const closeMenus=()=>buttonBar.querySelectorAll('.dtf-image-submenu-wrap.open').forEach(wrap=>{wrap.classList.remove('open');const trigger=wrap.querySelector('.dtf-image-submenu-trigger');if(trigger)trigger.setAttribute('aria-expanded','false')});entries.forEach(([label,select])=>{const wrap=document.createElement('div'),trigger=document.createElement('button'),menu=document.createElement('div');wrap.className='dtf-image-submenu-wrap';trigger.type='button';trigger.className='dtf-image-submenu-trigger';trigger.textContent=label+' ▾';trigger.title='Escolher '+label.toLowerCase();trigger.setAttribute('aria-haspopup','menu');trigger.setAttribute('aria-expanded','false');menu.className='dtf-image-submenu';menu.setAttribute('role','menu');menu.appendChild(select);wrap.append(trigger,menu);buttonBar.appendChild(wrap);imageVisualSubmenuButtons.push(trigger);trigger.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();const open=wrap.classList.contains('open');closeMenus();if(!open){wrap.classList.add('open');trigger.setAttribute('aria-expanded','true')}})});compactVisualTools.replaceChildren(buttonBar);document.addEventListener('pointerdown',event=>{if(!event.target.closest('.dtf-image-visual-buttons'))closeMenus()},{capture:true})}
if(imageVisualGroup){const decorationCaption=imageVisualGroup.querySelector('.dtf-caption');if(decorationCaption)decorationCaption.textContent='DECORAÇÃO'}
if(drawingOpenButton){const label=drawingOpenButton.querySelector('span:not(.dtf-icon)');if(label)label.textContent='Inserir';drawingOpenButton.title='Inserir imagem, ilustração, clipart ou ícone';drawingOpenButton.setAttribute('aria-label','Abrir biblioteca de imagem');drawingOpenButton.addEventListener('click',()=>openFreeElementsLibrary('photos'))}
QA('.dtf-panel .dtf-tool').forEach(button=>{const label=button.querySelector('span:not(.dtf-icon)');if(label&&!button.title)button.title=label.textContent.trim()});
/* Divisórias ajustáveis da aba Editar. A largura do grupo à esquerda muda,
 * portanto a divisória e todos os grupos à direita caminham juntos. */
function setupEditDividerControls(){
 if(editDividerController){editDividerController.sync();return editDividerController}
 const panel=Q('.dtf-panel.dtf-edit-panel[data-panel="editar"]');if(!panel)return null;
 const definitions=[
  {key:'import',selector:'.dtf-edit-import-group',name:'após Importar',minimum:70,fallback:125},
  {key:'actions',selector:'.dtf-edit-actions-group',name:'após Ações',minimum:70,fallback:130},
  {key:'removal',selector:'.dtf-background-removal-group',name:'após Remoção de fundo',minimum:290,fallback:300},
  {key:'object',selector:'.dtf-edit-object-group',name:'após Objeto',minimum:130,fallback:160}
 ],lastGroup=panel.querySelector('.dtf-edit-brush-group'),storageKey='printway_dtf_edit_dividers_v1_'+autosaveUserKey,measured=definitions.map(definition=>{const group=panel.querySelector(definition.selector);return group?Math.round(group.getBoundingClientRect().width):0}),lastMeasured=lastGroup?Math.round(lastGroup.getBoundingClientRect().width):0;
 let stored={};try{const parsed=JSON.parse(localStorage.getItem(storageKey)||'{}');stored=parsed&&parsed.widths&&typeof parsed.widths==='object'?parsed.widths:{}}catch(_){stored={}}
 const clearWidth=group=>{if(!group)return;['flex','width','min-width','max-width'].forEach(property=>group.style.removeProperty(property))};
 const setWidth=(group,width)=>{const value=Math.round(width);if(!editorRoot.classList.contains('dtf-divider-editing')){clearWidth(group);return value}group.style.setProperty('flex','0 0 '+value+'px','important');group.style.setProperty('width',value+'px','important');group.style.setProperty('min-width',value+'px','important');group.style.setProperty('max-width',value+'px','important');return value};
 panel.classList.add('dtf-edit-divider-layout');
 const items=definitions.map((definition,index)=>{const group=panel.querySelector(definition.selector),defaultWidth=Math.max(definition.minimum,measured[index]||definition.fallback),savedWidth=Number(stored[definition.key]),width=Number.isFinite(savedWidth)?clamp(savedWidth,definition.minimum,1600):defaultWidth;if(group)setWidth(group,width);return {...definition,index,group,defaultWidth,width,handle:null,badge:null}}).filter(item=>item.group);
 if(lastGroup)setWidth(lastGroup,Math.max(130,lastMeasured||160));
 const save=()=>{try{localStorage.setItem(storageKey,JSON.stringify({version:1,widths:Object.fromEntries(items.map(item=>[item.key,Math.round(item.group.getBoundingClientRect().width)]))}))}catch(_){}};
 const sync=()=>{const panelRect=panel.getBoundingClientRect();if(!panelRect.width)return;items.forEach(item=>{if(!item.handle)return;const x=Math.round(item.group.getBoundingClientRect().right-panelRect.left+panel.scrollLeft);item.handle.dataset.x=String(x);item.handle.setAttribute('aria-valuenow',String(x));item.handle.setAttribute('aria-valuetext','D'+(item.index+1)+', X '+x+' pixels, '+item.name);item.handle.title='D'+(item.index+1)+' — '+item.name+' — X '+x+' px. Arraste; use as setas; SHIFT move 10 px; duplo clique restaura.';item.badge.textContent='D'+(item.index+1)+' · X '+x})};
 const resizeItem=(item,width)=>{item.width=setWidth(item.group,clamp(Math.round(width),item.minimum,1600));sync()};
 const finish=(item,announce=true)=>{item.handle.classList.remove('dragging');document.body.classList.remove('dtf-edit-divider-dragging');save();sync();if(announce)setStatus('D'+(item.index+1)+' ajustada para X '+item.handle.dataset.x+' px. Informe esse valor para fixarmos a posição.')};
 items.forEach(item=>{
  const handle=document.createElement('div'),badge=document.createElement('span');handle.className='dtf-edit-divider-handle';handle.tabIndex=0;handle.setAttribute('role','separator');handle.setAttribute('aria-orientation','vertical');handle.dataset.divider='D'+(item.index+1);badge.className='dtf-edit-divider-badge';handle.appendChild(badge);item.group.appendChild(handle);item.handle=handle;item.badge=badge;
  let drag=null;
  handle.addEventListener('pointerdown',event=>{if(event.button!==0)return;drag={pointerId:event.pointerId,startX:event.clientX,startWidth:item.group.getBoundingClientRect().width};handle.classList.add('dragging');document.body.classList.add('dtf-edit-divider-dragging');try{handle.setPointerCapture(event.pointerId)}catch(_){}event.preventDefault();event.stopPropagation()});
  handle.addEventListener('pointermove',event=>{if(!drag||event.pointerId!==drag.pointerId)return;resizeItem(item,drag.startWidth+event.clientX-drag.startX);event.preventDefault()});
  const endDrag=event=>{if(!drag||event.pointerId!==drag.pointerId)return;drag=null;finish(item,true);event.preventDefault()};handle.addEventListener('pointerup',endDrag);handle.addEventListener('pointercancel',endDrag);
  handle.addEventListener('keydown',event=>{if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;const direction=event.key==='ArrowRight'?1:-1,step=event.shiftKey?10:1;resizeItem(item,item.group.getBoundingClientRect().width+direction*step);finish(item,false);event.preventDefault();event.stopPropagation()});
  handle.addEventListener('keyup',event=>{if(event.key==='ArrowLeft'||event.key==='ArrowRight')setStatus('D'+(item.index+1)+' ajustada para X '+handle.dataset.x+' px.')});
  handle.addEventListener('dblclick',event=>{resizeItem(item,item.defaultWidth);finish(item,true);event.preventDefault();event.stopPropagation()});
 });
 panel.addEventListener('scroll',sync,{passive:true});window.addEventListener('resize',sync);requestAnimationFrame(sync);editDividerController={sync,save,items};return editDividerController;
}
/* Divisórias ajustáveis em todas as abas. Só administradores recebem os
 * controles; um duplo clique em qualquer barra liga/desliga o modo global. */
function setupDividerControls(){
 if(editorRoot.dataset.dtfAdmin!=='1')return null;
 if(editDividerController){editDividerController.ensure(activeTab);return editDividerController}
 const states=new Map(),storagePrefix='printway_dtf_dividers_v2_'+autosaveUserKey,dividerWidthsInput=$id('dtfDividerWidths');let globalDividerWidths={};try{const parsed=JSON.parse(dividerWidthsInput&&dividerWidthsInput.value||'{}');if(parsed&&typeof parsed==='object')globalDividerWidths=parsed}catch(_){globalDividerWidths={}}
 const groupList=panel=>Array.from(panel.children).filter(group=>group.classList.contains('dtf-group')&&!group.classList.contains('dtf-color-tools-removed')&&!group.hidden&&getComputedStyle(group).display!=='none');
 /* Usa a largura renderizada como padrão inicial quando ainda não existe
  * uma medida global salva para a aba. Mantém a inicialização segura em
  * todas as abas, inclusive em instalações que não possuem a função antiga. */
 const editDefaults=(panel,groups)=>groups.map(group=>Math.round(group.getBoundingClientRect().width));
 const clearWidth=group=>{if(!group)return;['flex','width','min-width','max-width'].forEach(property=>group.style.removeProperty(property))};
 const setWidth=(group,width)=>{const value=Math.round(width);if(!editorRoot.classList.contains('dtf-divider-editing')){clearWidth(group);return value}group.style.setProperty('flex','0 0 '+value+'px','important');group.style.setProperty('width',value+'px','important');group.style.setProperty('min-width',value+'px','important');group.style.setProperty('max-width',value+'px','important');return value};
 const readWidths=key=>{let local={};try{const parsed=JSON.parse(localStorage.getItem(storagePrefix+'_'+key)||'{}');local=parsed&&parsed.widths&&typeof parsed.widths==='object'?parsed.widths:{}}catch(_){}const global=globalDividerWidths[key]&&typeof globalDividerWidths[key]==='object'?globalDividerWidths[key]:{};return {...local,...global}};
 const saveState=state=>{const widths=Object.fromEntries(state.items.map(item=>[item.id,Math.round(item.group.getBoundingClientRect().width)]));try{localStorage.setItem(storagePrefix+'_'+state.key,JSON.stringify({version:1,widths}))}catch(_){}globalDividerWidths[state.key]={...(globalDividerWidths[state.key]||{}),...widths};const url=editorRoot.dataset.dtfMediaUrl,nonce=editorRoot.dataset.dtfMediaNonce;if(url&&nonce)fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:new URLSearchParams({action:'dtf_uv_editor_save_divider_widths',nonce,widths:JSON.stringify(globalDividerWidths)})}).catch(()=>{})};
 const syncState=state=>{const panelRect=state.panel.getBoundingClientRect();if(!panelRect.width)return;state.items.forEach(item=>{const x=Math.round(item.group.getBoundingClientRect().right-panelRect.left+state.panel.scrollLeft);item.handle.dataset.x=String(x);item.handle.setAttribute('aria-valuenow',String(x));item.handle.setAttribute('aria-valuetext','D'+(item.index+1)+', X '+x+' pixels, '+item.name);item.handle.title='D'+(item.index+1)+' — '+item.name+' — X '+x+' px. '+(state.editing?'Arraste; use as setas; SHIFT move 10 px.':'Duplo clique para habilitar o ajuste.');item.badge.textContent='D'+(item.index+1)+' · X '+x})};
 const sync=()=>states.forEach(syncState);
 const controller={editing:false,states,ensure(tab){const panel=Q('.dtf-ribbon>.dtf-panel[data-panel="'+tab+'"]');if(!panel||panel.hidden||panel.classList.contains('dtf-help-panel'))return null;if(states.has(panel)){syncState(states.get(panel));return states.get(panel)}const groups=groupList(panel);if(groups.length<2)return null;const measured=groups.map(group=>Math.round(group.getBoundingClientRect().width)),defaults=editDefaults(panel,groups),stored=readWidths(panel.dataset.panel||tab),state={panel,key:panel.dataset.panel||tab,items:[],editing:this.editing};panel.classList.add('dtf-divider-layout');groups.forEach((group,index)=>{const minimum=panel.dataset.panel==='editar'?[70,70,240,130][index]||56:56,defaultWidth=Math.max(minimum,defaults&&defaults[index]||measured[index]||96),saved=Number(stored['g'+index]),width=Number.isFinite(saved)?clamp(saved,minimum,1600):defaultWidth;setWidth(group,width);if(index>=groups.length-1)return;const handle=document.createElement('div'),badge=document.createElement('span'),caption=group.querySelector('.dtf-caption'),name=(caption&&caption.textContent.trim())||'grupo '+(index+1),item={id:'g'+index,index,group,name,defaultWidth,minimum,width,handle,badge,drag:null};handle.className='dtf-divider-handle';handle.tabIndex=0;handle.setAttribute('role','separator');handle.setAttribute('aria-orientation','vertical');handle.setAttribute('aria-disabled',String(!this.editing));handle.dataset.divider='D'+(index+1);badge.className='dtf-divider-badge';handle.appendChild(badge);group.appendChild(handle);state.items.push(item);
  handle.addEventListener('pointerdown',event=>{if(event.button!==0)return;if(!controller.editing){setStatus('Ajuste de divisórias desativado. Dê duplo clique em uma barra para habilitar.');event.preventDefault();event.stopPropagation();return}item.drag={pointerId:event.pointerId,startX:event.clientX,startWidth:group.getBoundingClientRect().width};handle.classList.add('dragging');document.body.classList.add('dtf-edit-divider-dragging');try{handle.setPointerCapture(event.pointerId)}catch(_){}event.preventDefault();event.stopPropagation()});
  handle.addEventListener('pointermove',event=>{if(!item.drag||event.pointerId!==item.drag.pointerId||!controller.editing)return;item.width=setWidth(group,clamp(Math.round(item.drag.startWidth+event.clientX-item.drag.startX),item.minimum,1600));syncState(state);event.preventDefault()});
  const endDrag=event=>{if(!item.drag||event.pointerId!==item.drag.pointerId)return;item.drag=null;handle.classList.remove('dragging');document.body.classList.remove('dtf-edit-divider-dragging');saveState(state);syncState(state);setStatus('D'+(item.index+1)+' ajustada para X '+handle.dataset.x+' px.');event.preventDefault()};handle.addEventListener('pointerup',endDrag);handle.addEventListener('pointercancel',endDrag);
  handle.addEventListener('keydown',event=>{if(!controller.editing||!['ArrowLeft','ArrowRight'].includes(event.key))return;const step=event.shiftKey?10:1,direction=event.key==='ArrowRight'?1:-1;item.width=setWidth(group,clamp(group.getBoundingClientRect().width+direction*step,item.minimum,1600));saveState(state);syncState(state);event.preventDefault();event.stopPropagation()});
  handle.addEventListener('dblclick',event=>{controller.toggle();event.preventDefault();event.stopPropagation()});
 });states.set(panel,state);panel.addEventListener('scroll',()=>syncState(state),{passive:true});requestAnimationFrame(()=>syncState(state));if(!globalDividerWidths[state.key]&&state.items.length)saveState(state);return state},toggle(){this.editing=!this.editing;editorRoot.classList.toggle('dtf-divider-editing',this.editing);states.forEach(state=>{state.editing=this.editing;if(this.editing){state.items.forEach(item=>{item.width=setWidth(item.group,item.width||item.group.getBoundingClientRect().width);item.handle.setAttribute('aria-disabled','false')})}else{state.items.forEach(item=>{item.width=Math.round(item.group.getBoundingClientRect().width);clearWidth(item.group);item.handle.setAttribute('aria-disabled','true')})}syncState(state)});setStatus(this.editing?'Ajuste de divisórias habilitado em todas as abas. Arraste uma barra vertical para ajustar a largura.':'Ajuste de divisórias desabilitado. Dê duplo clique em uma barra para habilitar novamente.')},sync,save(){states.forEach(saveState)}};
 return editDividerController=controller;
}
showEditorTab('editar');
[['dtfObjCenter',ui.center],['dtfObjDuplicate',ui.duplicate],['dtfObjDelete',ui.del]].forEach(([id,target])=>{const source=$id(id);if(source&&target&&target.click)source.addEventListener('click',()=>target.click())});

syncWorkspaceUI();fitWidthZoom(false);updateHistoryUI();render();setStatus('Pronto — carregue uma imagem ou PDF');
async function revealEditor(){
 const notice=editorRoot.parentElement.querySelector('#dtfStartup');
 try{
  if(document.fonts)await document.fonts.ready;
  if(getComputedStyle(editorRoot).getPropertyValue('--dtf-css-ready').trim()!=='1')throw new Error('Estilos do editor não carregaram.');
  editorRoot.style.display='block';
  await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
  /* As divisórias administrativas foram removidas; o layout é automático. */
  if(editDividerController)editDividerController.ensure(activeTab);
  fitWidthZoom(false);render();
  editorRoot.style.visibility='visible';editorRoot.setAttribute('aria-busy','false');
  editorRoot.dataset.dtfInitialized='ready';
  if(notice)notice.style.display='none';
  warmBrowserBgModel();warmAllLocalAiModels();
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

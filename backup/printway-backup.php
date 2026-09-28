<?php
/**
 * PrintWay - Backup e restauração do site.
 * O módulo usa somente arquivos ZIP assinados pelo próprio site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Controlador único do envio externo. As versões anteriores acumularam vários
 * ouvintes para o mesmo formulário; em arquivos grandes isso gerava milhares
 * de requisições duplicadas e bloqueava o navegador perto do fim. */
add_action( 'plugins_loaded', function() {
	remove_action( 'admin_footer', 'pw_printway_backup_external_upload_progress_window', 109 );
	remove_action( 'admin_footer', 'pw_printway_backup_resilient_upload_window', 112 );
	remove_action( 'admin_footer', 'pw_printway_backup_upload_capture_guard', 113 );
	remove_action( 'admin_footer', 'pw_printway_backup_upload_import_auto_transition', 123 );
	/* A janela antiga encerrava a inclusao ao primeiro erro. Mantemos o criador
	 * da janela (prioridade 111) e o processador compacto com retomada automatica
	 * (prioridade 121), que atuam somente depois que o ZIP ja foi recebido. */
	remove_action( 'admin_footer', 'pw_printway_backup_import_progress_window', 110 );
	remove_action( 'admin_footer', 'pw_printway_backup_upload_retry_total', 114 );
	remove_action( 'admin_footer', 'pw_printway_backup_cleanup_cancelled_upload', 122 );
	remove_action( 'admin_footer', 'pw_printway_backup_import_volume_counter', 124 );
	remove_action( 'admin_footer', 'pw_printway_backup_import_completion_guard', 135 );
	remove_action( 'admin_footer', 'pw_printway_backup_xhr_upload_controller', 125 );
	remove_action( 'admin_footer', 'pw_printway_backup_compact_upload_controller', 120 );
	remove_action( 'admin_footer', 'pw_printway_backup_upload_controller_v3', 130 );
	remove_action( 'admin_footer', 'pw_printway_backup_upload_controller_v4', 140 );
	/* Mantém exclusivamente a janela de download do arquivo de referência.
	 * Os complementos posteriores não podem interceptar esse fluxo. */
	remove_action( 'admin_footer', 'pw_printway_backup_single_download_guard', 132 );
	remove_action( 'admin_footer', 'pw_printway_backup_native_package_download', 133 );
	remove_action( 'admin_footer', 'pw_printway_backup_download_eta_clock' );
	remove_action( 'admin_footer', 'pw_printway_backup_download_pause_controls', 99 );
	remove_action( 'admin_footer', 'pw_printway_backup_transfer_modal_lock', 105 );
	remove_action( 'admin_footer', 'pw_printway_backup_transfer_finish_style', 106 );
	remove_action( 'admin_footer', 'pw_printway_backup_download_clean_display', 99 );
}, 99 );
function pw_printway_backup_upload_controller_v3() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var f=document.getElementById("pw-backup-external-upload"),i=document.getElementById("pw-backup-external-file"),n=' . wp_json_encode( $nonce ) . ';if(!f||!i){return;}function delay(x){return new Promise(function(r){setTimeout(r,x);});}function open(){var m=document.createElement("div");m.className="pw-backup-stream-modal is-open";m.innerHTML="<div class=\\"pw-backup-stream-panel\\"><h2>Enviando ZIP único</h2><p class=\\"pw-backup-stream-message\\">Preparando envio…</p><div class=\\"pw-backup-stream-track\\"><span class=\\"pw-backup-stream-bar\\"></span></div><p class=\\"pw-backup-stream-percent\\">0%</p><div class=\\"pw-backup-stream-stats\\"><div class=\\"pw-backup-stream-stat\\"><small>Velocidade atual</small><strong class=\\"pw-backup-stream-speed\\">Calculando…</strong></div><div class=\\"pw-backup-stream-stat\\"><small>Tempo restante</small><strong class=\\"pw-backup-stream-eta\\">Calculando…</strong></div><div class=\\"pw-backup-stream-stat\\"><small>Tentativas de retomada</small><strong class=\\"pw-backup-stream-retries\\">Nenhuma</strong></div></div><div class=\\"pw-backup-stream-actions\\"><button type=\\"button\\" class=\\"button pw-backup-stream-cancel\\">Cancelar</button></div></div>";document.body.appendChild(m);return m;}document.addEventListener("submit",function(e){if(e.target!==f||f.dataset.pwUploadV3){return;}e.preventDefault();e.stopImmediatePropagation();var file=i.files&&i.files[0];if(!file){return;}f.dataset.pwUploadV3="1";var b=open(),q=b.querySelector.bind(b),msg=q(".pw-backup-stream-message"),bar=q(".pw-backup-stream-bar"),pct=q(".pw-backup-stream-percent"),spd=q(".pw-backup-stream-speed"),eta=q(".pw-backup-stream-eta"),tries=q(".pw-backup-stream-retries"),cancel=q(".pw-backup-stream-cancel"),submit=f.querySelector("button[type=submit]"),chunk=1024*1024,offset=0,restarts=0,stopped=false,xhr=null,start=performance.now(),id="upload-"+Date.now()+"-"+Math.random().toString(36).slice(2);if(submit){submit.disabled=true;}function close(){stopped=true;if(xhr){xhr.abort();}delete f.dataset.pwUploadV3;if(submit){submit.disabled=false;}b.remove();}cancel.onclick=close;function draw(){var secs=Math.max(.01,(performance.now()-start)/1000),rate=offset/secs,left=rate?(file.size-offset)/rate:0,p=Math.min(99,Math.round(offset*100/file.size));bar.style.width=p+"%";pct.textContent=p+"%";spd.textContent=(rate/1048576).toFixed(1)+" MB/s";eta.textContent=rate?Math.floor(left/60)+"min "+Math.round(left%60)+"s":"Calculando…";tries.textContent=restarts?restarts+" tentativa(s)":"Nenhuma";}function again(note,server){if(stopped){return;}if(Number.isFinite(server)&&server>=0){offset=Math.min(file.size,server);draw();if(offset>=file.size){msg.textContent="Arquivo confirmado no servidor. Abrindo a inclusão…";setTimeout(function(){location.reload();},300);return;}}restarts++;msg.textContent=note+" Nova tentativa automática em alguns segundos (tentativa "+restarts+")…";tries.textContent=restarts+" tentativa(s)";setTimeout(next,Math.min(8000,1000+restarts*350));}function next(){if(stopped){return;}if(offset>=file.size){msg.textContent="Upload concluído. Abrindo a inclusão do ZIP…";bar.style.width="100%";pct.textContent="100%";setTimeout(function(){location.reload();},300);return;}var end=Math.min(file.size,offset+chunk),data=new FormData(),r=new XMLHttpRequest();xhr=r;data.append("action","pw_printway_backup_upload_chunk");data.append("nonce",n);data.append("upload_id",id);data.append("offset",String(offset));data.append("total",String(file.size));data.append("last",end===file.size?"1":"");data.append("chunk",file.slice(offset,end),file.name);msg.textContent="Enviando "+(offset/1048576).toFixed(1)+" MB de "+(file.size/1048576).toFixed(1)+" MB…";r.open("POST",ajaxurl,true);r.timeout=45000;r.onload=function(){xhr=null;var out={};try{out=JSON.parse(r.responseText||"{}");}catch(z){again("Resposta inválida do servidor.");return;}if(r.status<200||r.status>=300||!out.success){again((out.data&&out.data.message)||("HTTP "+r.status),Number(out.data&&out.data.offset));return;}offset=Math.min(file.size,Number(out.data&&out.data.received)||end);draw();setTimeout(next,10);};r.onerror=function(){xhr=null;again("Conexão interrompida.");};r.ontimeout=function(){xhr=null;again("Tempo da resposta esgotado.");};r.onabort=function(){xhr=null;};r.send(data);}next();},true);});</script>';
}

/* Envio unico e adaptativo. Usa blocos maiores para reduzir a quantidade de
 * inicializacoes do WordPress e, quando uma resposta se perde, consulta a
 * posicao realmente gravada antes de decidir reenviar o bloco. */
add_action( 'admin_footer', 'pw_printway_backup_upload_controller_v4', 140 );
function pw_printway_backup_upload_controller_v4() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	$script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var form = document.getElementById('pw-backup-external-upload');
	var input = document.getElementById('pw-backup-external-file');
	var nonce = __PW_NONCE__;
	if (!form || !input) { return; }
	function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
	function makeModal() {
		var modal = document.createElement('div');
		modal.className = 'pw-backup-stream-modal is-open';
		modal.innerHTML = '<div class="pw-backup-stream-panel"><h2>Importando backup ZIP</h2><p class="pw-backup-stream-message">Preparando envio…</p><div class="pw-backup-stream-track"><span class="pw-backup-stream-bar"></span></div><p class="pw-backup-stream-percent">0%</p><div class="pw-backup-stream-stats"><div class="pw-backup-stream-stat"><small>Velocidade atual</small><strong class="pw-backup-stream-speed">Calculando…</strong></div><div class="pw-backup-stream-stat"><small>Tempo restante</small><strong class="pw-backup-stream-eta">Calculando…</strong></div><div class="pw-backup-stream-stat"><small>Retomadas</small><strong class="pw-backup-stream-retries">Nenhuma</strong></div></div><div class="pw-backup-stream-actions"><button type="button" class="button pw-backup-stream-cancel">Cancelar</button></div></div>';
		document.body.appendChild(modal);
		return modal;
	}
	function queryPosition(uploadId) {
		return fetch(ajaxurl + '?pw_upload_position=' + Date.now(), {
			method: 'POST', cache: 'no-store', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams({ action: 'pw_printway_backup_upload_status', nonce: nonce, upload_id: uploadId })
		}).then(function (response) { return response.json(); }).then(function (result) {
			if (!result || !result.success) { throw new Error('Não foi possível confirmar a posição no servidor.'); }
			return Math.max(0, Number(result.data && result.data.received || 0));
		});
	}
	function logProblem(uploadId, eventName, request, extra) {
		var responseText = request && typeof request.responseText === 'string' ? request.responseText : '';
		var payload = Object.assign({
			event: eventName,
			client_time: new Date().toISOString(),
			http_status: request ? Number(request.status || 0) : 0,
			ready_state: request ? Number(request.readyState || 0) : 0,
			content_type: request && request.getResponseHeader ? (request.getResponseHeader('content-type') || '') : '',
			response_length: responseText.length,
			response_sample: responseText.slice(0, 1800),
			user_agent: navigator.userAgent || ''
		}, extra || {});
		fetch(ajaxurl, {
			method: 'POST', credentials: 'same-origin', keepalive: true,
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams({ action: 'pw_printway_backup_upload_client_log', nonce: nonce, upload_id: uploadId, payload: JSON.stringify(payload) })
		}).catch(function () {});
	}
	document.addEventListener('submit', function (event) {
		if (event.target !== form || form.dataset.pwUploadV4) { return; }
		event.preventDefault(); event.stopImmediatePropagation();
		var file = input.files && input.files[0];
		if (!file) { return; }
		form.dataset.pwUploadV4 = '1';
		var modal = makeModal(), find = modal.querySelector.bind(modal);
		var message = find('.pw-backup-stream-message'), bar = find('.pw-backup-stream-bar');
		var percent = find('.pw-backup-stream-percent'), speed = find('.pw-backup-stream-speed');
		var eta = find('.pw-backup-stream-eta'), retriesNode = find('.pw-backup-stream-retries');
		var cancel = find('.pw-backup-stream-cancel'), submit = form.querySelector('button[type=submit]');
		var uploadId = 'upload-' + Date.now() + '-' + Math.random().toString(36).slice(2);
		var offset = 0, chunkSize = 4 * 1024 * 1024, retries = 0, stopped = false, xhr = null;
		var started = performance.now();
		if (submit) { submit.disabled = true; }
		function close() {
			stopped = true; if (xhr) { xhr.abort(); } delete form.dataset.pwUploadV4;
			if (submit) { submit.disabled = false; } modal.remove();
		}
		cancel.onclick = close;
		function paint() {
			var elapsed = Math.max(0.01, (performance.now() - started) / 1000);
			var rate = offset / elapsed, remaining = rate ? (file.size - offset) / rate : 0;
			var value = Math.min(99, Math.round(offset * 100 / file.size));
			bar.style.width = value + '%'; percent.textContent = value + '%';
			speed.textContent = rate ? (rate / 1048576).toFixed(1) + ' MB/s' : 'Calculando…';
			eta.textContent = rate ? Math.floor(remaining / 60) + 'min ' + Math.round(remaining % 60) + 's' : 'Calculando…';
			retriesNode.textContent = retries ? retries + ' tentativa(s)' : 'Nenhuma';
		}
		async function recover(reason) {
			if (stopped) { return; }
			try {
				var serverOffset = await queryPosition(uploadId);
				if (serverOffset > offset) {
					offset = Math.min(file.size, serverOffset); paint();
					message.textContent = 'Bloco confirmado pelo servidor. Continuando o envio…';
					await wait(80); sendNext(); return;
				}
			} catch (positionError) {
				logProblem(uploadId, 'position_check_failed', null, { client_offset: offset, chunk_size: chunkSize, retries: retries, error: String(positionError && positionError.message || positionError) });
			}
			retries++;
			if (retries >= 2 && chunkSize > 1024 * 1024) { chunkSize = Math.max(1024 * 1024, Math.floor(chunkSize / 2)); }
			message.textContent = reason + ' Nova tentativa automática (tentativa ' + retries + ')…';
			retriesNode.textContent = retries + ' tentativa(s)';
			await wait(Math.min(5000, 700 + retries * 250));
			if (!stopped) { sendNext(); }
		}
		function sendNext() {
			if (stopped) { return; }
			if (offset >= file.size) {
				message.textContent = 'Upload concluído. Preparando a inclusão do backup…';
				bar.style.width = '100%'; percent.textContent = '100%';
				setTimeout(function () { window.location.reload(); }, 300); return;
			}
			var end = Math.min(file.size, offset + chunkSize), data = new FormData(), request = new XMLHttpRequest();
			xhr = request;
			data.append('action', 'pw_printway_backup_upload_chunk'); data.append('nonce', nonce);
			data.append('upload_id', uploadId); data.append('offset', String(offset)); data.append('total', String(file.size));
			data.append('last', end === file.size ? '1' : ''); data.append('chunk', file.slice(offset, end), file.name);
			message.textContent = 'Enviando ' + (offset / 1048576).toFixed(1) + ' MB de ' + (file.size / 1048576).toFixed(1) + ' MB…';
			request.open('POST', ajaxurl, true); request.timeout = 120000;
			request.onload = function () {
				xhr = null; var result;
				try { result = JSON.parse(request.responseText || ''); }
				catch (error) {
					logProblem(uploadId, 'invalid_server_response', request, { client_offset: offset, requested_end: end, chunk_size: chunkSize, retries: retries, parse_error: String(error && error.message || error) });
					recover('Resposta inválida do servidor.'); return;
				}
				if (request.status < 200 || request.status >= 300 || !result.success) {
					logProblem(uploadId, 'http_or_application_error', request, { client_offset: offset, requested_end: end, chunk_size: chunkSize, retries: retries, server_message: result.data && result.data.message || '' });
					if (result.data && Number.isFinite(Number(result.data.offset))) { offset = Math.min(file.size, Number(result.data.offset)); paint(); }
					recover((result.data && result.data.message) || ('Servidor respondeu HTTP ' + request.status + '.')); return;
				}
				offset = Math.min(file.size, Number(result.data && result.data.received || end)); paint();
				setTimeout(sendNext, 30);
			};
			request.onerror = function () { xhr = null; logProblem(uploadId, 'network_error', request, { client_offset: offset, requested_end: end, chunk_size: chunkSize, retries: retries }); recover('Conexão interrompida.'); };
			request.ontimeout = function () { xhr = null; logProblem(uploadId, 'request_timeout', request, { client_offset: offset, requested_end: end, chunk_size: chunkSize, retries: retries, timeout_ms: request.timeout }); recover('Tempo de resposta esgotado.'); };
			request.onabort = function () { xhr = null; };
			request.send(data);
		}
		sendNext();
	}, true);
});
JS;
	$script = str_replace( '__PW_NONCE__', wp_json_encode( $nonce ), $script );
	echo '<script>' . $script . '</script>';
}

/* Versao 5: os blocos sao recebidos por um endpoint leve que nao inicializa o
 * WordPress. Isso evita que uma oscilacao do banco derrube a transferencia. O
 * WordPress volta a ser consultado apenas uma vez, depois de 100% recebido. */
add_action( 'admin_footer', 'pw_printway_backup_upload_controller_v5', 145 );
function pw_printway_backup_upload_controller_v5() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	$upload_id = 'direct-' . str_replace( '-', '', wp_generate_uuid4() );
	$secret    = wp_generate_password( 64, false, false );
	$auth_path = trailingslashit( pw_printway_backup_directory() ) . '.upload-auth-' . $upload_id . '.json';
	$auth      = array(
		'secret_hash' => hash( 'sha256', $secret ),
		'user_id'     => get_current_user_id(),
		'created'     => time(),
		'expires'     => time() + 6 * HOUR_IN_SECONDS,
		'complete'    => false,
	);
	if ( false === @file_put_contents( $auth_path, wp_json_encode( $auth, JSON_UNESCAPED_SLASHES ), LOCK_EX ) ) {
		return;
	}
	$endpoint = plugins_url( 'printway-upload.php', __FILE__ );
	$nonce    = wp_create_nonce( 'pw_printway_backup_ajax' );
	$script   = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var form = document.getElementById('pw-backup-external-upload');
	var input = document.getElementById('pw-backup-external-file');
	if (!form || !input) { return; }
	var endpoint = __ENDPOINT__, uploadId = __UPLOAD_ID__, token = __TOKEN__, nonce = __NONCE__, activeXhr = null;
	window.pwPrintwayUploadSession = { endpoint: endpoint, uploadId: uploadId, token: token };
	function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
	function modal() {
		var box = document.createElement('div'); box.className = 'pw-backup-stream-modal is-open';
		box.innerHTML = '<div class="pw-backup-stream-panel"><h2>Importando backup ZIP</h2><p class="pw-backup-stream-message">Preparando envio…</p><div class="pw-backup-stream-track"><span class="pw-backup-stream-bar"></span></div><p class="pw-backup-stream-percent">0%</p><div class="pw-backup-stream-stats"><div class="pw-backup-stream-stat"><small>Velocidade atual</small><strong class="pw-backup-stream-speed">Calculando…</strong></div><div class="pw-backup-stream-stat"><small>Tempo restante e previsão de término</small><strong class="pw-backup-stream-eta">Calculando…</strong></div><div class="pw-backup-stream-stat"><small>Retomadas</small><strong class="pw-backup-stream-retries">Nenhuma</strong></div></div><div class="pw-backup-stream-actions"><button type="button" class="button pw-backup-stream-cancel">Cancelar</button></div></div>';
		document.body.appendChild(box); return box;
	}
	function parse(text) {
		try { return JSON.parse(text || ''); } catch (ignore) {}
		var first = (text || '').indexOf('{'), last = (text || '').lastIndexOf('}');
		if (first >= 0 && last > first) { return JSON.parse(text.slice(first, last + 1)); }
		throw new Error('Resposta invalida do receptor de arquivos.');
	}
	function directRequest(operation, blob, offset, total, fileName, isLast) {
		return new Promise(function (resolve, reject) {
			var xhr = new XMLHttpRequest(); activeXhr = xhr;
			xhr.open('POST', endpoint + (operation ? '?operation=' + encodeURIComponent(operation) : ''), true);
			xhr.timeout = operation === 'preflight' ? 30000 : 90000;
			xhr.setRequestHeader('X-PW-Upload-ID', uploadId);
			xhr.setRequestHeader('X-PW-Upload-Token', token);
			if (!operation) {
				xhr.setRequestHeader('Content-Type', 'application/octet-stream');
				xhr.setRequestHeader('X-PW-Upload-Offset', String(offset));
				xhr.setRequestHeader('X-PW-Upload-Total', String(total));
				xhr.setRequestHeader('X-PW-Upload-Last', isLast ? '1' : '0');
				xhr.setRequestHeader('X-PW-File-Name', encodeURIComponent(fileName));
			}
			if (operation === 'preflight' && total) { xhr.setRequestHeader('X-PW-Upload-Total', String(total)); }
			xhr.onload = function () { activeXhr = null; try { var out = parse(xhr.responseText); if (xhr.status >= 200 && xhr.status < 300 && out.success) { resolve(out.data || {}); } else { var e = new Error((out.data && out.data.message) || ('HTTP ' + xhr.status)); e.received = Number(out.data && out.data.received); e.status = Number(xhr.status || 0); e.fatal = Boolean(out.data && out.data.fatal); reject(e); } } catch (e) { e.status = Number(xhr.status || 0); reject(e); } };
			xhr.onerror = function () { activeXhr = null; var e = new Error('Conexão interrompida.'); e.status = Number(xhr.status || 0); reject(e); };
			xhr.ontimeout = function () { activeXhr = null; var e = new Error('Tempo de resposta esgotado.'); e.status = 0; reject(e); };
			xhr.send(blob || new Blob([]));
		});
	}
	function initializeLog(file) {
		try { localStorage.setItem('pw_printway_upload_session', JSON.stringify({ endpoint: endpoint, uploadId: uploadId, token: token })); } catch (ignore) {}
		return directRequest('init-log', new Blob([JSON.stringify({ file_name: file.name, file_size: file.size, client_time: new Date().toISOString(), user_agent: navigator.userAgent || '' })], { type: 'application/json' }));
	}
	function recordClient(eventName, extra) {
		var payload = Object.assign({ event: eventName, client_time: new Date().toISOString(), user_agent: navigator.userAgent || '' }, extra || {});
		try {
			var entries = JSON.parse(localStorage.getItem('pw_printway_upload_client_log') || '[]');
			entries.push(payload); if (entries.length > 100) { entries = entries.slice(-100); }
			localStorage.setItem('pw_printway_upload_client_log', JSON.stringify(entries));
		} catch (ignore) {}
	}
	function registerUpload() {
		return fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams({ action: 'pw_printway_backup_upload_register_direct', nonce: nonce, upload_id: uploadId, token: token }) }).then(function (response) { return response.text().then(function (text) { var out = parse(text); if (!response.ok || !out.success) { throw new Error((out.data && out.data.message) || 'O WordPress ainda nao respondeu.'); } return out.data || {}; }); });
	}
	document.addEventListener('submit', function (event) {
		if (event.target !== form || form.dataset.pwUploadV5) { return; }
		event.preventDefault(); event.stopImmediatePropagation();
		var file = input.files && input.files[0]; if (!file) { return; }
		form.dataset.pwUploadV5 = '1';
		var box = modal(), q = box.querySelector.bind(box), message = q('.pw-backup-stream-message');
		var bar = q('.pw-backup-stream-bar'), percent = q('.pw-backup-stream-percent');
		var speed = q('.pw-backup-stream-speed'), eta = q('.pw-backup-stream-eta');
		var retriesNode = q('.pw-backup-stream-retries'), cancel = q('.pw-backup-stream-cancel');
		var submit = form.querySelector('button[type=submit]'), offset = 0, chunkSize = 4 * 1024 * 1024;
		var retries = 0, consecutiveErrors = 0, stopped = false, busy = false, recovering = false, started = performance.now(); if (submit) { submit.disabled = true; }
		function close() { stopped = true; if (activeXhr) { try { activeXhr.abort(); } catch (ignore) {} activeXhr = null; } directRequest('cancel').catch(function () {}); delete form.dataset.pwUploadV5; if (submit) { submit.disabled = false; } box.remove(); }
		cancel.onclick = close;
		function paint(forceComplete) {
			var elapsed = Math.max(.01, (performance.now() - started) / 1000), rate = offset / elapsed;
			var remaining = rate ? (file.size - offset) / rate : 0, value = forceComplete ? 100 : Math.min(99, Math.round(offset * 100 / file.size));
			bar.style.width = value + '%'; percent.textContent = value + '%';
			speed.textContent = rate ? (rate / 1048576).toFixed(1) + ' MB/s' : 'Calculando…';
			if (forceComplete) {
				eta.textContent = 'Concluído';
			} else if (rate) {
				var finish = new Date(Date.now() + remaining * 1000);
				var finishText = String(finish.getHours()).padStart(2, '0') + 'h' + String(finish.getMinutes()).padStart(2, '0');
				eta.textContent = Math.floor(remaining / 60) + 'min ' + Math.round(remaining % 60) + 's — término às ' + finishText;
			} else {
				eta.textContent = 'Calculando…';
			}
			retriesNode.textContent = retries ? retries + ' tentativa(s)' : 'Nenhuma';
		}
		async function recover(reason) {
			if (stopped || recovering) { return; } recovering = true; busy = false;
			try { var status = await directRequest('status'); var confirmed = Number(status.received || 0); if (confirmed >= 0) { offset = Math.min(file.size, confirmed); paint(false); } }
			catch (positionError) { recordClient('position_check_failed', { client_offset: offset, retries: retries, error: String(positionError && positionError.message || positionError) }); }
			retries++; consecutiveErrors++; message.textContent = reason + ' Retomando com intervalo de segurança (tentativa ' + retries + ')…'; paint(false);
			recordClient('retry_scheduled', { client_offset: offset, retries: retries, error: reason });
			/* Nunca exige um novo clique depois de uma oscilacao. Reduz o bloco
			 * progressivamente e continua tentando ate o administrador cancelar. */
			if (consecutiveErrors >= 3 && chunkSize > 512 * 1024) {
				chunkSize = Math.max(512 * 1024, Math.floor(chunkSize / 2));
				message.textContent = reason + ' O envio continuará automaticamente com blocos menores (tentativa ' + retries + ')…';
			}
			await wait(Math.min(20000, 3000 + retries * 1000)); recovering = false; if (!stopped) { sendNext(); }
		}
		async function finishRegistration() {
			while (!stopped) {
				try { message.textContent = 'Arquivo recebido. Incluindo o backup na lista…'; await registerUpload(); paint(true); recordClient('import_registered', { client_offset: offset, retries: retries }); message.textContent = 'Backup importado e incluído na lista com sucesso.'; cancel.textContent = 'Fechar'; cancel.classList.add('button-primary'); cancel.onclick = function () { window.location.reload(); }; setTimeout(function () { if (!stopped) { window.location.reload(); } }, 1000); return; }
				catch (error) { retries++; recordClient('registration_retry', { client_offset: offset, retries: retries, error: String(error && error.message || error) }); retriesNode.textContent = retries + ' tentativa(s)'; message.textContent = 'Arquivo completo no servidor. Aguardando o WordPress para concluir o cadastro (tentativa ' + retries + ')…'; await wait(Math.min(12000, 1500 + retries * 500)); }
			}
		}
		async function sendNext() {
			if (stopped || busy || recovering) { return; }
			if (offset >= file.size) { paint(true); finishRegistration(); return; }
			busy = true;
			var end = Math.min(file.size, offset + chunkSize);
			message.textContent = 'Enviando ' + (offset / 1048576).toFixed(1) + ' MB de ' + (file.size / 1048576).toFixed(1) + ' MB…';
			try { var result = await directRequest('', file.slice(offset, end), offset, file.size, file.name, end === file.size); offset = Math.min(file.size, Number(result.received || end)); consecutiveErrors = 0; busy = false; paint(false); setTimeout(sendNext, 250); }
			catch (error) { busy = false; recordClient('direct_upload_error', { client_offset: offset, requested_end: end, retries: retries, http_status: Number(error.status || 0), error: String(error && error.message || error) }); if (Number.isFinite(Number(error.received)) && Number(error.received) >= 0) { offset = Math.min(file.size, Number(error.received)); paint(false); } if (error.fatal || Number(error.status) === 507) { stopped = true; message.textContent = error.message || 'Importação interrompida para proteger o servidor.'; cancel.textContent = 'Fechar e limpar'; cancel.onclick = close; return; } recover(error.message || 'Falha temporária.'); }
		}
		message.textContent = 'Verificando espaço e capacidade do servidor…';
		directRequest('preflight', null, 0, file.size, file.name, false).then(function (capacity) {
			recordClient('preflight_ok', capacity || {});
			return initializeLog(file);
		}).then(function () {
			recordClient('client_ready', { file_name: file.name, file_size: file.size });
			message.textContent = 'Capacidade confirmada. Iniciando importação…';
			sendNext();
		}).catch(function (error) {
			recordClient('preflight_failed', { http_status: Number(error.status || 0), error: String(error && error.message || error) });
			stopped = true;
			message.textContent = error.message || 'Não foi possível confirmar capacidade suficiente no servidor.';
			cancel.textContent = 'Fechar e limpar'; cancel.classList.add('button-primary'); cancel.onclick = close;
		});
	}, true);
});
JS;
	$script = str_replace(
		array( '__ENDPOINT__', '__UPLOAD_ID__', '__TOKEN__', '__NONCE__' ),
		array( wp_json_encode( $endpoint ), wp_json_encode( $upload_id ), wp_json_encode( $secret ), wp_json_encode( $nonce ) ),
		$script
	);
	echo '<script>' . $script . '</script>';
}

add_action( 'wp_ajax_pw_printway_backup_upload_register_direct', function() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissao.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$upload_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['upload_id'] ?? '' ) );
	$secret    = (string) wp_unslash( $_POST['token'] ?? '' );
	$directory = trailingslashit( pw_printway_backup_directory() );
	$auth_path = $directory . '.upload-auth-' . $upload_id . '.json';
	$temp      = $directory . '.upload-' . $upload_id . '.tmp';
	$auth      = is_file( $auth_path ) ? json_decode( (string) @file_get_contents( $auth_path ), true ) : array();
	if ( ! $upload_id || ! is_array( $auth ) || empty( $auth['secret_hash'] ) || ! hash_equals( (string) $auth['secret_hash'], hash( 'sha256', $secret ) ) ) {
		wp_send_json_error( array( 'message' => 'A autorizacao final do envio nao confere.' ), 403 );
	}
	clearstatcache( true, $temp );
	$total = (int) ( $auth['total'] ?? 0 );
	if ( ! is_file( $temp ) || $total < 1 || (int) filesize( $temp ) !== $total || empty( $auth['complete'] ) ) {
		wp_send_json_error( array( 'message' => 'O arquivo ainda nao foi recebido integralmente.' ), 409 );
	}
	pw_printway_backup_remember_upload_log( $upload_id );
	pw_printway_backup_upload_log( $upload_id, 'Upload direto concluido: ' . size_format( $total, 2 ) . '. O arquivo sera incluido na lista em uma requisicao separada.' );
	set_transient( 'pw_printway_upload_ready_' . get_current_user_id(), $temp, 6 * HOUR_IN_SECONDS );
	/* Mantem o recibo temporario para tornar esta confirmacao idempotente. Se a
	 * resposta se perder depois de salvar o transient, a repeticao confirma o
	 * mesmo arquivo sem reiniciar o upload. */
	$auth['registered'] = true;
	$auth['updated']    = time();
	@file_put_contents( $auth_path, wp_json_encode( $auth, JSON_UNESCAPED_SLASHES ), LOCK_EX );
	wp_send_json_success( array( 'received' => $total, 'complete' => true ) );
} );

/* Aciona o seletor apenas pelo botão de importação e mantém a janela de envio
 * visualmente idêntica à janela usada nos downloads. */
add_action( 'admin_footer', 'pw_printway_backup_import_button_ui', 131 );
function pw_printway_backup_import_button_ui() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-external-upload"),input=document.getElementById("pw-backup-external-file"),button=document.getElementById("pw-backup-import-choose");if(button&&input&&form){button.addEventListener("click",function(){input.value="";input.click();});input.addEventListener("change",function(){if(input.files&&input.files[0]){form.dispatchEvent(new Event("submit",{bubbles:true,cancelable:true}));}});}function polish(modal){var title=modal.querySelector("h2"),labels=modal.querySelectorAll(".pw-backup-stream-stat small");if(title&&title.textContent==="Enviando ZIP único"){title.textContent="Importando backup ZIP";}labels.forEach(function(label){if(label.textContent==="Tentativas de retomada"){label.textContent="Retomadas";}});}new MutationObserver(function(){document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(polish);}).observe(document.body,{childList:true,subtree:true});document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(polish);});</script>';
}

/* Há versões antigas da tela que registravam mais de um ouvinte de clique no
 * mesmo botão de download. Este controlador em captura impede downloads em
 * duplicidade e mantém apenas uma gravação por vez no arquivo escolhido. */
add_action( 'admin_footer', 'pw_printway_backup_single_download_guard', 132 );
function pw_printway_backup_single_download_guard() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	echo '<script>document.addEventListener("DOMContentLoaded",function(){function wait(ms){return new Promise(function(done){setTimeout(done,ms);});}function fmt(n){return n<1048576?(n/1024).toFixed(1)+" KB":n<1073741824?(n/1048576).toFixed(1)+" MB":(n/1073741824).toFixed(2)+" GB";}function modal(){var m=document.createElement("div");m.className="pw-backup-stream-modal is-open";m.innerHTML="<div class=\\"pw-backup-stream-panel\\"><h2>Baixando ZIP único</h2><p class=\\"pw-backup-stream-message\\">Preparando download…</p><div class=\\"pw-backup-stream-track\\"><span class=\\"pw-backup-stream-bar\\"></span></div><p class=\\"pw-backup-stream-percent\\">0%</p><div class=\\"pw-backup-stream-stats\\"><div class=\\"pw-backup-stream-stat\\"><small>Velocidade atual</small><strong class=\\"pw-backup-stream-speed\\">Calculando…</strong></div><div class=\\"pw-backup-stream-stat\\"><small>Tempo restante</small><strong class=\\"pw-backup-stream-eta\\">Calculando…</strong></div><div class=\\"pw-backup-stream-stat\\"><small>Retomadas</small><strong class=\\"pw-backup-stream-retries\\">Nenhuma</strong></div></div><div class=\\"pw-backup-stream-actions\\"><button type=\\"button\\" class=\\"button pw-backup-stream-cancel\\">Cancelar</button></div></div>";document.body.appendChild(m);return m;}document.addEventListener("click",function(event){var button=event.target.closest&&event.target.closest(".pw-backup-single-download");if(!button||!window.showSaveFilePicker||button.dataset.pwSingleGuard){return;}event.preventDefault();event.stopImmediatePropagation();button.dataset.pwSingleGuard="1";(async function(){var handle;try{handle=await window.showSaveFilePicker({suggestedName:button.dataset.fileName||"backup.zip",types:[{description:"Arquivo ZIP",accept:{"application/zip":[".zip"]}}]});}catch(error){delete button.dataset.pwSingleGuard;return;}var box=modal(),q=box.querySelector.bind(box),msg=q(".pw-backup-stream-message"),bar=q(".pw-backup-stream-bar"),pct=q(".pw-backup-stream-percent"),speed=q(".pw-backup-stream-speed"),eta=q(".pw-backup-stream-eta"),tries=q(".pw-backup-stream-retries"),cancel=q(".pw-backup-stream-cancel"),total=Number(button.dataset.fileSize||0),offset=0,restarts=0,stopped=false,writer=await handle.createWritable(),started=performance.now();cancel.onclick=function(){stopped=true;if(writer&&writer.abort){writer.abort();}writer=null;box.remove();delete button.dataset.pwSingleGuard;};function paint(){var seconds=Math.max(.001,(performance.now()-started)/1000),rate=offset/seconds,left=rate?(total-offset)/rate:0,p=Math.round(offset*100/total);bar.style.width=p+"%";pct.textContent=p+"%";speed.textContent=fmt(rate)+"/s";eta.textContent=Math.floor(left/60)+"min "+Math.round(left%60)+"s";tries.textContent=restarts?restarts+" retomada(s)":"Nenhuma";}while(offset<total&&!stopped){var end=Math.min(total-1,offset+8*1024*1024-1),data;for(;;){try{var response=await fetch(button.dataset.streamUrl,{method:"GET",headers:{Range:"bytes="+offset+"-"+end},credentials:"same-origin",cache:"no-store"});if(!response.ok){throw new Error("HTTP "+response.status);}data=await response.arrayBuffer();if(data.byteLength!==end-offset+1){throw new Error("Bloco incompleto");}break;}catch(error){if(stopped){return;}restarts++;msg.textContent="Conexão interrompida. Retomando automaticamente…";paint();await wait(Math.min(10000,1200+restarts*400));}}if(stopped){return;}await writer.write({type:"write",position:offset,data:data});offset=end+1;msg.textContent=(button.dataset.fileName||"backup.zip")+" — "+fmt(offset)+" de "+fmt(total)+".";paint();}if(stopped){return;}await writer.close();writer=null;bar.style.width="100%";pct.textContent="100%";eta.textContent="Concluído";msg.textContent="Download concluído com sucesso.";button.classList.add("is-downloaded");fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_package_downloaded",nonce:button.dataset.ajaxNonce,file:button.dataset.backup})});cancel.textContent="Fechar";cancel.onclick=function(){box.remove();delete button.dataset.pwSingleGuard;};})().catch(function(error){delete button.dataset.pwSingleGuard;});},true);});</script>';
}

/* O download nativo do navegador é mais estável para arquivos de vários GB.
 * Interrompe os antigos scripts de fluxo em partes antes de chegarem ao
 * documento, mas preserva a ação normal do link de download. */
add_action( 'admin_footer', 'pw_printway_backup_native_package_download', 133 );
function pw_printway_backup_native_package_download() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	echo '<script>window.addEventListener("click",function(event){var link=event.target.closest&&event.target.closest(".pw-backup-single-download");if(!link){return;}event.stopImmediatePropagation();},{capture:true});</script>';
}

/* Finalização somente visual da janela estável de download. Não interfere na
 * leitura, gravação ou retomada dos blocos do arquivo. */
add_action( 'admin_footer', 'pw_printway_backup_download_completed_button', 134 );
function pw_printway_backup_download_completed_button() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	echo '<style>.pw-backup-stream-cancel.pw-backup-download-finished{background:#00a32a!important;border-color:#008a20!important;color:#fff!important;font-weight:600}.pw-backup-stream-cancel.pw-backup-download-finished:hover,.pw-backup-stream-cancel.pw-backup-download-finished:focus{background:#008a20!important;border-color:#006b18!important;color:#fff!important}</style><script>document.addEventListener("DOMContentLoaded",function(){function watch(modal){if(modal.dataset.pwFinishedWatch){return;}modal.dataset.pwFinishedWatch="1";var percent=modal.querySelector(".pw-backup-stream-percent"),button=modal.querySelector(".pw-backup-stream-cancel");if(!percent||!button){return;}function update(){if((percent.textContent||"").trim()==="100%"){button.textContent="Fechar";button.classList.add("pw-backup-download-finished");}}new MutationObserver(update).observe(percent,{childList:true,subtree:true,characterData:true});update();}new MutationObserver(function(){document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(watch);}).observe(document.body,{childList:true,subtree:true});document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(watch);});</script>';
}

/* Confirma pelo servidor que a importacao ja apareceu na lista. Isso encerra
 * a janela mesmo quando a resposta do ultimo passo se perde na rede. */
add_action( 'admin_footer', 'pw_printway_backup_import_completion_guard', 135 );
function pw_printway_backup_import_completion_guard() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	$files = pw_printway_backup_list();
	$baseline_count = count( $files );
	$baseline_latest = $files ? basename( $files[0] ) : '';
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<style>.pw-backup-stream-cancel.pw-import-finished{background:#00a32a!important;border-color:#008a20!important;color:#fff!important;font-weight:600}</style><script>document.addEventListener("DOMContentLoaded",function(){var baseCount=' . (int) $baseline_count . ',baseLatest=' . wp_json_encode( $baseline_latest ) . ',nonce=' . wp_json_encode( $nonce ) . ',finished=false;function finish(data){if(finished){return;}finished=true;var modal=document.querySelector(".pw-backup-stream-modal.is-open");if(modal){var message=modal.querySelector(".pw-backup-stream-message"),bar=modal.querySelector(".pw-backup-stream-bar"),percent=modal.querySelector(".pw-backup-stream-percent"),eta=modal.querySelector(".pw-backup-stream-eta"),button=modal.querySelector(".pw-backup-stream-cancel");if(message){message.textContent="Backup importado e incluído na lista de Restauração.";}if(bar){bar.style.width="100%";}if(percent){percent.textContent="100%";}if(eta){eta.textContent="Concluído";}if(button){button.disabled=false;button.textContent="Fechar";button.classList.add("pw-import-finished");button.onclick=function(){window.location.reload();};}}var tab=document.getElementById("pw-backup-restore-tab")||document.querySelector(".nav-tab-wrapper a[href*=\"tab=restore\"]");if(tab){tab.textContent="Restauração ("+Number(data.restore_count||0).toLocaleString("pt-BR")+")";}setTimeout(function(){window.location.reload();},1200);}function check(){if(finished){return;}fetch(ajaxurl+"?pw_import_check="+Date.now(),{method:"POST",cache:"no-store",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_restore_count",nonce:nonce})}).then(function(r){return r.json();}).then(function(result){var data=result&&result.success?(result.data||{}):{};if(Number(data.restore_count||0)>baseCount||(data.latest_file&&data.latest_file!==baseLatest)){finish(data);return;}setTimeout(check,1000);}).catch(function(){setTimeout(check,2000);});}if(document.querySelector(".pw-backup-stream-modal.is-open")){check();}else{new MutationObserver(function(){if(document.querySelector(".pw-backup-stream-modal.is-open")){check();}}).observe(document.body,{childList:true,subtree:true});}});</script>';
}

const PW_PRINTWAY_BACKUP_OPTION = 'pw_printway_backup_settings';
const PW_PRINTWAY_BACKUP_HOOK   = 'pw_printway_backup_scheduled';
const PW_PRINTWAY_BACKUP_RUN_HOOK = 'pw_printway_backup_run_queued';
const PW_PRINTWAY_BACKUP_PACKAGE_HOOK = 'pw_printway_backup_build_package';
const PW_PRINTWAY_BACKUP_QUEUE    = 'pw_printway_backup_queued_run';
const PW_PRINTWAY_BACKUP_LAST_VISIT_OPTION = 'pw_printway_backup_last_frontend_visit';

function pw_printway_backup_timezone() {
	static $timezone = null;
	if ( null === $timezone ) {
		$timezone = new DateTimeZone( 'America/Sao_Paulo' );
	}
	return $timezone;
}

/* Mantém a página do Backup leve: versões antigas da interface deixaram dois ciclos extras de 0,5 s e 0,9 s. */
add_action( 'admin_head', 'pw_printway_backup_lightweight_admin_ui', 1 );
function pw_printway_backup_lightweight_admin_ui() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<script>(function(){var original=window.setInterval;window.setInterval=function(callback,delay){if(delay===500||delay===900){return 0;}return original.apply(window,arguments);};}());</script>';
}

add_action( 'template_redirect', 'pw_printway_backup_track_frontend_visit', 1 );
function pw_printway_backup_track_frontend_visit() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() ) {
		return;
	}
	$now = time();
	$last = absint( get_option( PW_PRINTWAY_BACKUP_LAST_VISIT_OPTION, 0 ) );
	/* Evita gravar a opção repetidamente durante várias requisições da mesma página. */
	if ( $now - $last >= 10 ) {
		update_option( PW_PRINTWAY_BACKUP_LAST_VISIT_OPTION, $now, false );
	}
}

add_action( 'admin_menu', 'pw_printway_backup_menu', 20 );
function pw_printway_backup_menu() {
	add_submenu_page( 'pw-printway', 'Backup e restauração', 'Backup e restauração', 'manage_options', 'pw-printway-backup', 'pw_printway_backup_page' );
}

function pw_printway_backup_defaults() {
	return array(
		'database'      => 1,
		'core'          => 1,
		'plugins'       => 1,
		'themes'        => 1,
		'uploads'       => 1,
		'content_other' => 1,
		'config'        => 1,
		'selection'     => array(),
		'schedule'      => 'manual',
		'retention'     => 5,
		'retention_rules' => array( 'count' ),
		'retention_size_gb' => 0,
		'retention_age_days' => 0,
		'retention_minimum' => 1,
		'zip_volume_size_mb' => 256,
	);
}

function pw_printway_backup_settings() {
	return wp_parse_args( (array) get_option( PW_PRINTWAY_BACKUP_OPTION, array() ), pw_printway_backup_defaults() );
}

function pw_printway_backup_directory() {
	/* Prefere uma pasta acima da raiz pública; assim o ZIP não fica acessível pela web. */
	$private_dir = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'printway-backups';
	if ( ! file_exists( $private_dir ) ) {
		wp_mkdir_p( $private_dir );
	}
	$is_private_writable = function_exists( 'wp_is_writable' ) ? wp_is_writable( $private_dir ) : is_writable( $private_dir );
	if ( is_dir( $private_dir ) && $is_private_writable ) {
		return $private_dir;
	}
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'printway-backups';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	/* Os ZIPs podem conter banco e wp-config.php: nunca devem ser acessíveis por URL. */
	if ( is_dir( $dir ) ) {
		if ( ! file_exists( trailingslashit( $dir ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $dir ) . '.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( trailingslashit( $dir ) . 'index.php' ) ) {
			file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php // Silence is golden.\n" );
		}
	}
	return $dir;
}

function pw_printway_backup_url() {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['baseurl'] ) . 'printway-backups';
}

function pw_printway_backup_remove_temporary_directory( $directory ) {
	$root = realpath( pw_printway_backup_directory() );
	$target = realpath( $directory );
	if ( ! $root || ! $target || 0 !== strpos( wp_normalize_path( $target ), trailingslashit( wp_normalize_path( $root ) ) ) || 0 !== strpos( basename( $target ), 'tmp-' ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) { @rmdir( $item->getPathname() ); } else { @unlink( $item->getPathname() ); }
	}
	@rmdir( $target );
}

function pw_printway_backup_set_notice( $message, $type = 'success' ) {
	set_transient( 'pw_printway_backup_notice_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), MINUTE_IN_SECONDS );
}

function pw_printway_backup_run_token() {
	return ! empty( $GLOBALS['pw_printway_backup_run_token'] ) ? (string) $GLOBALS['pw_printway_backup_run_token'] : '';
}

function pw_printway_backup_can_update_status( $status ) {
	$token = pw_printway_backup_run_token();
	return ! $token || ! is_array( $status ) || empty( $status['run_id'] ) || $token === (string) $status['run_id'];
}

function pw_printway_backup_progress( $percent, $message, $state = 'running' ) {
	$previous = get_transient( 'pw_printway_backup_progress' );
	$is_new   = 'running' === $state && absint( $percent ) <= 1;
	if ( ! $is_new && ! pw_printway_backup_can_update_status( $previous ) ) {
		return;
	}
	$token    = pw_printway_backup_run_token();
	$started  = ! $is_new && is_array( $previous ) && ! empty( $previous['started'] ) ? (int) $previous['started'] : time();
	$log      = ! $is_new && is_array( $previous ) && ! empty( $previous['log'] ) && is_array( $previous['log'] ) ? $previous['log'] : array();
	$entry    = '[' . wp_date( 'd/m/Y H:i:s', time(), pw_printway_backup_timezone() ) . '] ' . wp_strip_all_tags( (string) $message );
	if ( empty( $log ) || end( $log ) !== $entry ) {
		$log[] = $entry;
	}
	$log = array_slice( $log, -120 );
	set_transient( 'pw_printway_backup_progress', array(
		'percent' => max( 0, min( 100, absint( $percent ) ) ),
		'message' => (string) $message,
		'state'   => $state,
		'started' => $started,
		'updated' => time(),
		'current' => ! $is_new && is_array( $previous ) && ! empty( $previous['current'] ) ? $previous['current'] : '',
		'current_done' => ! $is_new && is_array( $previous ) && isset( $previous['current_done'] ) ? (int) $previous['current_done'] : 0,
		'current_total' => ! $is_new && is_array( $previous ) && isset( $previous['current_total'] ) ? (int) $previous['current_total'] : 0,
		'current_percent' => ! $is_new && is_array( $previous ) && isset( $previous['current_percent'] ) ? (int) $previous['current_percent'] : -1,
		'current_unit' => ! $is_new && is_array( $previous ) && ! empty( $previous['current_unit'] ) ? (string) $previous['current_unit'] : '',
		'zip_files' => ! $is_new && is_array( $previous ) && ! empty( $previous['zip_files'] ) ? (int) $previous['zip_files'] : 0,
		'zip_bytes' => ! $is_new && is_array( $previous ) && ! empty( $previous['zip_bytes'] ) ? (int) $previous['zip_bytes'] : 0,
		'zip_stored_files' => ! $is_new && is_array( $previous ) && ! empty( $previous['zip_stored_files'] ) ? (int) $previous['zip_stored_files'] : 0,
		'zip_compressed_files' => ! $is_new && is_array( $previous ) && ! empty( $previous['zip_compressed_files'] ) ? (int) $previous['zip_compressed_files'] : 0,
		'phase' => ! $is_new && is_array( $previous ) && ! empty( $previous['phase'] ) ? (string) $previous['phase'] : '',
		'phase_started' => ! $is_new && is_array( $previous ) && ! empty( $previous['phase_started'] ) ? (int) $previous['phase_started'] : 0,
		'expected_seconds' => ! $is_new && is_array( $previous ) && ! empty( $previous['expected_seconds'] ) ? (int) $previous['expected_seconds'] : 0,
		'run_id' => $token ? $token : ( ! $is_new && is_array( $previous ) ? (string) ( $previous['run_id'] ?? '' ) : '' ),
		'kind' => $is_new ? (string) ( $GLOBALS['pw_printway_backup_kind'] ?? 'manual' ) : ( ! empty( $previous['kind'] ) ? (string) $previous['kind'] : 'manual' ),
		'log'     => $log,
	), HOUR_IN_SECONDS );
}

function pw_printway_backup_zip_stat( $file, $zip = null, $inside = '' ) {
	if ( empty( $GLOBALS['pw_printway_backup_zip_stats'] ) || ! is_array( $GLOBALS['pw_printway_backup_zip_stats'] ) ) {
		$GLOBALS['pw_printway_backup_zip_stats'] = array(
			'files' => 0,
			'bytes' => 0,
			'stored_files' => 0,
			'compressed_files' => 0,
			'last_update' => 0,
		);
	}
	$stats = &$GLOBALS['pw_printway_backup_zip_stats'];
	$size  = is_file( $file ) ? (int) filesize( $file ) : 0;
	$ext   = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	/* Para um backup de recuperação, confiabilidade e tempo de conclusão são mais importantes que reduzir alguns megabytes.
	 * Guardar os arquivos sem recompressão evita que milhares de mídias sejam todas processadas no fechamento final do ZIP. */
	$store_without_recompression = true;
	if ( $store_without_recompression && $zip && $inside && method_exists( $zip, 'setCompressionName' ) ) {
		if ( @$zip->setCompressionName( $inside, ZipArchive::CM_STORE ) ) {
			$stats['stored_files']++;
		} else {
			$stats['compressed_files']++;
		}
	} else {
		$stats['compressed_files']++;
	}
	$stats['files']++;
	$stats['bytes'] += $size;
	/* A gravação em lotes não pode depender do relógio: diretórios grandes conseguem adicionar milhares de itens em um segundo. */
	pw_printway_backup_zip_maybe_flush( $zip );
	if ( time() === (int) $stats['last_update'] ) {
		return;
	}
	$stats['last_update'] = time();
	pw_printway_backup_zip_snapshot();
}

function pw_printway_backup_zip_snapshot() {
	$stats = ! empty( $GLOBALS['pw_printway_backup_zip_stats'] ) && is_array( $GLOBALS['pw_printway_backup_zip_stats'] ) ? $GLOBALS['pw_printway_backup_zip_stats'] : array();
	$status = get_transient( 'pw_printway_backup_progress' );
	if ( ! is_array( $status ) || ! pw_printway_backup_can_update_status( $status ) ) {
		return $stats;
	}
	$status['zip_files'] = (int) ( $stats['files'] ?? 0 );
	$status['zip_bytes'] = (int) ( $stats['bytes'] ?? 0 );
	$status['zip_stored_files'] = (int) ( $stats['stored_files'] ?? 0 );
	$status['zip_compressed_files'] = (int) ( $stats['compressed_files'] ?? 0 );
	$status['updated']   = time();
	set_transient( 'pw_printway_backup_progress', $status, HOUR_IN_SECONDS );
	return $stats;
}

function pw_printway_backup_zip_maybe_flush( $zip ) {
	if ( ! $zip instanceof ZipArchive || empty( $GLOBALS['pw_printway_backup_zip_runtime'] ) || ! is_array( $GLOBALS['pw_printway_backup_zip_runtime'] ) ) {
		return;
	}
	$runtime = &$GLOBALS['pw_printway_backup_zip_runtime'];
	$stats   = ! empty( $GLOBALS['pw_printway_backup_zip_stats'] ) && is_array( $GLOBALS['pw_printway_backup_zip_stats'] ) ? $GLOBALS['pw_printway_backup_zip_stats'] : array();
	$files_since_flush = max( 0, (int) ( $stats['files'] ?? 0 ) - (int) ( $runtime['flushed_files'] ?? 0 ) );
	$bytes_since_flush = max( 0, (int) ( $stats['bytes'] ?? 0 ) - (int) ( $runtime['flushed_bytes'] ?? 0 ) );
	$volume_limit = max( 0, (int) ( $runtime['volume_limit_bytes'] ?? 0 ) );
	/* O limite por tamanho continua soberano. O limite por quantidade existe apenas
	 * para impedir índices ZIP gigantes; 5.000 evita dezenas de volumes minúsculos. */
	if ( $files_since_flush < 5000 && ( ! $volume_limit || $bytes_since_flush < $volume_limit ) ) {
		return;
	}
	pw_printway_backup_check_cancel();
	$runtime['flush_number'] = 1 + (int) ( $runtime['flush_number'] ?? 0 );
	$expected = max( 10, min( 120, (int) ceil( 6 + ( $bytes_since_flush / ( 32 * MB_IN_BYTES ) ) ) ) );
	$current_status  = get_transient( 'pw_printway_backup_progress' );
	$current_percent = is_array( $current_status ) ? (int) ( $current_status['percent'] ?? 1 ) : 1;
	$volume_number = max( 1, (int) ( $runtime['volume_number'] ?? 1 ) );
	pw_printway_backup_progress( $current_percent, 'Finalizando volume ' . $volume_number . ': ' . number_format_i18n( $files_since_flush ) . ' arquivos e ' . size_format( $bytes_since_flush, 2 ) . '.' );
	pw_printway_backup_current_file( 'Volume ' . $volume_number . ': gravando e validando os dados preparados.', 'Finalizando volume do backup…' );
	pw_printway_backup_set_phase( 'zip_flush', $expected );
	if ( ! $zip->close() ) {
		throw new RuntimeException( 'O servidor não conseguiu finalizar o volume ' . $volume_number . ' do backup.' );
	}
	if ( ! pw_printway_backup_wait_for_volume( $runtime['current_path'] ) ) {
		throw new RuntimeException( 'O volume ' . $volume_number . ' foi fechado, mas não ficou disponível no disco do servidor.' );
	}
	$runtime['parts'][] = $runtime['current_path'];
	$runtime['volume_number'] = $volume_number + 1;
	$runtime['current_path'] = preg_replace( '/\.zip$/i', '.part' . str_pad( (string) $runtime['volume_number'], 3, '0', STR_PAD_LEFT ) . '.zip', $runtime['path'] );
	if ( true !== $zip->open( $runtime['current_path'], ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'O servidor não conseguiu criar o volume ' . $runtime['volume_number'] . ' do backup.' );
	}
	$runtime['flushed_files'] = (int) ( $stats['files'] ?? 0 );
	$runtime['flushed_bytes'] = (int) ( $stats['bytes'] ?? 0 );
	pw_printway_backup_progress( $current_percent, 'Volume ' . $volume_number . ' concluído. Preparando o volume ' . $runtime['volume_number'] . '.' );
	pw_printway_backup_set_phase( 'copying' );
	pw_printway_backup_check_cancel();
}

/* Alguns provedores finalizam a gravação física do ZIP instantes depois de
 * ZipArchive::close(). Só registramos um volume quando ele existir e tiver
 * conteúdo, evitando que o backup falhe por uma verificação prematura. */
function pw_printway_backup_wait_for_volume( $path, $attempts = 20 ) {
	$path = (string) $path;
	for ( $attempt = 0; $attempt < max( 1, absint( $attempts ) ); $attempt++ ) {
		clearstatcache( true, $path );
		if ( is_file( $path ) && (int) filesize( $path ) > 0 ) {
			return true;
		}
		usleep( 100000 );
	}
	return false;
}

function pw_printway_backup_set_phase( $phase, $expected_seconds = 0 ) {
	$status = get_transient( 'pw_printway_backup_progress' );
	if ( ! is_array( $status ) || ! pw_printway_backup_can_update_status( $status ) ) {
		return;
	}
	$status['phase'] = sanitize_key( $phase );
	$status['phase_started'] = time();
	$status['expected_seconds'] = max( 0, absint( $expected_seconds ) );
	$status['updated'] = time();
	set_transient( 'pw_printway_backup_progress', $status, HOUR_IN_SECONDS );
}

function pw_printway_backup_status_is_stale( $status ) {
	if ( ! is_array( $status ) || empty( $status['updated'] ) ) {
		return true;
	}
	if ( 'zip_close' === ( $status['phase'] ?? '' ) ) {
		$expected = max( 45, (int) ( $status['expected_seconds'] ?? 0 ) );
		$since    = ! empty( $status['phase_started'] ) ? (int) $status['phase_started'] : (int) $status['updated'];
		return time() - $since > max( 180, (int) ceil( $expected * 2.5 ) );
	}
	if ( 'validating' === ( $status['phase'] ?? '' ) ) {
		/* A validação atualiza o heartbeat a cada volume. Só considere travado se
		 * nenhum volume responder por vários minutos, nunca pela duração total. */
		return time() - (int) $status['updated'] > 300;
	}
	return time() - (int) $status['updated'] > 180;
}

function pw_printway_backup_current_file( $file, $message = 'Processando arquivo', $done = null, $total = null, $unit = '', $force = false ) {
	static $last_update = 0;
	if ( ! $force && time() === $last_update ) {
		return;
	}
	$last_update = time();
	$status = get_transient( 'pw_printway_backup_progress' );
	if ( ! is_array( $status ) || ! pw_printway_backup_can_update_status( $status ) ) {
		return;
	}
	$status['current'] = (string) $file;
	$status['message'] = $message;
	$status['current_done'] = is_numeric( $done ) ? max( 0, (int) $done ) : 0;
	$status['current_total'] = is_numeric( $total ) ? max( 0, (int) $total ) : 0;
	$status['current_percent'] = $status['current_total'] > 0 ? min( 100, (int) round( ( $status['current_done'] / $status['current_total'] ) * 100 ) ) : -1;
	$status['current_unit'] = sanitize_text_field( (string) $unit );
	$status['updated'] = time();
	set_transient( 'pw_printway_backup_progress', $status, HOUR_IN_SECONDS );
}

/* Estado terminal separado: limpa os indicadores transitórios da validação para
 * que nenhuma rotina de tela continue tratando o último volume como pendente. */
function pw_printway_backup_mark_complete_status() {
	$status = get_transient( 'pw_printway_backup_progress' );
	if ( ! is_array( $status ) || ! pw_printway_backup_can_update_status( $status ) ) {
		return;
	}
	$status['percent']         = 100;
	$status['state']           = 'complete';
	$status['phase']           = 'complete';
	$status['phase_started']   = time();
	$status['expected_seconds'] = 0;
	$status['current']         = '';
	/* Mantém a contagem final apenas para a mensagem de conclusão; o item atual fica limpo. */
	$status['current_done']    = max( 0, absint( $status['current_total'] ?? 0 ) );
	$status['current_total']   = max( 0, absint( $status['current_total'] ?? 0 ) );
	$status['current_percent'] = -1;
	$status['current_unit']    = '';
	$status['updated']         = time();
	set_transient( 'pw_printway_backup_progress', $status, HOUR_IN_SECONDS );
}

function pw_printway_backup_plugin_version() {
	static $version = null;
	if ( null !== $version ) {
		return $version;
	}
	$header = get_file_data( dirname( __DIR__ ) . '/printway.php', array( 'Version' => 'Version' ), 'plugin' );
	$version = ! empty( $header['Version'] ) ? (string) $header['Version'] : '—';
	return $version;
}

/**
 * Valida a estrutura de um volume sem reler e recalcular o SHA-256 de todo o
 * conteúdo. O próprio formato ZIP mantém CRC individual para cada arquivo e a
 * restauração volta a conferir a leitura dos itens antes de gravá-los.
 */
function pw_printway_backup_validate_zip_volume( $path ) {
	if ( ! is_file( $path ) || filesize( $path ) <= 0 ) {
		throw new RuntimeException( 'O volume ' . basename( $path ) . ' está vazio ou não foi encontrado.' );
	}
	$zip   = new ZipArchive();
	$flags = defined( 'ZipArchive::CHECKCONS' ) ? ZipArchive::CHECKCONS : 0;
	$open  = $zip->open( $path, $flags );
	if ( true !== $open ) {
		throw new RuntimeException( 'O volume ' . basename( $path ) . ' não passou na validação estrutural do ZIP.' );
	}
	$entries = (int) $zip->numFiles;
	$zip->close();
	if ( $entries < 1 ) {
		throw new RuntimeException( 'O volume ' . basename( $path ) . ' não contém arquivos válidos.' );
	}
	return $entries;
}

function pw_printway_backup_cancel_requested() {
	return (bool) get_transient( 'pw_printway_backup_cancel' );
}

function pw_printway_backup_check_cancel() {
	if ( pw_printway_backup_cancel_requested() ) {
		throw new RuntimeException( 'Backup interrompido por solicitação do administrador.' );
	}
}

function pw_printway_backup_redirect( $tab = 'backup' ) {
	wp_safe_redirect( add_query_arg( array( 'page' => 'pw-printway-backup', 'tab' => $tab ), admin_url( 'admin.php' ) ) );
	exit;
}

function pw_printway_backup_components() {
	return array(
		'database'      => 'Banco de dados do WordPress',
		'core'          => 'Arquivos e pastas nativas / raiz do WordPress',
		'plugins'       => 'Plugins instalados',
		'themes'        => 'Temas instalados',
		'uploads'       => 'Uploads, imagens e mídias',
		'content_other' => 'Outros arquivos de wp-content',
		'config'        => 'wp-config.php e .htaccess',
	);
}

function pw_printway_backup_component_items( $component ) {
	global $wpdb;
	$items = array();
	if ( 'database' === $component ) {
		$prefix = $wpdb->esc_like( $wpdb->prefix ) . '%';
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix ) ) as $table ) {
			$items[ $table ] = $table;
		}
		return $items;
	}
	$directories = array(
		'plugins'       => WP_PLUGIN_DIR,
		'themes'        => get_theme_root(),
		'uploads'       => wp_upload_dir()['basedir'],
		'content_other' => WP_CONTENT_DIR,
	);
	if ( isset( $directories[ $component ] ) && is_dir( $directories[ $component ] ) ) {
		$skip = 'content_other' === $component ? array( 'plugins', 'themes', 'uploads', 'printway-backups' ) : array();
		if ( 'plugins' === $component ) {
			/* O próprio plugin de backup nunca entra num conjunto que ele mesmo
			 * precisará restaurar. Isso evita sobrescrever o código em execução. */
			$skip[] = basename( untrailingslashit( wp_normalize_path( PW_PRINTWAY_DIR ) ) );
		}
		foreach ( new DirectoryIterator( $directories[ $component ] ) as $item ) {
			if ( $item->isDot() || in_array( $item->getFilename(), $skip, true ) ) { continue; }
			$items[ $item->getFilename() ] = $item->getFilename() . ( $item->isDir() ? ' (pasta)' : ' (arquivo)' );
		}
	} elseif ( 'core' === $component ) {
		foreach ( new DirectoryIterator( ABSPATH ) as $item ) {
			if ( $item->isDot() || 'wp-content' === $item->getFilename() || in_array( $item->getFilename(), array( 'wp-config.php', '.htaccess' ), true ) ) { continue; }
			$items[ $item->getFilename() ] = $item->getFilename() . ( $item->isDir() ? ' (pasta)' : ' (arquivo)' );
		}
	} elseif ( 'config' === $component ) {
		foreach ( array( 'wp-config.php', '.htaccess' ) as $name ) { if ( is_file( ABSPATH . $name ) ) { $items[ $name ] = $name; } }
	}
	uksort( $items, 'strnatcasecmp' );
	return $items;
}

function pw_printway_backup_protected_plugin_directory() {
	return wp_normalize_path( untrailingslashit( PW_PRINTWAY_DIR ) );
}

function pw_printway_backup_is_protected_destination( $path ) {
	$path       = wp_normalize_path( $path );
	$plugin_dir = pw_printway_backup_protected_plugin_directory();
	$backup_dir = wp_normalize_path( untrailingslashit( pw_printway_backup_directory() ) );
	foreach ( array( $plugin_dir, $backup_dir ) as $protected ) {
		if ( $path === $protected || 0 === strpos( $path, trailingslashit( $protected ) ) ) {
			return true;
		}
	}
	return false;
}

/* Catálogo portátil de páginas. Backups antigos, que não possuem este bloco,
 * continuam restauráveis, mas não oferecem seleção de páginas individuais. */
function pw_printway_backup_page_snapshots() {
	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
	$output = array();
	foreach ( $pages as $page ) {
		$meta = get_post_meta( $page->ID );
		unset( $meta['_edit_lock'], $meta['_edit_last'] );
		$output[ (string) $page->ID ] = array(
			'ID'             => (int) $page->ID,
			'post_title'     => (string) $page->post_title,
			'post_name'      => (string) $page->post_name,
			'post_content'   => (string) $page->post_content,
			'post_excerpt'   => (string) $page->post_excerpt,
			'post_status'    => (string) $page->post_status,
			'post_parent'    => (int) $page->post_parent,
			'menu_order'     => (int) $page->menu_order,
			'comment_status' => (string) $page->comment_status,
			'ping_status'    => (string) $page->ping_status,
			'post_password'  => (string) $page->post_password,
			'post_date'      => (string) $page->post_date,
			'post_date_gmt'  => (string) $page->post_date_gmt,
			'meta'           => $meta,
		);
	}
	return $output;
}

function pw_printway_backup_selected_items( $settings, $component ) {
	if ( ! isset( $settings['selection'] ) || ! is_array( $settings['selection'] ) || ! array_key_exists( $component, $settings['selection'] ) ) {
		return null;
	}
	return array_values( array_filter( array_map( 'sanitize_text_field', (array) $settings['selection'][ $component ] ) ) );
}

add_filter( 'cron_schedules', 'pw_printway_backup_monthly_schedule' );
function pw_printway_backup_monthly_schedule( $schedules ) {
	$schedules['pw_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Mensal (a cada 30 dias)' );
	$schedules['pw_12_hours'] = array( 'interval' => 12 * HOUR_IN_SECONDS, 'display' => 'A cada 12 horas' );
	return $schedules;
}

function pw_printway_backup_is_admin_screen() {
	return is_admin() && ! wp_doing_ajax() && 'pw-printway-backup' === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
}

/* A tela de acompanhamento não deve disparar um cron atrasado só por ser aberta. */
add_action( 'init', 'pw_printway_backup_pause_cron_on_admin_screen', 0 );
function pw_printway_backup_pause_cron_on_admin_screen() {
	if ( pw_printway_backup_is_admin_screen() && ! defined( 'DISABLE_WP_CRON' ) ) {
		define( 'DISABLE_WP_CRON', true );
	}
}

function pw_printway_backup_schedule() {
	if ( pw_printway_backup_is_admin_screen() ) {
		return;
	}
	$settings = pw_printway_backup_settings();
	$wanted   = in_array( $settings['schedule'], array( 'hourly', 'pw_12_hours', 'daily', 'weekly', 'pw_monthly' ), true ) ? $settings['schedule'] : false;
	$next     = wp_next_scheduled( PW_PRINTWAY_BACKUP_HOOK );
	if ( ! $wanted ) {
		if ( $next ) {
			wp_unschedule_event( $next, PW_PRINTWAY_BACKUP_HOOK );
		}
		return;
	}
	if ( $next && wp_get_schedule( PW_PRINTWAY_BACKUP_HOOK ) !== $wanted ) {
		wp_unschedule_event( $next, PW_PRINTWAY_BACKUP_HOOK );
		$next = false;
	}
	if ( ! $next ) {
		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, $wanted, PW_PRINTWAY_BACKUP_HOOK );
	}
}
add_action( 'init', 'pw_printway_backup_schedule' );
add_action( PW_PRINTWAY_BACKUP_HOOK, 'pw_printway_backup_run_scheduled' );
function pw_printway_backup_run_scheduled() {
	try {
		pw_printway_backup_create( pw_printway_backup_settings(), 'automatico' );
	} catch ( Throwable $error ) {
		error_log( 'PrintWay backup automático: ' . $error->getMessage() );
		if ( function_exists( 'pw_printway_log' ) ) { pw_printway_log( 'backup', 'error', 'Backup automático falhou: ' . $error->getMessage() ); }
	}
}

add_action( PW_PRINTWAY_BACKUP_RUN_HOOK, 'pw_printway_backup_run_queued', 10, 2 );
function pw_printway_backup_run_queued( $token, $kind = 'manual' ) {
	$queued = get_transient( PW_PRINTWAY_BACKUP_QUEUE );
	if ( ! is_array( $queued ) || empty( $queued['token'] ) || ! hash_equals( (string) $queued['token'], (string) $token ) ) {
		return;
	}
	delete_transient( PW_PRINTWAY_BACKUP_QUEUE );
	try {
		pw_printway_backup_create( pw_printway_backup_settings(), sanitize_key( $kind ) ?: 'manual' );
	} catch ( Throwable $error ) {
		$status = get_transient( 'pw_printway_backup_progress' );
		pw_printway_backup_record_failure( 'error', $error->getMessage(), is_array( $status ) ? $status : array() );
		pw_printway_backup_progress( 0, $error->getMessage(), 'error' );
		error_log( 'PrintWay backup em segundo plano: ' . $error->getMessage() );
		if ( function_exists( 'pw_printway_log' ) ) { pw_printway_log( 'backup', 'error', 'Backup em segundo plano falhou: ' . $error->getMessage() ); }
	}
}

/*
 * O backup normal é disponibilizado logo após a validação dos volumes. Em
 * seguida montamos, em pequenas etapas, um único ZIP de transporte contendo
 * todos os volumes e seus metadados. Isso evita prender a conclusão do
 * backup principal numa operação grande de disco.
 */
add_action( PW_PRINTWAY_BACKUP_PACKAGE_HOOK, 'pw_printway_backup_build_package', 10, 1 );

function pw_printway_backup_safe_path( $path ) {
	$path = wp_normalize_path( $path );
	return false === strpos( $path, '../' ) && 0 !== strpos( $path, '/' ) ? ltrim( $path, '/' ) : ltrim( str_replace( '..', '', $path ), '/' );
}

function pw_printway_backup_add_directory( ZipArchive $zip, $source, $inside, $exclude = array(), $selected = null ) {
	if ( ! is_dir( $source ) ) {
		return 0;
	}
	$count    = 0;
	$source   = wp_normalize_path( realpath( $source ) );
	$excluded = array_map( 'wp_normalize_path', $exclude );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
	foreach ( $iterator as $file ) {
		pw_printway_backup_check_cancel();
		if ( ! $file->isFile() || $file->isLink() ) {
			continue;
		}
		$real = wp_normalize_path( $file->getRealPath() );
		$skip = false;
		foreach ( $excluded as $excluded_path ) {
			if ( 0 === strpos( $real, trailingslashit( $excluded_path ) ) || $real === $excluded_path ) {
				$skip = true;
				break;
			}
		}
		if ( $skip ) {
			continue;
		}
		$relative = ltrim( substr( $real, strlen( $source ) ), '/' );
		pw_printway_backup_current_file( $relative, 'Copiando arquivo: ' . $relative );
		$top_item = strtok( $relative, '/' );
		if ( is_array( $selected ) && ! in_array( $top_item, $selected, true ) ) {
			continue;
		}
		$inside_name = trailingslashit( $inside ) . $relative;
		if ( $zip->addFile( $real, $inside_name ) ) {
			pw_printway_backup_zip_stat( $real, $zip, $inside_name );
			$count++;
		}
	}
	return $count;
}

function pw_printway_backup_add_root( ZipArchive $zip, $selected = null ) {
	$count   = 0;
	$root    = wp_normalize_path( ABSPATH );
	$exclude = array( wp_normalize_path( WP_CONTENT_DIR ) );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
	foreach ( $iterator as $file ) {
		pw_printway_backup_check_cancel();
		if ( ! $file->isFile() || $file->isLink() ) {
			continue;
		}
		$real = wp_normalize_path( $file->getRealPath() );
		$skip = false;
		foreach ( $exclude as $excluded_path ) {
			if ( 0 === strpos( $real, trailingslashit( $excluded_path ) ) || $real === $excluded_path ) {
				$skip = true;
				break;
			}
		}
		if ( $skip || in_array( basename( $real ), array( 'wp-config.php', '.htaccess' ), true ) ) {
			continue;
		}
		$relative = ltrim( substr( $real, strlen( $root ) ), '/' );
		pw_printway_backup_current_file( $relative, 'Copiando arquivo: ' . $relative );
		$top_item = strtok( $relative, '/' );
		if ( is_array( $selected ) && ! in_array( $top_item, $selected, true ) ) {
			continue;
		}
		$inside_name = 'files/root/' . $relative;
		if ( $zip->addFile( $real, $inside_name ) ) {
			pw_printway_backup_zip_stat( $real, $zip, $inside_name );
			$count++;
		}
	}
	return $count;
}

function pw_printway_backup_database_dump( $file, $selected = null, $on_table = null ) {
	global $wpdb;
	$prefix = $wpdb->esc_like( $wpdb->prefix ) . '%';
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix ) );
	if ( is_array( $selected ) ) {
		$tables = array_values( array_intersect( $tables, $selected ) );
	}
	if ( empty( $tables ) ) {
		throw new RuntimeException( 'Não foi possível localizar as tabelas do WordPress.' );
	}
	$handle = fopen( $file, 'wb' );
	if ( ! $handle ) {
		throw new RuntimeException( 'Não foi possível criar o arquivo temporário do banco de dados.' );
	}
	fwrite( $handle, "SET foreign_key_checks = 0;\n" );
	$total_tables = count( $tables );
	foreach ( $tables as $table_index => $table ) {
		pw_printway_backup_check_cancel();
		if ( is_callable( $on_table ) ) {
			call_user_func( $on_table, $table_index, $total_tables, $table );
		}
		pw_printway_backup_current_file( $table, 'Copiando tabela do banco: ' . $table );
		$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . str_replace( '`', '``', $table ) . '`', ARRAY_N );
		if ( empty( $create[1] ) ) {
			fclose( $handle );
			throw new RuntimeException( 'Não foi possível ler a estrutura da tabela ' . $table . '.' );
		}
		fwrite( $handle, "DROP TABLE IF EXISTS `" . str_replace( '`', '``', $table ) . "`;\n" . $create[1] . ";\n" );
		/*
		 * Em tabelas grandes, OFFSET fica mais lento a cada lote. Quando há
		 * chave primária numérica (como action_id), a leitura avança por ela.
		 */
		$keys       = $wpdb->get_results( 'SHOW KEYS FROM `' . str_replace( '`', '``', $table ) . '` WHERE Key_name = "PRIMARY"', ARRAY_A );
		$primary    = count( (array) $keys ) === 1 && ! empty( $keys[0]['Column_name'] ) ? $keys[0]['Column_name'] : '';
		$column     = $primary ? $wpdb->get_row( 'SHOW COLUMNS FROM `' . str_replace( '`', '``', $table ) . '` LIKE "' . esc_sql( $primary ) . '"', ARRAY_A ) : array();
		$table_info = $wpdb->get_row( 'SHOW TABLE STATUS LIKE "' . esc_sql( $table ) . '"', ARRAY_A );
		$estimated_rows = ! empty( $table_info['Rows'] ) ? (int) $table_info['Rows'] : 0;
		$processed_rows = 0;
		$cursor     = 0;
		$offset     = 0;
		$limit      = 750;
		$use_cursor = '' !== $primary && ! empty( $column['Type'] ) && (bool) preg_match( '/(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/i', $column['Type'] );
		do {
			pw_printway_backup_check_cancel();
			if ( $use_cursor ) {
				$query = 'SELECT * FROM `' . str_replace( '`', '``', $table ) . '` WHERE `' . str_replace( '`', '``', $primary ) . '` > ' . absint( $cursor ) . ' ORDER BY `' . str_replace( '`', '``', $primary ) . '` ASC LIMIT ' . absint( $limit );
			} else {
				$query = 'SELECT * FROM `' . str_replace( '`', '``', $table ) . '` LIMIT ' . absint( $limit ) . ' OFFSET ' . absint( $offset );
			}
			$rows = $wpdb->get_results( $query, ARRAY_A );
			foreach ( (array) $rows as $row ) {
				$values = array();
				foreach ( $row as $value ) {
					$values[] = is_null( $value ) ? 'NULL' : $wpdb->prepare( '%s', (string) $value );
				}
				if ( false === fwrite( $handle, 'INSERT INTO `' . str_replace( '`', '``', $table ) . '` VALUES (' . implode( ',', $values ) . ");\n" ) ) {
					fclose( $handle );
					throw new RuntimeException( 'O servidor interrompeu a gravação do banco de dados por falta de espaço.' );
				}
			}
			$read    = count( (array) $rows );
			$processed_rows += $read;
			if ( $use_cursor && $read ) {
				$last_row = $rows[ $read - 1 ];
				if ( isset( $last_row[ $primary ] ) && is_numeric( $last_row[ $primary ] ) ) {
					$cursor = (int) $last_row[ $primary ];
				} else {
					/* Chaves não numéricas usam o modo compatível por OFFSET. */
					$use_cursor = false;
					$offset     = $read;
				}
			} else {
				$offset += $read;
			}
			$table_percent = $estimated_rows > 0 ? min( 100, (int) round( ( $processed_rows / $estimated_rows ) * 100 ) ) : 0;
			$table_status  = $table . ' — ' . number_format_i18n( $processed_rows ) . ( $estimated_rows > 0 ? ' de aproximadamente ' . number_format_i18n( $estimated_rows ) . ' registros (' . $table_percent . '%)' : ' registros copiados' );
			pw_printway_backup_current_file( $table_status, 'Copiando tabela do banco: ' . $table, $processed_rows, $estimated_rows, 'registros' );
			unset( $rows );
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}
		} while ( $read === $limit );
	}
	fwrite( $handle, "SET foreign_key_checks = 1;\n" );
	fclose( $handle );
	return array( 'tables' => count( $tables ), 'checksum' => hash_file( 'sha256', $file ) );
}

function pw_printway_backup_create( $settings, $kind = 'manual' ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		throw new RuntimeException( 'O servidor não possui a extensão ZIP ativada.' );
	}
	if ( get_transient( 'pw_printway_backup_lock' ) ) {
		$status = get_transient( 'pw_printway_backup_progress' );
		if ( pw_printway_backup_status_is_stale( $status ) ) {
			delete_transient( 'pw_printway_backup_lock' );
		} else {
			throw new RuntimeException( 'Já existe um backup em andamento. Interrompa o processo atual e, depois, inicie outro manualmente.' );
		}
	}
	delete_transient( 'pw_printway_backup_cancel' );
	$GLOBALS['pw_printway_backup_run_token'] = wp_generate_uuid4();
	$GLOBALS['pw_printway_backup_kind'] = $kind;
	$started_at = time();
	$GLOBALS['pw_printway_backup_zip_stats'] = array(
		'files' => 0,
		'bytes' => 0,
		'stored_files' => 0,
		'compressed_files' => 0,
		'last_update' => 0,
	);
	pw_printway_backup_progress( 1, 'Preparando o backup…' );
	pw_printway_backup_set_phase( 'preparing' );
	set_transient( 'pw_printway_backup_lock', array( 'run_id' => pw_printway_backup_run_token(), 'started' => time() ), HOUR_IN_SECONDS );
	@set_time_limit( 0 );
	@ignore_user_abort( true );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	try {
		$dir      = pw_printway_backup_directory();
		$stamp    = wp_date( 'Ymd-His', time(), pw_printway_backup_timezone() );
		$filename = 'printway-backup-' . $stamp . '-' . wp_generate_password( 6, false, false ) . '.zip';
		$path     = trailingslashit( $dir ) . $filename;
		$temp_dir = trailingslashit( $dir ) . 'tmp-' . wp_generate_uuid4();
		wp_mkdir_p( $temp_dir );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Não foi possível criar o arquivo ZIP do backup.' );
		}
		$zip_is_open = true;
		$GLOBALS['pw_printway_backup_zip_runtime'] = array(
			'path'          => $path,
			'current_path'  => $path,
			'parts'         => array(),
			'volume_number' => 1,
			'volume_limit_bytes' => max( 0, absint( $settings['zip_volume_size_mb'] ?? 256 ) ) * MB_IN_BYTES,
			'flushed_files' => 0,
			'flushed_bytes' => 0,
			'flush_number'  => 0,
		);
		$components = array();
		$files      = 0;
		$database_selected = pw_printway_backup_selected_items( $settings, 'database' );
		if ( ! empty( $settings['database'] ) && ( null === $database_selected || ! empty( $database_selected ) ) ) {
			pw_printway_backup_progress( 8, 'Criando cópia do banco de dados…' );
			$sql = trailingslashit( $temp_dir ) . 'database.sql';
			$database = pw_printway_backup_database_dump( $sql, $database_selected, function( $index, $total, $table ) {
				$percent = 8 + (int) floor( 16 * ( $index / max( 1, $total ) ) );
				pw_printway_backup_progress( $percent, 'Criando cópia da tabela: ' . $table );
			} );
			$zip->addFile( $sql, 'database/database.sql' );
			pw_printway_backup_zip_stat( $sql, $zip, 'database/database.sql' );
			$components['database'] = array_merge( $database, array( 'selected' => $database_selected ) );
		}
		$core_selected = pw_printway_backup_selected_items( $settings, 'core' );
		if ( ! empty( $settings['core'] ) && ( null === $core_selected || ! empty( $core_selected ) ) ) {
			pw_printway_backup_progress( 25, 'Copiando arquivos nativos do WordPress…' );
			$files += pw_printway_backup_add_root( $zip, $core_selected );
			$components['core'] = array( 'selected' => $core_selected );
		}
		$backup_dir = pw_printway_backup_directory();
		$plugins_selected = pw_printway_backup_selected_items( $settings, 'plugins' );
		if ( ! empty( $settings['plugins'] ) && ( null === $plugins_selected || ! empty( $plugins_selected ) ) ) {
			pw_printway_backup_progress( 42, 'Copiando plugins selecionados…' );
			$files += pw_printway_backup_add_directory( $zip, WP_PLUGIN_DIR, 'files/wp-content/plugins', array( $backup_dir, pw_printway_backup_protected_plugin_directory() ), $plugins_selected );
			$components['plugins'] = array( 'selected' => $plugins_selected );
		}
		$themes_selected = pw_printway_backup_selected_items( $settings, 'themes' );
		if ( ! empty( $settings['themes'] ) && ( null === $themes_selected || ! empty( $themes_selected ) ) ) {
			pw_printway_backup_progress( 58, 'Copiando temas selecionados…' );
			$files += pw_printway_backup_add_directory( $zip, get_theme_root(), 'files/wp-content/themes', array( $backup_dir ), $themes_selected );
			$components['themes'] = array( 'selected' => $themes_selected );
		}
		$uploads_selected = pw_printway_backup_selected_items( $settings, 'uploads' );
		if ( ! empty( $settings['uploads'] ) && ( null === $uploads_selected || ! empty( $uploads_selected ) ) ) {
			pw_printway_backup_progress( 72, 'Copiando mídias e uploads selecionados…' );
			$uploads = wp_upload_dir();
			$files += pw_printway_backup_add_directory( $zip, $uploads['basedir'], 'files/wp-content/uploads', array( $backup_dir ), $uploads_selected );
			$components['uploads'] = array( 'selected' => $uploads_selected );
		}
		$content_selected = pw_printway_backup_selected_items( $settings, 'content_other' );
		if ( ! empty( $settings['content_other'] ) && ( null === $content_selected || ! empty( $content_selected ) ) ) {
			pw_printway_backup_progress( 84, 'Copiando outros arquivos de wp-content…' );
			$files += pw_printway_backup_add_directory( $zip, WP_CONTENT_DIR, 'files/wp-content', array( WP_PLUGIN_DIR, get_theme_root(), wp_upload_dir()['basedir'], $backup_dir ), $content_selected );
			$components['content_other'] = array( 'selected' => $content_selected );
		}
		$config_selected = pw_printway_backup_selected_items( $settings, 'config' );
		if ( ! empty( $settings['config'] ) && ( null === $config_selected || ! empty( $config_selected ) ) ) {
			pw_printway_backup_progress( 91, 'Incluindo arquivos de configuração…' );
			foreach ( array( 'wp-config.php', '.htaccess' ) as $name ) {
				if ( is_array( $config_selected ) && ! in_array( $name, $config_selected, true ) ) { continue; }
				$file = ABSPATH . $name;
				$inside_name = 'files/config/' . $name;
				if ( is_file( $file ) && $zip->addFile( $file, $inside_name ) ) {
					pw_printway_backup_zip_stat( $file, $zip, $inside_name );
					$files++;
				}
			}
			$components['config'] = array( 'selected' => $config_selected );
		}
		$zip_stats  = pw_printway_backup_zip_snapshot();
		$zip_files  = (int) ( $zip_stats['files'] ?? $files );
		$zip_bytes  = (int) ( $zip_stats['bytes'] ?? 0 );
		$zip_stored = (int) ( $zip_stats['stored_files'] ?? 0 );
		$zip_compressed = (int) ( $zip_stats['compressed_files'] ?? 0 );
		pw_printway_backup_progress( 94, 'Preparando manifesto do ZIP: ' . number_format_i18n( $zip_files ) . ' arquivos, ' . size_format( $zip_bytes, 2 ) . '.' );
		$manifest = array(
			'format'     => 'printway-site-backup',
			'version'    => 2,
			'wordpress_version' => get_bloginfo( 'version' ),
			'created_at' => $started_at,
			'site_url'   => home_url( '/' ),
			'kind'       => $kind,
			'components' => $components,
			'file_count' => $files,
		);
		if ( isset( $components['database'] ) ) {
			$manifest['pages'] = pw_printway_backup_page_snapshots();
		}
		$runtime = &$GLOBALS['pw_printway_backup_zip_runtime'];
		/* Alguns servidores só atualizam ZipArchive::numFiles depois do close().
		 * Também usamos os contadores próprios para não descartar um volume válido. */
		$current_has_files = (int) $zip->numFiles > 0
			|| $zip_files > (int) ( $runtime['flushed_files'] ?? 0 )
			|| $zip_bytes > (int) ( $runtime['flushed_bytes'] ?? 0 );
		/* Feche o último volume antes de verificar se ele existe no disco. Em
		 * alguns servidores o arquivo só aparece após ZipArchive::close(). */
		if ( $zip_is_open && ! $zip->close() ) {
			throw new RuntimeException( 'O servidor não conseguiu finalizar o último volume ZIP.' );
		}
		$zip_is_open = false;
		if ( $current_has_files ) {
			if ( ! pw_printway_backup_wait_for_volume( $runtime['current_path'] ) ) {
				throw new RuntimeException( 'O último volume ZIP foi fechado, mas não ficou disponível no disco do servidor.' );
			}
			$runtime['parts'][] = $runtime['current_path'];
		} elseif ( ! empty( $runtime['parts'] ) ) {
			@unlink( $runtime['current_path'] );
		}
		$part_paths = array_values( array_unique( array_filter( (array) $runtime['parts'], 'is_file' ) ) );
		if ( empty( $part_paths ) ) {
			throw new RuntimeException( 'Nenhum volume válido foi criado para o backup.' );
		}
		$manifest['volumes'] = array_map( 'basename', $part_paths );
		$manifest['signature'] = hash_hmac( 'sha256', wp_json_encode( $manifest ), wp_salt( 'auth' ) );
		$expected_close = max( 20, min( 180, (int) ceil( 10 + ( filesize( end( $part_paths ) ) / ( 80 * MB_IN_BYTES ) ) ) ) );
		pw_printway_backup_progress( 96, 'Finalizando ' . count( $part_paths ) . ' volume(s) ZIP: ' . number_format_i18n( $zip_files ) . ' arquivos, ' . size_format( $zip_bytes, 2 ) . '.' );
		pw_printway_backup_current_file( 'Finalizando o último volume e gravando o índice do conjunto.', 'Finalizando os volumes ZIP…' );
		pw_printway_backup_set_phase( 'zip_close', $expected_close );
		$index_zip = new ZipArchive();
		if ( true !== $index_zip->open( $path, ZipArchive::CREATE ) ) {
			throw new RuntimeException( 'Não foi possível abrir o primeiro volume para gravar o manifesto.' );
		}
		$index_zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		if ( ! $index_zip->close() ) {
			throw new RuntimeException( 'Não foi possível gravar o manifesto do conjunto de volumes.' );
		}
		pw_printway_backup_check_cancel();
		foreach ( glob( trailingslashit( $temp_dir ) . '*' ) as $temp ) { @unlink( $temp ); }
		@rmdir( $temp_dir );
		pw_printway_backup_progress( 99, 'Salvando informações finais do backup…' );
		$status = get_transient( 'pw_printway_backup_progress' );
		$log = ! empty( $status['log'] ) && is_array( $status['log'] ) ? $status['log'] : array();
		$part_meta = array();
		$total_size = array_sum( array_map( 'filesize', $part_paths ) );
		$part_total = count( $part_paths );
		$validation_expected = max( 30, min( 900, $part_total * 3 ) );
		pw_printway_backup_set_phase( 'validating', $validation_expected );
		foreach ( $part_paths as $part_index => $part_path ) {
			$part_number = $part_index + 1;
			$part_name   = basename( $part_path );
			pw_printway_backup_current_file( 'Validação ZIP — volume ' . $part_number . ' de ' . $part_total . ': ' . $part_name, 'Validando a estrutura dos volumes ZIP…', $part_number - 1, $part_total, 'volumes', true );
			pw_printway_backup_progress( 99, 'Validando estrutura do volume ' . $part_number . ' de ' . $part_total . ': ' . $part_name . '.' );
			$part_size = (int) filesize( $part_path );
			$entries   = pw_printway_backup_validate_zip_volume( $part_path );
			$fingerprint = hash_hmac( 'sha256', $part_name . '|' . $part_size . '|' . $entries . '|' . $manifest['signature'], wp_salt( 'auth' ) );
			$part_meta[] = array( 'file' => $part_name, 'size' => $part_size, 'entries' => $entries, 'checksum' => $fingerprint );
			pw_printway_backup_current_file( 'Validação ZIP — volume ' . $part_number . ' de ' . $part_total . ' validado: ' . $part_name, 'Validação estrutural em andamento…', $part_number, $part_total, 'volumes', true );
			pw_printway_backup_check_cancel();
		}
		$meta = array( 'created_at' => $manifest['created_at'], 'completed_at' => time(), 'duration' => max( 0, time() - $started_at ), 'kind' => $kind, 'wordpress_version' => $manifest['wordpress_version'], 'components' => array_keys( $components ), 'component_details' => $components, 'file_count' => $files, 'size' => $total_size, 'checksum' => $part_meta[0]['checksum'], 'validation_method' => 'zip-structure-v2', 'parts' => $part_meta, 'log_file' => basename( $path ) . '.log.txt' );
		file_put_contents( $path . '.json', wp_json_encode( $meta ) );
		file_put_contents( $path . '.log.txt', implode( PHP_EOL, $log ) . PHP_EOL );
		/* O conjunto de volumes já está pronto. O ZIP único é opcional e só será criado quando solicitado. */
		pw_printway_backup_prune();
		pw_printway_backup_progress( 100, 'Backup concluído com sucesso.', 'complete' );
		pw_printway_backup_mark_complete_status();
		return array( 'path' => $path, 'meta' => $meta );
	} catch ( Throwable $error ) {
		$failure_status = get_transient( 'pw_printway_backup_progress' );
		pw_printway_backup_record_failure( pw_printway_backup_cancel_requested() ? 'cancelled' : 'error', $error->getMessage(), is_array( $failure_status ) ? $failure_status : array() );
		if ( isset( $zip ) && $zip instanceof ZipArchive && ! empty( $zip_is_open ) ) { @$zip->close(); }
		if ( ! empty( $path ) ) {
			foreach ( glob( preg_replace( '/\.zip$/i', '*.zip', $path ) ) ?: array() as $partial_path ) { @unlink( $partial_path ); }
		}
		if ( ! empty( $path ) ) { @unlink( $path . '.json' ); @unlink( $path . '.log.txt' ); }
		if ( ! empty( $temp_dir ) ) { pw_printway_backup_remove_temporary_directory( $temp_dir ); }
		pw_printway_backup_progress( 0, $error->getMessage(), pw_printway_backup_cancel_requested() ? 'cancelled' : 'error' );
		throw $error;
	} finally {
		$lock = get_transient( 'pw_printway_backup_lock' );
		if ( ! is_array( $lock ) || empty( $lock['run_id'] ) || pw_printway_backup_run_token() === (string) $lock['run_id'] ) {
			delete_transient( 'pw_printway_backup_lock' );
		}
		delete_transient( 'pw_printway_backup_cancel' );
		unset( $GLOBALS['pw_printway_backup_zip_runtime'], $GLOBALS['pw_printway_backup_zip_stats'], $GLOBALS['pw_printway_backup_run_token'], $GLOBALS['pw_printway_backup_kind'] );
	}
}

function pw_printway_backup_prune() {
	$settings = pw_printway_backup_settings();
	$rules = array_values( array_intersect( (array) ( $settings['retention_rules'] ?? array( 'count' ) ), array( 'count', 'size', 'age' ) ) );
	if ( empty( $rules ) ) {
		return;
	}
	$minimum = max( 1, min( 100, absint( $settings['retention_minimum'] ?? 1 ) ) );
	$files = pw_printway_backup_list();
	if ( count( $files ) <= $minimum ) {
		return;
	}
	$remove = array();
	$candidates = array_slice( $files, $minimum );
	if ( in_array( 'age', $rules, true ) && ! empty( $settings['retention_age_days'] ) ) {
		$cutoff = time() - ( max( 1, absint( $settings['retention_age_days'] ) ) * DAY_IN_SECONDS );
		foreach ( $candidates as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				$remove[] = $file;
			}
		}
	}
	$remaining = array_values( array_diff( $files, $remove ) );
	if ( in_array( 'count', $rules, true ) ) {
		$limit = max( $minimum, min( 100, absint( $settings['retention'] ?? 5 ) ) );
		$remove = array_merge( $remove, array_slice( $remaining, $limit ) );
		$remaining = array_values( array_diff( $remaining, $remove ) );
	}
	if ( in_array( 'size', $rules, true ) && ! empty( $settings['retention_size_gb'] ) ) {
		$limit_bytes = (float) $settings['retention_size_gb'] * 1024 * 1024 * 1024;
		$total_bytes = array_sum( array_map( 'pw_printway_backup_total_size', $remaining ) );
		for ( $i = count( $remaining ) - 1; $i >= 0 && $total_bytes > $limit_bytes && count( $remaining ) > $minimum; $i-- ) {
			$file = $remaining[ $i ];
			$remove[] = $file;
			$total_bytes -= pw_printway_backup_total_size( $file );
			unset( $remaining[ $i ] );
		}
	}
	foreach ( array_unique( $remove ) as $file ) {
		pw_printway_backup_delete_set( $file );
	}
}

function pw_printway_backup_list() {
	$files = glob( trailingslashit( pw_printway_backup_directory() ) . '*.zip' );
	$files = array_values( array_filter( $files, function( $file ) {
		$meta = json_decode( (string) @file_get_contents( $file . '.json' ), true );
		/* O arquivo de metadados só é criado depois do fechamento e da validação estrutural de todos os volumes. */
		return is_array( $meta ) && ! empty( $meta['completed_at'] ) && ! empty( $meta['checksum'] );
	} ) );
	usort( $files, function( $a, $b ) { return filemtime( $b ) <=> filemtime( $a ); } );
	return $files;
}

function pw_printway_backup_kind_label( $kind ) {
	$labels = array(
		'automatico'             => 'Automático',
		'antes-da-restauracao'    => 'Segurança antes da restauração',
		'manual'                  => 'Manual',
	);
	return $labels[ (string) $kind ] ?? 'Manual';
}

function pw_printway_backup_duration_label( $seconds ) {
	$seconds = max( 0, absint( $seconds ) );
	if ( ! $seconds ) { return 'Não registrado'; }
	$hours = floor( $seconds / HOUR_IN_SECONDS );
	$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
	$seconds = $seconds % MINUTE_IN_SECONDS;
	return ( $hours ? $hours . 'h ' : '' ) . sprintf( '%02dmin %02ds', $minutes, $seconds );
}

function pw_printway_backup_file_meta( $file ) {
	$meta = json_decode( (string) @file_get_contents( $file . '.json' ), true );
	$meta = is_array( $meta ) ? $meta : array();
	$meta['kind'] = $meta['kind'] ?? 'manual';
	$meta['duration'] = absint( $meta['duration'] ?? 0 );
	return $meta;
}

function pw_printway_backup_volume_paths( $file ) {
	$directory = trailingslashit( pw_printway_backup_directory() );
	$meta = pw_printway_backup_file_meta( $file );
	$paths = array();
	foreach ( (array) ( $meta['parts'] ?? array() ) as $part ) {
		$name = is_array( $part ) ? (string) ( $part['file'] ?? '' ) : (string) $part;
		$name = basename( sanitize_file_name( $name ) );
		$path = $name ? $directory . $name : '';
		if ( $path && is_file( $path ) && 'zip' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$paths[] = $path;
		}
	}
	if ( empty( $paths ) && is_file( $file ) ) {
		$paths[] = $file;
	}
	return array_values( array_unique( $paths ) );
}

function pw_printway_backup_packages_directory() {
	$directory = trailingslashit( pw_printway_backup_directory() ) . 'packages';
	if ( ! is_dir( $directory ) ) { wp_mkdir_p( $directory ); }
	return trailingslashit( $directory );
}

function pw_printway_backup_public_download_directory() {
	$uploads = wp_upload_dir();
	$directory = trailingslashit( $uploads['basedir'] ) . 'printway-backup-downloads';
	if ( ! is_dir( $directory ) ) { wp_mkdir_p( $directory ); }
	if ( ! file_exists( trailingslashit( $directory ) . 'index.php' ) ) { file_put_contents( trailingslashit( $directory ) . 'index.php', '<?php // Silence is golden.' ); }
	return trailingslashit( $directory );
}

function pw_printway_backup_cleanup_public_downloads() {
	$root = pw_printway_backup_public_download_directory();
	foreach ( glob( $root . '*' ) ?: array() as $directory ) {
		if ( ! is_dir( $directory ) || time() - (int) @filemtime( $directory ) < HOUR_IN_SECONDS ) { continue; }
		foreach ( glob( trailingslashit( $directory ) . '*' ) ?: array() as $item ) { if ( is_file( $item ) || is_link( $item ) ) { @unlink( $item ); } }
		@rmdir( $directory );
	}
}

function pw_printway_backup_public_download_url( $path ) {
	/* O WordPress autentica o pedido; depois LiteSpeed/Apache entrega o arquivo diretamente, sem o limite de transmissão do PHP. */
	pw_printway_backup_cleanup_public_downloads();
	$token = wp_generate_password( 48, false, false );
	$directory = pw_printway_backup_public_download_directory() . $token;
	if ( ! wp_mkdir_p( $directory ) ) { return ''; }
	$target = trailingslashit( $directory ) . basename( $path );
	$linked = function_exists( 'link' ) && @link( $path, $target );
	if ( ! $linked ) {
		/* Em hospedagens que não permitem hard-link, mantém o método protegido já existente. */
		@rmdir( $directory );
		return '';
	}
	$rules = "Options -Indexes\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{QUERY_STRING} !(^|&)token=" . preg_quote( $token, '/' ) . "(&|$) [NC]\nRewriteRule ^ - [F,L]\n</IfModule>\n";
	file_put_contents( trailingslashit( $directory ) . '.htaccess', $rules );
	file_put_contents( trailingslashit( $directory ) . 'index.php', '<?php // Silence is golden.' );
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['baseurl'] ) . 'printway-backup-downloads/' . rawurlencode( $token ) . '/' . rawurlencode( basename( $path ) ) . '?token=' . rawurlencode( $token );
}

function pw_printway_backup_update_meta( $file, $meta ) {
	if ( ! is_array( $meta ) || ! is_file( $file ) ) { return false; }
	return false !== file_put_contents( $file . '.json', wp_json_encode( $meta, JSON_UNESCAPED_SLASHES ) );
}

function pw_printway_backup_package_data( $file ) {
	$meta = pw_printway_backup_file_meta( $file );
	$package = isset( $meta['package'] ) && is_array( $meta['package'] ) ? $meta['package'] : array();
	$package['state'] = sanitize_key( (string) ( $package['state'] ?? 'none' ) );
	$package['parts_total'] = absint( $package['parts_total'] ?? 0 );
	$package['parts_done'] = absint( $package['parts_done'] ?? 0 );
	$package['bytes_total'] = (float) ( $package['bytes_total'] ?? 0 );
	$package['bytes_done'] = (float) ( $package['bytes_done'] ?? 0 );
	return $package;
}

function pw_printway_backup_queue_package( $file ) {
	$parts = pw_printway_backup_volume_paths( $file );
	if ( empty( $parts ) ) { return false; }
	$meta = pw_printway_backup_file_meta( $file );
	$old  = pw_printway_backup_package_data( $file );
	if ( 'complete' === ( $old['state'] ?? '' ) || 'building' === ( $old['state'] ?? '' ) ) { return true; }
	if ( ! empty( $old['file'] ) ) {
		$old_package = pw_printway_backup_packages_directory() . basename( sanitize_file_name( $old['file'] ) );
		if ( is_file( $old_package ) ) { @unlink( $old_package ); }
	}
	$package_name = preg_replace( '/\.zip$/i', '', basename( $file ) ) . '-conjunto-completo.zip';
	$meta['package'] = array(
		'state' => 'building', 'file' => $package_name, 'parts_total' => count( $parts ), 'parts_done' => 0,
		'bytes_total' => array_sum( array_map( 'filesize', $parts ) ), 'bytes_done' => 0,
		'started_at' => time(), 'updated_at' => time(), 'message' => 'Preparando pacote único para download.',
	);
	if ( ! pw_printway_backup_update_meta( $file, $meta ) ) { return false; }
	wp_schedule_single_event( time() + 5, PW_PRINTWAY_BACKUP_PACKAGE_HOOK, array( basename( $file ) ) );
	if ( function_exists( 'spawn_cron' ) ) { spawn_cron( time() ); }
	return true;
}

function pw_printway_backup_validate_package( $path, $parts ) {
	$entries = pw_printway_backup_validate_zip_volume( $path );
	if ( $entries < count( $parts ) + 2 ) { throw new RuntimeException( 'O pacote único não contém todos os volumes esperados.' ); }
	$zip = new ZipArchive();
	$open = $zip->open( $path, defined( 'ZipArchive::CHECKCONS' ) ? ZipArchive::CHECKCONS : 0 );
	if ( true !== $open ) { throw new RuntimeException( 'O pacote único não passou na validação estrutural.' ); }
	foreach ( $parts as $part ) {
		$stat = $zip->statName( 'volumes/' . basename( $part ) );
		if ( ! is_array( $stat ) || (int) ( $stat['size'] ?? 0 ) !== (int) filesize( $part ) ) { $zip->close(); throw new RuntimeException( 'Um volume do pacote único está ausente ou incompleto.' ); }
	}
	$zip->close();
	return true;
}

function pw_printway_backup_build_package( $backup_name ) {
	$backup_name = basename( sanitize_file_name( $backup_name ) );
	$file = trailingslashit( pw_printway_backup_directory() ) . $backup_name;
	if ( ! is_file( $file ) || ! in_array( $file, pw_printway_backup_list(), true ) ) { return; }
	$lock_key = 'pw_printway_backup_package_lock_' . md5( $backup_name );
	if ( get_transient( $lock_key ) ) { return; }
	set_transient( $lock_key, 1, 10 * MINUTE_IN_SECONDS );
	try {
		$meta = pw_printway_backup_file_meta( $file );
		$package = pw_printway_backup_package_data( $file );
		if ( 'complete' === $package['state'] || 'error' === $package['state'] ) { return; }
		$parts = pw_printway_backup_volume_paths( $file );
		$index = min( count( $parts ), absint( $package['parts_done'] ) );
		$package_path = pw_printway_backup_packages_directory() . basename( sanitize_file_name( (string) ( $package['file'] ?? '' ) ) );
		if ( ! $package_path ) { throw new RuntimeException( 'Nome inválido para o pacote único.' ); }
		if ( $index < count( $parts ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $package_path, ZipArchive::CREATE ) ) { throw new RuntimeException( 'Não foi possível abrir o pacote único para gravação.' ); }
			$part = $parts[ $index ];
			$inside = 'volumes/' . basename( $part );
			if ( ! $zip->addFile( $part, $inside ) ) { $zip->close(); throw new RuntimeException( 'Não foi possível incluir um volume no pacote único.' ); }
			if ( method_exists( $zip, 'setCompressionName' ) ) { @$zip->setCompressionName( $inside, ZipArchive::CM_STORE ); }
			if ( ! $zip->close() ) { throw new RuntimeException( 'Não foi possível gravar o volume no pacote único.' ); }
			$package['parts_done'] = $index + 1;
			$package['bytes_done'] = (float) ( $package['bytes_done'] + filesize( $part ) );
			$package['updated_at'] = time();
			$package['message'] = 'Incluindo volume ' . ( $index + 1 ) . ' de ' . count( $parts ) . ' no pacote único.';
			$meta['package'] = $package;
			pw_printway_backup_update_meta( $file, $meta );
			wp_schedule_single_event( time() + 2, PW_PRINTWAY_BACKUP_PACKAGE_HOOK, array( $backup_name ) );
			if ( function_exists( 'spawn_cron' ) ) { spawn_cron( time() ); }
			return;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $package_path, ZipArchive::CREATE ) ) { throw new RuntimeException( 'Não foi possível finalizar o pacote único.' ); }
		$meta_copy = wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$zip->addFromString( 'indice/' . basename( $file ) . '.json', $meta_copy );
		if ( is_file( $file . '.log.txt' ) ) { $zip->addFile( $file . '.log.txt', 'logs/' . basename( $file ) . '.log.txt' ); }
		$zip->addFromString( 'LEIA-ME.txt', "Pacote único PrintWay. Contém todos os volumes ZIP e o arquivo de índice do backup.\nPara restauração no servidor, mantenha os volumes e o índice juntos.\n" );
		if ( ! $zip->close() || ! pw_printway_backup_validate_package( $package_path, $parts ) ) { throw new RuntimeException( 'Não foi possível validar o pacote único.' ); }
		$package['state'] = 'complete';
		$package['completed_at'] = time();
		$package['updated_at'] = time();
		$package['bytes_done'] = $package['bytes_total'];
		$package['size'] = (int) filesize( $package_path );
		$package['message'] = 'Pacote único concluído e pronto para download.';
		$meta['package'] = $package;
		pw_printway_backup_update_meta( $file, $meta );
	} catch ( Throwable $error ) {
		$meta = pw_printway_backup_file_meta( $file );
		$package = pw_printway_backup_package_data( $file );
		$package['state'] = 'error'; $package['updated_at'] = time(); $package['message'] = $error->getMessage();
		$meta['package'] = $package; pw_printway_backup_update_meta( $file, $meta );
		if ( ! empty( $package['file'] ) ) { @unlink( pw_printway_backup_packages_directory() . basename( $package['file'] ) ); }
		error_log( 'PrintWay pacote único: ' . $error->getMessage() );
		if ( function_exists( 'pw_printway_log' ) ) { pw_printway_log( 'backup', 'error', 'Falha ao gerar pacote único: ' . $error->getMessage() ); }
	} finally { delete_transient( $lock_key ); }
}

/* Importa um ZIP único produzido pelo próprio plugin. O pacote é desfeito em
 * volumes no servidor e só aparece na lista após conferir sua estrutura e a
 * assinatura do manifesto. */
function pw_printway_backup_import_package( $package_path ) {
	if ( ! class_exists( 'ZipArchive' ) || ! is_file( $package_path ) ) {
		throw new RuntimeException( 'O arquivo enviado não está disponível para importação.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $package_path ) ) {
		throw new RuntimeException( 'Não foi possível abrir o ZIP único enviado.' );
	}
	$index_entry = '';
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = (string) $zip->getNameIndex( $i );
		if ( 0 === strpos( $name, 'indice/' ) && preg_match( '/\.zip\.json$/i', $name ) ) {
			$index_entry = $name;
			break;
		}
	}
	if ( ! $index_entry ) {
		$zip->close();
		throw new RuntimeException( 'Este ZIP não é um pacote único PrintWay válido.' );
	}
	$meta = json_decode( (string) $zip->getFromName( $index_entry ), true );
	$backup_name = basename( preg_replace( '/\.json$/i', '', basename( $index_entry ) ) );
	if ( ! is_array( $meta ) || ! preg_match( '/\.zip$/i', $backup_name ) || empty( $meta['parts'] ) ) {
		$zip->close();
		throw new RuntimeException( 'O índice do pacote único está incompleto.' );
	}
	$directory = trailingslashit( pw_printway_backup_directory() );
	$created = array();
	try {
		foreach ( (array) $meta['parts'] as $part ) {
			$part_name = basename( sanitize_file_name( is_array( $part ) ? (string) ( $part['file'] ?? '' ) : (string) $part ) );
			$entry = 'volumes/' . $part_name;
			if ( ! $part_name || false === $zip->locateName( $entry ) ) {
				throw new RuntimeException( 'Um dos volumes obrigatórios está ausente no ZIP único.' );
			}
			$target = $directory . $part_name;
			if ( is_file( $target ) ) {
				throw new RuntimeException( 'Já existe um volume com o nome ' . $part_name . ' no servidor.' );
			}
			$stream = $zip->getStream( $entry );
			$temp = $target . '.importing-' . wp_generate_password( 8, false, false );
			$output = @fopen( $temp, 'wb' );
			if ( ! $stream || ! $output ) {
				if ( $stream ) { fclose( $stream ); }
				throw new RuntimeException( 'Não foi possível preparar o volume ' . $part_name . '.' );
			}
			while ( ! feof( $stream ) ) {
				$buffer = fread( $stream, MB_IN_BYTES );
				if ( false === $buffer || ( '' !== $buffer && false === fwrite( $output, $buffer ) ) ) {
					fclose( $stream ); fclose( $output ); @unlink( $temp );
					throw new RuntimeException( 'Falha ao gravar o volume ' . $part_name . '.' );
				}
			}
			fclose( $stream ); fclose( $output );
			if ( ! @rename( $temp, $target ) || ! pw_printway_backup_validate_zip_volume( $target ) ) {
				@unlink( $temp ); @unlink( $target );
				throw new RuntimeException( 'O volume ' . $part_name . ' não passou na validação.' );
			}
			$created[] = $target;
		}
		$zip->close();
		/* O pacote já contém um índice validado; o ZIP único opcional não é copiado
		 * porque ele pode ser recriado sob demanda no novo servidor. */
		$manifest = pw_printway_backup_manifest( $directory . $backup_name, true );
		unset( $meta['package'] );
		$meta['imported_at'] = time();
		$meta['origin'] = empty( $manifest['_pw_signature_valid'] ) ? 'other_site' : 'this_site';
		$meta['origin_site_url'] = esc_url_raw( (string) ( $manifest['site_url'] ?? '' ) );
		if ( false === file_put_contents( $directory . $backup_name . '.json', wp_json_encode( $meta, JSON_UNESCAPED_SLASHES ) ) ) {
			throw new RuntimeException( 'Não foi possível registrar o backup importado.' );
		}
		$log_entry = 'logs/' . $backup_name . '.log.txt';
		$log_zip = new ZipArchive();
		if ( true === $log_zip->open( $package_path ) ) {
			$log = $log_zip->getFromName( $log_entry );
			if ( false !== $log ) { file_put_contents( $directory . $backup_name . '.log.txt', $log ); }
			$log_zip->close();
		}
		/* Confirma estrutura e manifesto. Backups de outro site continuam exigindo
		 * confirmação adicional somente no momento da restauração. */
		if ( ! in_array( $directory . $backup_name, pw_printway_backup_list(), true ) ) {
			throw new RuntimeException( 'O conjunto importado não foi reconhecido como backup concluído.' );
		}
		return $backup_name;
	} catch ( Throwable $error ) {
		$zip->close();
		foreach ( $created as $created_file ) { @unlink( $created_file ); }
		@unlink( $directory . $backup_name . '.json' );
		@unlink( $directory . $backup_name . '.log.txt' );
		throw $error;
	}
}

/* A importação de um pacote grande é dividida por volume. Assim a última
 * requisição do upload não fica presa tentando descompactar vários GB. */
function pw_printway_backup_import_state_key( $token ) {
	return 'pw_printway_import_' . preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $token );
}

function pw_printway_backup_import_complete_key( $token ) {
	return 'pw_printway_import_complete_' . preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $token );
}

function pw_printway_backup_import_start( $package_path ) {
	/* Não abra um ZIP de vários GB na resposta do último bloco enviado. A
	 * conferência do índice ocorre no primeiro passo assíncrono de importação. */
	if ( ! is_file( $package_path ) ) { throw new RuntimeException( 'O pacote enviado não está disponível.' ); }
	$token = wp_generate_password( 22, false, false );
	$state = array( 'user_id' => get_current_user_id(), 'package_path' => $package_path, 'initialized' => false, 'backup_name' => '', 'meta' => array(), 'parts' => array(), 'index' => 0, 'created' => array() );
	set_transient( pw_printway_backup_import_state_key( $token ), $state, 6 * HOUR_IN_SECONDS );
	set_transient( 'pw_printway_import_pending_' . get_current_user_id(), $token, 6 * HOUR_IN_SECONDS );
	return array( 'token' => $token, 'total' => 0 );
}

function pw_printway_backup_import_step( $token ) {
	/* A resposta da ultima requisicao pode se perder depois que o servidor ja
	 * concluiu a importacao. Nesse caso, devolve novamente o mesmo resultado em
	 * vez de informar que a sessao expirou e deixar a janela tentando para sempre. */
	$completed = get_transient( pw_printway_backup_import_complete_key( $token ) );
	if ( is_array( $completed ) && ! empty( $completed['complete'] ) ) {
		return $completed;
	}
	$state = get_transient( pw_printway_backup_import_state_key( $token ) );
	if ( ! is_array( $state ) || get_current_user_id() !== absint( $state['user_id'] ?? 0 ) ) { throw new RuntimeException( 'A sessão de importação expirou. Envie o ZIP novamente.' ); }
	$directory = trailingslashit( pw_printway_backup_directory() );
	try {
		if ( empty( $state['initialized'] ) ) {
			if ( ! class_exists( 'ZipArchive' ) || ! is_file( $state['package_path'] ) ) { throw new RuntimeException( 'O pacote enviado não está disponível.' ); }
			$initial_zip = new ZipArchive();
			if ( true !== $initial_zip->open( $state['package_path'] ) ) { throw new RuntimeException( 'Não foi possível abrir o ZIP único enviado.' ); }
			$index_entry = '';
			for ( $i = 0; $i < $initial_zip->numFiles; $i++ ) { $name = (string) $initial_zip->getNameIndex( $i ); if ( 0 === strpos( $name, 'indice/' ) && preg_match( '/\.zip\.json$/i', $name ) ) { $index_entry = $name; break; } }
			$meta = $index_entry ? json_decode( (string) $initial_zip->getFromName( $index_entry ), true ) : array();
			$backup_name = $index_entry ? basename( preg_replace( '/\.json$/i', '', basename( $index_entry ) ) ) : '';
			$import_log = $backup_name ? $initial_zip->getFromName( 'logs/' . $backup_name . '.log.txt' ) : false;
			$initial_zip->close();
			if ( ! is_array( $meta ) || ! $backup_name || ! preg_match( '/\.zip$/i', $backup_name ) || empty( $meta['parts'] ) ) { throw new RuntimeException( 'Este ZIP não é um pacote único PrintWay válido.' ); }
			$state['initialized'] = true; $state['backup_name'] = $backup_name; $state['meta'] = $meta; $state['parts'] = array_values( (array) $meta['parts'] );
			if ( false !== $import_log ) { file_put_contents( $directory . $backup_name . '.log.txt', $import_log ); }
			set_transient( pw_printway_backup_import_state_key( $token ), $state, 6 * HOUR_IN_SECONDS );
			return array( 'complete' => false, 'done' => 0, 'total' => count( $state['parts'] ), 'current' => 'Índice do pacote validado. Preparando volumes.' );
		}
		if ( (int) $state['index'] < count( $state['parts'] ) ) {
			$part = $state['parts'][ $state['index'] ];
			$part_name = basename( sanitize_file_name( is_array( $part ) ? (string) ( $part['file'] ?? '' ) : (string) $part ) );
			if ( ! $part_name ) { throw new RuntimeException( 'Nome de volume inválido no pacote.' ); }
			$zip = new ZipArchive();
			if ( true !== $zip->open( $state['package_path'] ) ) { throw new RuntimeException( 'Não foi possível reabrir o pacote enviado.' ); }
			$stream = $zip->getStream( 'volumes/' . $part_name );
			$target = $directory . $part_name; $temp = $target . '.importing-' . wp_generate_password( 8, false, false );
			$output = @fopen( $temp, 'wb' );
			if ( ! $stream || ! $output || is_file( $target ) ) { if ( $stream ) { fclose( $stream ); } if ( $output ) { fclose( $output ); } $zip->close(); throw new RuntimeException( 'Não foi possível preparar o volume ' . $part_name . '.' ); }
			while ( ! feof( $stream ) ) { $buffer = fread( $stream, MB_IN_BYTES ); if ( false === $buffer || ( '' !== $buffer && false === fwrite( $output, $buffer ) ) ) { fclose( $stream ); fclose( $output ); $zip->close(); @unlink( $temp ); throw new RuntimeException( 'Falha ao gravar o volume ' . $part_name . '.' ); } }
			fclose( $stream ); fclose( $output ); $zip->close();
			if ( ! @rename( $temp, $target ) || ! pw_printway_backup_validate_zip_volume( $target ) ) { @unlink( $temp ); @unlink( $target ); throw new RuntimeException( 'O volume ' . $part_name . ' não passou na validação.' ); }
			$state['created'][] = $target; $state['index']++;
			set_transient( pw_printway_backup_import_state_key( $token ), $state, 6 * HOUR_IN_SECONDS );
			return array( 'complete' => false, 'done' => (int) $state['index'], 'total' => count( $state['parts'] ), 'current' => $part_name );
		}
		$manifest = pw_printway_backup_manifest( $directory . $state['backup_name'], true );
		$meta = $state['meta']; unset( $meta['package'] ); $meta['imported_at'] = time(); $meta['origin'] = empty( $manifest['_pw_signature_valid'] ) ? 'other_site' : 'this_site'; $meta['origin_site_url'] = esc_url_raw( (string) ( $manifest['site_url'] ?? '' ) );
		if ( false === file_put_contents( $directory . $state['backup_name'] . '.json', wp_json_encode( $meta, JSON_UNESCAPED_SLASHES ) ) ) { throw new RuntimeException( 'Não foi possível registrar o backup importado.' ); }
		if ( ! in_array( $directory . $state['backup_name'], pw_printway_backup_list(), true ) ) { throw new RuntimeException( 'O conjunto importado não foi reconhecido como backup concluído.' ); }
		$result = array( 'complete' => true, 'done' => count( $state['parts'] ), 'total' => count( $state['parts'] ), 'stored_file' => $state['backup_name'] );
		/* Grava o recibo antes de apagar a sessao e o pacote temporario. Assim a
		 * confirmacao final e idempotente mesmo se o navegador repetir a chamada. */
		set_transient( pw_printway_backup_import_complete_key( $token ), $result, 6 * HOUR_IN_SECONDS );
		@unlink( $state['package_path'] ); delete_transient( pw_printway_backup_import_state_key( $token ) ); delete_transient( 'pw_printway_import_pending_' . get_current_user_id() );
		return $result;
	} catch ( Throwable $error ) {
		foreach ( (array) ( $state['created'] ?? array() ) as $created ) { @unlink( $created ); }
		if ( ! empty( $state['backup_name'] ) ) { @unlink( $directory . $state['backup_name'] . '.json' ); @unlink( $directory . $state['backup_name'] . '.log.txt' ); }
		@unlink( $state['package_path'] ); delete_transient( pw_printway_backup_import_state_key( $token ) ); delete_transient( 'pw_printway_import_pending_' . get_current_user_id() );
		throw $error;
	}
}

function pw_printway_backup_upload_log_path( $upload_id ) {
	$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $upload_id );
	$directory = trailingslashit( pw_printway_backup_directory() ) . 'upload-logs/';
	if ( ! is_dir( $directory ) ) {
		wp_mkdir_p( $directory );
	}
	return $directory . 'upload-' . $id . '.log.txt';
}

function pw_printway_backup_upload_log( $upload_id, $message ) {
	if ( ! $upload_id ) {
		return;
	}
	$line = '[' . wp_date( 'd/m/Y H:i:s', time(), pw_printway_backup_timezone() ) . '] ' . sanitize_text_field( (string) $message ) . PHP_EOL;
	@file_put_contents( pw_printway_backup_upload_log_path( $upload_id ), $line, FILE_APPEND | LOCK_EX );
	@file_put_contents( __DIR__ . '/import.log', '[' . $upload_id . '] ' . $line, FILE_APPEND | LOCK_EX );
}

/* Cria o diagnóstico antes do primeiro bloco. Assim existe um log mesmo se a
 * conexão falhar antes de o receptor leve conseguir gravar qualquer byte. */
add_action( 'wp_ajax_pw_printway_backup_upload_log_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$upload_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['upload_id'] ?? '' ) );
	if ( ! $upload_id ) { wp_send_json_error( array( 'message' => 'Identificador de envio inválido.' ), 400 ); }
	$name = sanitize_file_name( (string) wp_unslash( $_POST['file_name'] ?? 'backup.zip' ) );
	$size = max( 0, (int) ( $_POST['file_size'] ?? 0 ) );
	pw_printway_backup_remember_upload_log( $upload_id );
	@file_put_contents( pw_printway_backup_upload_log_path( $upload_id ), '' );
	pw_printway_backup_upload_log( $upload_id, 'INÍCIO DA IMPORTAÇÃO. Arquivo: ' . $name . '; tamanho: ' . size_format( $size, 2 ) . '; usuário WordPress: ' . get_current_user_id() . '; plugin: ' . ( defined( 'PW_PRINTWAY_VERSION' ) ? PW_PRINTWAY_VERSION : 'desconhecida' ) . '; WordPress: ' . get_bloginfo( 'version' ) . '; PHP: ' . PHP_VERSION . '.' );
	wp_send_json_success( array( 'id' => $upload_id ) );
} );

function pw_printway_backup_remember_upload_log( $upload_id ) {
	$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $upload_id );
	if ( $id ) {
		set_transient( 'pw_printway_last_upload_id_' . get_current_user_id(), $id, 7 * DAY_IN_SECONDS );
	}
}

add_action( 'wp_ajax_pw_printway_backup_upload_log', function() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$requested = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['upload_id'] ?? '' ) );
	$id = $requested ?: get_transient( 'pw_printway_last_upload_id_' . get_current_user_id() );
	$path = $id ? pw_printway_backup_upload_log_path( $id ) : '';
	if ( ! $path || ! is_file( $path ) ) {
		wp_send_json_error( array( 'message' => 'Ainda não há log de envio para mostrar.' ), 404 );
	}
	wp_send_json_success( array( 'id' => $id, 'log' => (string) @file_get_contents( $path ) ) );
} );

/* Consulta minima usada somente para retomar um envio cuja resposta se perdeu.
 * Nao abre o ZIP e nao processa o pacote. */
add_action( 'wp_ajax_pw_printway_backup_upload_status', function() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	nocache_headers();
	$upload_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['upload_id'] ?? '' ) );
	if ( ! $upload_id ) { wp_send_json_error( array( 'message' => 'Identificador de envio inválido.' ), 400 ); }
	$temp = trailingslashit( pw_printway_backup_directory() ) . '.upload-' . $upload_id . '.tmp';
	clearstatcache( true, $temp );
	wp_send_json_success( array( 'received' => is_file( $temp ) ? (int) filesize( $temp ) : 0 ) );
} );

/* Recebe diagnosticos do navegador quando o servidor devolve HTML, resposta
 * vazia, timeout ou outro conteudo que nao seja o JSON esperado. */
add_action( 'wp_ajax_pw_printway_backup_upload_client_log', function() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$upload_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['upload_id'] ?? '' ) );
	if ( ! $upload_id ) { wp_send_json_error( array( 'message' => 'Identificador de envio inválido.' ), 400 ); }
	$payload_raw = (string) wp_unslash( $_POST['payload'] ?? '' );
	$payload = json_decode( $payload_raw, true );
	$payload = is_array( $payload ) ? $payload : array( 'raw' => substr( $payload_raw, 0, 2000 ) );
	$allowed = array( 'event', 'client_time', 'http_status', 'ready_state', 'content_type', 'response_length', 'response_sample', 'user_agent', 'client_offset', 'requested_end', 'chunk_size', 'retries', 'parse_error', 'server_message', 'error', 'timeout_ms' );
	$diagnostic = array();
	foreach ( $allowed as $key ) {
		if ( ! array_key_exists( $key, $payload ) ) { continue; }
		$value = is_scalar( $payload[ $key ] ) ? (string) $payload[ $key ] : wp_json_encode( $payload[ $key ] );
		$diagnostic[ $key ] = substr( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ), 0, 'response_sample' === $key ? 1800 : 350 );
	}
	$temp = trailingslashit( pw_printway_backup_directory() ) . '.upload-' . $upload_id . '.tmp';
	clearstatcache( true, $temp );
	$diagnostic['server_received'] = is_file( $temp ) ? (int) filesize( $temp ) : 0;
	$diagnostic['server_time'] = wp_date( 'd/m/Y H:i:s', time(), pw_printway_backup_timezone() );
	$diagnostic['php_memory_limit'] = (string) ini_get( 'memory_limit' );
	$diagnostic['php_post_max_size'] = (string) ini_get( 'post_max_size' );
	$diagnostic['php_upload_max_filesize'] = (string) ini_get( 'upload_max_filesize' );
	$diagnostic['php_max_execution_time'] = (string) ini_get( 'max_execution_time' );
	$free = @disk_free_space( pw_printway_backup_directory() );
	$diagnostic['disk_free_bytes'] = false === $free ? 'indisponível' : (string) (int) $free;
	pw_printway_backup_remember_upload_log( $upload_id );
	pw_printway_backup_upload_log( $upload_id, 'DIAGNÓSTICO DO NAVEGADOR: ' . wp_json_encode( $diagnostic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	wp_send_json_success();
} );

add_action( 'wp_ajax_pw_printway_backup_upload_chunk', 'pw_printway_backup_upload_chunk' );
function pw_printway_backup_upload_chunk() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$upload_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['upload_id'] ?? '' ) );
	$offset = max( 0, absint( $_POST['offset'] ?? 0 ) );
	$total = max( 0, (int) ( $_POST['total'] ?? 0 ) );
	$is_last = ! empty( $_POST['last'] );
	if ( ! $upload_id || empty( $_FILES['chunk']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['chunk']['error'] ) {
		wp_send_json_error( array( 'message' => 'Bloco de upload inválido.' ), 400 );
	}
	$directory = trailingslashit( pw_printway_backup_directory() );
	$temp = $directory . '.upload-' . $upload_id . '.tmp';
	$current = is_file( $temp ) ? (int) filesize( $temp ) : 0;
	if ( 0 === $offset ) {
		pw_printway_backup_remember_upload_log( $upload_id );
		pw_printway_backup_upload_log( $upload_id, 'Início do envio: ' . size_format( $total, 2 ) . '.' );
	} elseif ( $is_last || ( $offset > 0 && 0 === ( $offset % ( 25 * MB_IN_BYTES ) ) ) ) {
		pw_printway_backup_upload_log( $upload_id, 'Progresso confirmado: ' . size_format( $offset, 2 ) . ' de ' . size_format( $total, 2 ) . '. Servidor está em ' . size_format( $current, 2 ) . '.' );
	}
	if ( $offset !== $current ) {
		/* A resposta do último bloco pode se perder após o servidor já tê-lo
		 * gravado. Não abra nem processe o ZIP nesta requisição: apenas informe
		 * que o arquivo está pronto para a página iniciar a importação depois. */
		if ( $total && $current === $total ) {
			pw_printway_backup_upload_log( $upload_id, 'O servidor já possuía o arquivo completo. Marcado como pronto para importação.' );
			set_transient( 'pw_printway_upload_ready_' . get_current_user_id(), $temp, 6 * HOUR_IN_SECONDS );
			wp_send_json_success( array( 'received' => $current, 'complete' => true, 'message' => 'Upload já concluído no servidor.' ) );
		}
		pw_printway_backup_upload_log( $upload_id, 'Erro de posição: cliente enviou ' . size_format( $offset, 2 ) . ' e o servidor esperava ' . size_format( $current, 2 ) . '.' );
		wp_send_json_error( array( 'message' => 'A posição do bloco não confere. Atualize a página e tente novamente.', 'offset' => $current ), 409 );
	}
	$source = @fopen( $_FILES['chunk']['tmp_name'], 'rb' );
	$target = @fopen( $temp, 'ab' );
	if ( ! $source || ! $target ) { if ( $source ) { fclose( $source ); } if ( $target ) { fclose( $target ); } pw_printway_backup_upload_log( $upload_id, 'Falha: não foi possível abrir o bloco temporário para gravação.' ); wp_send_json_error( array( 'message' => 'Não foi possível gravar o bloco no servidor.' ), 500 ); }
	stream_copy_to_stream( $source, $target ); fclose( $source ); fclose( $target );
	$current = (int) filesize( $temp );
	if ( 0 === $offset || ( $current > 0 && 0 === ( $current % ( 25 * MB_IN_BYTES ) ) ) || ( $total && $current >= $total ) ) {
		pw_printway_backup_upload_log( $upload_id, 'Bloco gravado com sucesso. Total confirmado no servidor: ' . size_format( $current, 2 ) . ' de ' . size_format( $total, 2 ) . '.' );
	}
	if ( $total && $current !== $total && $is_last ) { @unlink( $temp ); pw_printway_backup_upload_log( $upload_id, 'Falha: o último bloco deixou o arquivo incompleto (' . size_format( $current, 2 ) . ' de ' . size_format( $total, 2 ) . ').' ); wp_send_json_error( array( 'message' => 'O arquivo enviado ficou incompleto.' ), 400 ); }
	/* A resposta final deve ser mínima. Processar ou até abrir um ZIP de vários
	 * GB aqui pode segurar o PHP/FastCGI e fazer o navegador aparentar travar. */
	if ( $total && $current === $total ) {
		pw_printway_backup_upload_log( $upload_id, 'Upload concluído. Arquivo completo marcado para importação em nova requisição.' );
		set_transient( 'pw_printway_upload_ready_' . get_current_user_id(), $temp, 6 * HOUR_IN_SECONDS );
		wp_send_json_success( array( 'received' => $current, 'complete' => true, 'message' => 'Upload concluído no servidor.' ) );
	}
	wp_send_json_success( array( 'received' => $current ) );
}

/* A importação só começa em uma nova requisição/página, depois que a última
 * resposta do upload já foi entregue ao navegador. */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) {
		return;
	}
	$key  = 'pw_printway_upload_ready_' . get_current_user_id();
	$temp = get_transient( $key );
	if ( ! is_string( $temp ) || ! is_file( $temp ) ) {
		return;
	}
	try {
		pw_printway_backup_import_start( $temp );
		delete_transient( $key );
	} catch ( Throwable $error ) {
		set_transient( 'pw_printway_backup_import_notice_' . get_current_user_id(), $error->getMessage(), 5 * MINUTE_IN_SECONDS );
	}
}, 20 );

/* Remove somente temporários controlados pelo plugin. Arquivos ativos têm o
 * horário atualizado a cada bloco; os abandonados após 30 minutos são limpos. */
function pw_printway_backup_cleanup_upload_temps( $specific_id = '' ) {
	$directory = trailingslashit( pw_printway_backup_directory() );
	if ( $specific_id ) {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $specific_id );
		$file = $directory . '.upload-' . $id . '.tmp';
		if ( is_file( $file ) ) { @unlink( $file ); }
		$auth = $directory . '.upload-auth-' . $id . '.json';
		if ( is_file( $auth ) ) { @unlink( $auth ); }
		return;
	}
	foreach ( (array) glob( $directory . '.upload-*.tmp' ) as $file ) {
		if ( is_file( $file ) && filemtime( $file ) < ( time() - ( 30 * MINUTE_IN_SECONDS ) ) ) { @unlink( $file ); }
	}
	foreach ( (array) glob( $directory . '.upload-auth-*.json' ) as $file ) {
		if ( is_file( $file ) && filemtime( $file ) < ( time() - ( 6 * HOUR_IN_SECONDS ) ) ) { @unlink( $file ); }
	}
}
add_action( 'admin_init', function() {
	if ( current_user_can( 'manage_options' ) ) { pw_printway_backup_cleanup_upload_temps(); }
} );
add_action( 'wp_ajax_pw_printway_backup_upload_cleanup', function() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	if ( ! empty( $_POST['all'] ) ) {
		$directory = trailingslashit( pw_printway_backup_directory() );
		foreach ( (array) glob( $directory . '.upload-*.tmp' ) as $file ) { if ( is_file( $file ) ) { @unlink( $file ); } }
	} else { pw_printway_backup_cleanup_upload_temps( wp_unslash( $_POST['upload_id'] ?? '' ) ); }
	wp_send_json_success();
} );

add_action( 'wp_ajax_pw_printway_backup_import_step', 'pw_printway_backup_import_step_ajax' );
function pw_printway_backup_import_step_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$token = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) wp_unslash( $_POST['token'] ?? '' ) );
	try { wp_send_json_success( pw_printway_backup_import_step( $token ) ); }
	catch ( Throwable $error ) { wp_send_json_error( array( 'message' => $error->getMessage() ), 400 ); }
}

function pw_printway_backup_download_url( $file, $asset = 'zip' ) {
	$args = array( 'action' => 'pw_printway_backup_download', 'file' => basename( $file ) );
	if ( 'zip' !== $asset ) { $args['asset'] = sanitize_key( $asset ); }
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'pw_printway_backup_download' );
}

function pw_printway_backup_download_set_controls( $file, $id = '' ) {
	$parts = pw_printway_backup_volume_paths( $file );
	$count = count( $parts );
	$id    = $id ? sanitize_html_class( $id ) : 'pw-backup-download-' . wp_generate_uuid4();
	$package = pw_printway_backup_package_data( $file );
	$percent = ! empty( $package['bytes_total'] ) ? min( 100, round( 100 * (float) $package['bytes_done'] / (float) $package['bytes_total'] ) ) : ( 'complete' === $package['state'] ? 100 : 0 );
	$parts_html = '';
	if ( $count > 1 ) {
		$parts_html = '<details class="pw-backup-download-set" id="' . esc_attr( $id ) . '"><summary class="button">Baixar por partes (' . esc_html( $count ) . ' volumes)</summary><div class="pw-backup-download-set__content"><p class="description">O arquivo de índice será baixado automaticamente junto aos volumes selecionados.</p><p><label><input type="checkbox" class="pw-backup-parts-all" checked> Selecionar todos</label></p><ol class="pw-backup-download-set__list">';
		foreach ( $parts as $index => $part ) {
			$part_url = pw_printway_backup_download_url( $part );
			$parts_html .= '<li><label><input type="checkbox" class="pw-backup-part-item" checked data-url="' . esc_url( $part_url ) . '" data-name="' . esc_attr( basename( $part ) ) . '" data-size="' . esc_attr( filesize( $part ) ) . '"> Volume ' . esc_html( $index + 1 ) . ' de ' . esc_html( $count ) . '</label> <a class="pw-backup-part-direct" href="' . esc_url( $part_url ) . '">Baixar este volume</a><code>' . esc_html( basename( $part ) ) . '</code></li>';
		}
		$parts_html .= '</ol><button type="button" class="button pw-backup-parts-start" data-index-url="' . esc_url( pw_printway_backup_download_url( $file, 'meta' ) ) . '" data-index-name="' . esc_attr( basename( $file ) . '.json' ) . '">Iniciar downloads</button><div class="pw-backup-parts-progress" hidden><span class="pw-backup-parts-progress-bar"></span></div><span class="pw-backup-parts-status" aria-live="polite"></span></div></details>';
	}
	$box_id = $id . '-package';
	$state = $package['state'] ?? 'none';
	$ajax_nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	if ( 'complete' === $package['state'] ) {
		$package_path = ! empty( $package['file'] ) ? pw_printway_backup_packages_directory() . basename( sanitize_file_name( $package['file'] ) ) : '';
		$single_size = $package_path && is_file( $package_path ) ? size_format( filesize( $package_path ), 2 ) : '';
		$downloaded_class = ! empty( $package['downloaded_at'] ) ? ' is-downloaded' : '';
		$html = '<div class="pw-backup-download-actions"><a class="button pw-backup-single-download' . esc_attr( $downloaded_class ) . '" href="' . esc_url( pw_printway_backup_download_url( $file, 'package' ) ) . '" data-stream-url="' . esc_url( pw_printway_backup_download_url( $file, 'package_stream' ) ) . '" data-file-name="' . esc_attr( basename( $package_path ) ) . '" data-file-size="' . esc_attr( $package_path && is_file( $package_path ) ? filesize( $package_path ) : 0 ) . '" data-backup="' . esc_attr( basename( $file ) ) . '" data-ajax-nonce="' . esc_attr( wp_create_nonce( 'pw_printway_backup_ajax' ) ) . '">Baixar ZIP único' . ( $single_size ? ' (' . esc_html( $single_size ) . ')' : '' ) . '</a>' . $parts_html . '</div>';
	} else {
		$building = 'building' === $state;
		$label = $building ? 'Criando ZIP único (' . $percent . '%)' : ( 'error' === $state ? 'Criar ZIP único novamente' : 'Criar ZIP único' );
		$tooltip = 'Crie o ZIP único somente quando precisar baixá-lo em um arquivo.';
		$message = $building ? ( $package['message'] ?? 'Preparando ZIP único…' ) : ( 'error' === $state ? ( $package['message'] ?? 'Não foi possível criar o ZIP único.' ) : '' );
		$html = '<div id="' . esc_attr( $box_id ) . '" class="pw-backup-download-actions pw-backup-package-on-demand" data-backup="' . esc_attr( basename( $file ) ) . '" data-nonce="' . esc_attr( $ajax_nonce ) . '" data-state="' . esc_attr( $state ) . '"><button type="button" class="button pw-backup-package-create" title="' . esc_attr( $tooltip ) . '"' . ( $building ? ' disabled' : '' ) . '>' . esc_html( $label ) . '</button><small class="pw-backup-package-message">' . esc_html( $message ) . '</small>' . $parts_html . '</div>';
		$html .= '<script>(function(){var box=document.getElementById(' . wp_json_encode( $box_id ) . ');if(!box){return;}var button=box.querySelector(".pw-backup-package-create"),message=box.querySelector(".pw-backup-package-message"),timer=null;function request(action){return fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:action,nonce:box.dataset.nonce,file:box.dataset.backup})}).then(function(r){return r.json();});}function refresh(){request("pw_printway_backup_package_status").then(function(r){if(!r.success){message.textContent=(r.data&&r.data.message)||"Não foi possível consultar o ZIP único.";button.disabled=false;button.textContent="Criar ZIP único novamente";return;}var p=r.data||{},pc=Math.max(0,Math.min(100,Number(p.percent||0)));if(p.state==="complete"||p.state==="error"){if(document.querySelector(".pw-backup-stream-modal.is-open")){message.textContent=p.state==="complete"?"ZIP único concluído. A lista será atualizada quando a transferência atual terminar.":(p.message||"Não foi possível criar o ZIP único.");timer=setTimeout(refresh,2000);return;}window.location.reload();return;}button.disabled=true;button.textContent="Criando ZIP único ("+pc+"%)";message.textContent=p.message||"Preparando ZIP único…";timer=setTimeout(refresh,2500);}).catch(function(){message.textContent="Não foi possível consultar o andamento. Tentando novamente…";timer=setTimeout(refresh,5000);});}button.addEventListener("click",function(){if(button.disabled){return;}button.disabled=true;button.textContent="Criando ZIP único (0%)";message.textContent="Preparando ZIP único…";request("pw_printway_backup_package_create").then(function(r){if(!r.success){button.disabled=false;button.textContent="Criar ZIP único novamente";message.textContent=(r.data&&r.data.message)||"Não foi possível iniciar a criação.";return;}refresh();}).catch(function(){button.disabled=false;button.textContent="Criar ZIP único novamente";message.textContent="Não foi possível iniciar a criação.";});});if(box.dataset.state==="building"){refresh();}}());</script>';
	}
	return $html;
}

function pw_printway_backup_total_size( $file ) {
	return array_sum( array_map( 'filesize', pw_printway_backup_volume_paths( $file ) ) );
}

function pw_printway_backup_delete_set( $file ) {
	$removed = false;
	$meta = pw_printway_backup_file_meta( $file );
	foreach ( pw_printway_backup_volume_paths( $file ) as $part ) {
		if ( is_file( $part ) && @unlink( $part ) ) { $removed = true; }
	}
	@unlink( $file . '.json' );
	@unlink( $file . '.log.txt' );
	if ( ! empty( $meta['package']['file'] ) ) { @unlink( pw_printway_backup_packages_directory() . basename( sanitize_file_name( $meta['package']['file'] ) ) ); }
	return $removed;
}

function pw_printway_backup_failures_directory() {
	$directory = trailingslashit( pw_printway_backup_directory() ) . 'failures';
	if ( ! is_dir( $directory ) ) { wp_mkdir_p( $directory ); }
	return $directory;
}

function pw_printway_backup_failure_files() {
	$files = glob( trailingslashit( pw_printway_backup_failures_directory() ) . '*.json' ) ?: array();
	usort( $files, function( $a, $b ) { return filemtime( $b ) <=> filemtime( $a ); } );
	return $files;
}

function pw_printway_backup_record_failure( $state, $message, $status = array() ) {
	$status = is_array( $status ) ? $status : array();
	$run_id = sanitize_text_field( (string) ( $status['run_id'] ?? pw_printway_backup_run_token() ) );
	$dedupe = 'pw_printway_backup_failure_' . md5( $run_id . '|' . $state );
	if ( $run_id && get_transient( $dedupe ) ) { return; }
	if ( $run_id ) { set_transient( $dedupe, 1, DAY_IN_SECONDS ); }
	$created = time();
	$id = 'failure-' . wp_date( 'Ymd-His', $created, wp_timezone() ) . '-' . substr( wp_generate_uuid4(), 0, 8 );
	$log = ! empty( $status['log'] ) && is_array( $status['log'] ) ? $status['log'] : array();
	$log[] = '[' . wp_date( 'd/m/Y H:i:s', $created, wp_timezone() ) . '] ' . $message;
	$data = array(
		'id' => $id, 'created_at' => $created, 'state' => sanitize_key( $state ), 'message' => (string) $message,
		'run_id' => $run_id, 'kind' => sanitize_key( (string) ( $status['kind'] ?? $GLOBALS['pw_printway_backup_kind'] ?? 'manual' ) ),
		'started' => absint( $status['started'] ?? 0 ), 'duration' => ! empty( $status['started'] ) ? max( 0, $created - absint( $status['started'] ) ) : 0,
		'phase' => sanitize_key( (string) ( $status['phase'] ?? '' ) ), 'phase_started' => absint( $status['phase_started'] ?? 0 ),
		'percent' => absint( $status['percent'] ?? 0 ), 'current' => (string) ( $status['current'] ?? '' ),
		'zip_files' => absint( $status['zip_files'] ?? 0 ), 'zip_bytes' => absint( $status['zip_bytes'] ?? 0 ),
		'zip_stored_files' => absint( $status['zip_stored_files'] ?? 0 ), 'zip_compressed_files' => absint( $status['zip_compressed_files'] ?? 0 ),
		'expected_seconds' => absint( $status['expected_seconds'] ?? 0 ), 'last_update' => absint( $status['updated'] ?? 0 ),
		'php_version' => PHP_VERSION, 'wordpress_version' => get_bloginfo( 'version' ), 'site_url' => home_url( '/' ),
		'server' => sanitize_text_field( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? 'não informado' ) ),
		'memory_limit' => (string) ini_get( 'memory_limit' ), 'peak_memory' => memory_get_peak_usage( true ),
		'sent_at' => 0, 'sent_to' => '', 'sent_count' => 0,
	);
	$base = trailingslashit( pw_printway_backup_failures_directory() ) . $id;
	file_put_contents( $base . '.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	file_put_contents( $base . '.log.txt', implode( PHP_EOL, $log ) . PHP_EOL );
	$failure_files = pw_printway_backup_failure_files();
	foreach ( array_slice( $failure_files, 100 ) as $old ) { @unlink( $old ); @unlink( preg_replace( '/\.json$/', '.log.txt', $old ) ); }
}

function pw_printway_backup_stored_list( $files, $base ) {
	if ( empty( $files ) ) {
		echo '<p>Nenhum backup armazenado ainda.</p>';
		return;
	}
	echo '<div id="pw-backup-stored-list"><table class="widefat striped"><thead><tr><th>Arquivo</th><th>Tipo</th><th>Data e hora</th><th>Duração</th><th>Tamanho</th><th>Ações</th></tr></thead><tbody>';
	foreach ( $files as $file ) {
		$meta = pw_printway_backup_file_meta( $file );
		echo '<tr><td>' . esc_html( basename( $file ) ) . '</td><td>' . esc_html( pw_printway_backup_kind_label( $meta['kind'] ) ) . '</td><td>' . esc_html( wp_date( 'd/m/Y H:i', filemtime( $file ), pw_printway_backup_timezone() ) ) . '</td><td>' . esc_html( pw_printway_backup_duration_label( $meta['duration'] ) ) . '</td><td>' . esc_html( size_format( filesize( $file ), 2 ) ) . '</td><td><a class="button" href="' . esc_url( add_query_arg( array( 'tab' => 'restore', 'file' => basename( $file ) ), $base ) ) . '">Ver opções</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

function pw_printway_backup_restore_list( $files ) {
	echo '<h2>Backups disponíveis</h2><p>Selecione os backups que deseja apagar. Esta ação não pode ser desfeita.</p>';
	if ( empty( $files ) ) {
		echo '<p>Nenhum backup armazenado ainda.</p>';
		return;
	}

	$modals = '';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="pw-backup-delete-form"><input type="hidden" name="action" value="pw_printway_backup_delete_selected">';
	wp_nonce_field( 'pw_printway_backup_delete_selected' );
	echo '<p><button type="submit" class="button button-secondary" id="pw-backup-delete-selected" disabled>Apagar selecionados</button></p><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="pw-backup-delete-all" aria-label="Selecionar todos os backups"></td><th>Arquivo</th><th>Tipo</th><th>Data e hora</th><th>Duração</th><th>Tamanho</th><th>Ação</th></tr></thead><tbody>';
	foreach ( $files as $file ) {
		$name = basename( $file );
		$download = wp_nonce_url( add_query_arg( array( 'action' => 'pw_printway_backup_download', 'file' => $name ), admin_url( 'admin-post.php' ) ), 'pw_printway_backup_download' );
		$meta = pw_printway_backup_file_meta( $file );
		echo '<tr><th scope="row" class="check-column"><input type="checkbox" class="pw-backup-delete-item" name="backups[]" value="' . esc_attr( $name ) . '"></th><td>' . esc_html( $name ) . '</td><td>' . esc_html( pw_printway_backup_kind_label( $meta['kind'] ) ) . '</td><td>' . esc_html( wp_date( 'd/m/Y H:i', filemtime( $file ), wp_timezone() ) ) . '</td><td>' . esc_html( pw_printway_backup_duration_label( $meta['duration'] ) ) . '</td><td>' . esc_html( size_format( filesize( $file ), 2 ) ) . '</td><td><a class="button" href="' . esc_url( $download ) . '">Baixar</a></td></tr>';
	}
	echo '</tbody></table></form><script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-delete-form");if(!form){return;}var all=document.getElementById("pw-backup-delete-all"),items=Array.prototype.slice.call(form.querySelectorAll(".pw-backup-delete-item")),button=document.getElementById("pw-backup-delete-selected");function update(){var count=items.filter(function(item){return item.checked;}).length;button.disabled=!count;all.checked=count===items.length;all.indeterminate=count>0&&count<items.length;}all.addEventListener("change",function(){items.forEach(function(item){item.checked=all.checked;});update();});items.forEach(function(item){item.addEventListener("change",update);});form.addEventListener("submit",function(event){if(!items.some(function(item){return item.checked;})){event.preventDefault();return;}if(!window.confirm("Apagar os backups selecionados? Esta ação não pode ser desfeita.")){event.preventDefault();}});update();});</script>';
}

function pw_printway_backup_backup_scope( $meta ) {
	$all = pw_printway_backup_components();
	$details = ! empty( $meta['component_details'] ) && is_array( $meta['component_details'] ) ? $meta['component_details'] : array();
	$included = ! empty( $details ) ? array_keys( $details ) : (array) ( $meta['components'] ?? array() );
	$partial = count( $included ) !== count( $all );
	$rows = array();
	foreach ( $all as $key => $label ) {
		if ( ! in_array( $key, $included, true ) ) {
			$rows[] = array( 'label' => $label, 'text' => 'Não incluído' );
			continue;
		}
		$selected = $details[ $key ]['selected'] ?? null;
		if ( null === $selected ) {
			$rows[] = array( 'label' => $label, 'text' => 'Todos os itens da categoria' );
			continue;
		}
		$available = pw_printway_backup_component_items( $key );
		$selected = array_values( array_filter( (array) $selected ) );
		if ( ! empty( $available ) && count( $selected ) >= count( $available ) ) {
			$rows[] = array( 'label' => $label, 'text' => 'Todos os itens da categoria' );
			continue;
		}
		$partial = true;
		$names = array();
		foreach ( $selected as $item ) { $names[] = $available[ $item ] ?? $item; }
		$rows[] = array( 'label' => $label, 'text' => $names ? implode( ', ', $names ) : 'Sem itens selecionados' );
	}
	return array( 'label' => $partial ? 'Parcial' : 'Completo', 'rows' => $rows );
}

function pw_printway_backup_restore_list_v2( $files, $base ) {
	$ftp_directory = untrailingslashit( pw_printway_backup_directory() );
	$ftp_package_directory = untrailingslashit( pw_printway_backup_packages_directory() );
	$ftp_html = '<div class="pw-backup-ftp-path"><strong>Download via FTP</strong><br>Volumes, arquivo de índice e logs:<code>' . esc_html( $ftp_directory ) . '</code>ZIP único, quando concluído:<code>' . esc_html( $ftp_package_directory ) . '</code></div>';
	echo '<style>.pw-backup-package-create{box-sizing:border-box;width:250px!important;min-height:38px;display:flex!important;align-items:center;justify-content:center;text-align:center}.pw-backup-package-on-demand .pw-backup-package-message{display:block;font-size:12px;line-height:1.35;margin:6px 0 8px;color:#50575e}.pw-backup-package-on-demand .pw-backup-package-message:empty{display:none}@media(max-width:782px){.pw-backup-package-create{width:100%!important}}</style>';
	echo '<style>.pw-backup-details-modal{display:none;position:fixed;inset:0;z-index:100200;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:20px}.pw-backup-details-modal.is-open{display:flex}.pw-backup-details-panel{width:min(760px,96vw);max-height:82vh;overflow:auto;background:#fff;border-radius:10px;box-shadow:0 18px 65px rgba(0,0,0,.35);padding:22px}.pw-backup-details-panel__head{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:14px}.pw-backup-details-close{font-size:24px;border:0;background:none;cursor:pointer}.pw-backup-detail-tabs{display:flex;gap:7px;border-bottom:1px solid #dcdcde;margin-bottom:16px}.pw-backup-detail-tab{border:0;border-bottom:3px solid transparent;background:none;padding:9px 12px;cursor:pointer;font-weight:600}.pw-backup-detail-tab.is-active{border-bottom-color:#2271b1;color:#135e96}.pw-backup-detail-panel{display:none}.pw-backup-detail-panel.is-active{display:block}.pw-backup-log-area{width:100%;min-height:280px;box-sizing:border-box;font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace}.pw-backup-action-stack{display:flex;flex-direction:column;align-items:stretch;gap:8px;width:250px}.pw-backup-download-actions{display:block}.pw-backup-single-download,.pw-backup-download-set>summary{box-sizing:border-box;width:250px!important;min-height:38px;display:flex!important;align-items:center;justify-content:center;text-align:center}.pw-backup-single-download.is-downloaded{background:#008a20!important;border-color:#008a20!important;color:#fff!important}.pw-backup-download-set{display:block;position:relative}.pw-backup-download-set summary{list-style:none;cursor:pointer}.pw-backup-download-set summary::-webkit-details-marker{display:none}.pw-backup-download-set[open]{z-index:2}.pw-backup-download-set__content{margin-top:8px;padding:10px;width:420px;border:1px solid #c3c4c7;border-radius:7px;background:#fff;box-shadow:0 8px 26px rgba(0,0,0,.16)}.pw-backup-download-set__list{max-height:230px;overflow:auto;margin:8px 0;padding-left:22px}.pw-backup-download-set__list li{margin:9px 0}.pw-backup-download-set code{display:block;font-size:11px;word-break:break-all;margin-top:3px}.pw-backup-part-direct{display:inline-block;margin-left:8px;font-size:12px}.pw-backup-parts-progress{height:7px;background:#dcdcde;border-radius:4px;overflow:hidden;margin-top:10px}.pw-backup-parts-progress-bar{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}.pw-backup-parts-status{display:block;margin-top:7px;font-size:12px}.pw-backup-package{padding:10px;border:1px solid #c3c4c7;border-radius:6px;min-width:250px}.pw-backup-package-message{margin:6px 0}.pw-backup-package-track{height:7px;background:#dcdcde;border-radius:4px;overflow:hidden}.pw-backup-package-bar{display:block;height:100%;background:#2271b1}.pw-backup-ftp-path{margin:28px 0 0;padding:12px 14px;border-left:4px solid #2271b1;background:#f0f6fc}.pw-backup-ftp-path code{display:block;margin-top:5px;word-break:break-all}@media(max-width:782px){.pw-backup-action-stack,.pw-backup-single-download,.pw-backup-download-set>summary{width:100%!important}.pw-backup-download-set__content{width:auto}}</style><h2>Backups disponíveis para restauração</h2><p>Confira o conteúdo de cada backup antes de restaurar ou apagar.</p>';
	if ( empty( $files ) ) { echo '<p>Nenhum backup armazenado ainda.</p>' . $ftp_html; return; }
	$modals = '';
	$backup_sizes = array();
	$total_storage = 0;
	foreach ( $files as $backup_file ) {
		$backup_meta = pw_printway_backup_file_meta( $backup_file );
		$backup_size = pw_printway_backup_total_size( $backup_file );
		foreach ( array( $backup_file . '.json', $backup_file . '.log.txt' ) as $extra_file ) {
			if ( is_file( $extra_file ) ) { $backup_size += (int) filesize( $extra_file ); }
		}
		if ( ! empty( $backup_meta['package']['file'] ) ) {
			$package_file = pw_printway_backup_packages_directory() . basename( sanitize_file_name( $backup_meta['package']['file'] ) );
			if ( is_file( $package_file ) ) { $backup_size += (int) filesize( $package_file ); }
		}
		$backup_sizes[ basename( $backup_file ) ] = $backup_size;
		$total_storage += $backup_size;
	}
	echo '<p id="pw-backup-storage-total"><strong>Espaço total ocupado pelos backups:</strong> ' . esc_html( size_format( $total_storage, 2 ) ) . '.</p><p id="pw-backup-storage-selected" class="description"></p>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-delete-form"),output=document.getElementById("pw-backup-storage-selected"),sizes=' . wp_json_encode( $backup_sizes ) . ';if(!form||!output){return;}function label(bytes){if(bytes<1024){return bytes+" B";}var units=["KB","MB","GB","TB"],index=-1;do{bytes/=1024;index++;}while(bytes>=1024&&index<units.length-1);return bytes.toLocaleString("pt-BR",{maximumFractionDigits:2})+" "+units[index];}function update(){var selected=Array.prototype.slice.call(form.querySelectorAll(".pw-backup-delete-item:checked")),total=selected.reduce(function(sum,item){return sum+Number(sizes[item.value]||0);},0);output.textContent=selected.length?"Ao apagar os "+selected.length+" backup(s) selecionado(s), serão liberados aproximadamente "+label(total)+" no servidor.":"";}form.addEventListener("change",update);form.addEventListener("submit",update);update();});</script>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="pw-backup-delete-form"><input type="hidden" name="action" value="pw_printway_backup_delete_selected">';
	wp_nonce_field( 'pw_printway_backup_delete_selected' );
	echo '<p><button type="submit" class="button button-secondary" id="pw-backup-delete-selected" disabled>Apagar selecionados</button></p><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="pw-backup-delete-all" aria-label="Selecionar todos os backups"></td><th>Arquivo</th><th>Origem</th><th>Assinatura</th><th>Método</th><th>Conteúdo</th><th>Data e hora</th><th>Duração</th><th>Tamanho</th><th>Ações</th></tr></thead><tbody>';
	foreach ( $files as $index => $file ) {
		$name = basename( $file );
		$meta = pw_printway_backup_file_meta( $file );
		$manifest = array();
		$is_imported = ! empty( $meta['imported_at'] ) || in_array( (string) ( $meta['origin'] ?? '' ), array( 'this_site', 'other_site' ), true );
		$origin_label = 'Este site';
		$origin_note = '';
		$signature_label = 'Não confirmada';
		$method_label = $is_imported ? 'Importação por ZIP' : pw_printway_backup_kind_label( $meta['kind'] );
		try {
			$manifest = pw_printway_backup_manifest( $file, true );
			if ( ! empty( $manifest['_pw_signature_valid'] ) ) {
				$signature_label = 'Válida para este site';
				$origin_label = 'Este site';
			} else {
				$signature_label = 'Não confere com este site';
				$origin_label = 'Outro site';
				$origin_note = sanitize_text_field( (string) ( $manifest['site_url'] ?? $meta['origin_site_url'] ?? '' ) );
			}
		} catch ( Throwable $error ) {
			$origin_label = 'other_site' === (string) ( $meta['origin'] ?? '' ) ? 'Outro site' : ( 'this_site' === (string) ( $meta['origin'] ?? '' ) ? 'Este site' : 'Não confirmada' );
			$signature_label = 'Não foi possível verificar';
		}
		if ( empty( $meta['component_details'] ) ) {
			try { $manifest = $manifest ?: pw_printway_backup_manifest( $file, true ); $meta['component_details'] = $manifest['components'] ?? array(); $meta['components'] = array_keys( $meta['component_details'] ); $meta['file_count'] = $manifest['file_count'] ?? 0; } catch ( Throwable $error ) { }
		}
		if ( empty( $meta['wordpress_version'] ) ) {
			try { $manifest = $manifest ?: pw_printway_backup_manifest( $file, true ); $meta['wordpress_version'] = $manifest['wordpress_version'] ?? ''; } catch ( Throwable $error ) { }
		}
		$scope = pw_printway_backup_backup_scope( $meta );
		$backup_wp = sanitize_text_field( (string) ( $meta['wordpress_version'] ?? '' ) );
		$current_wp = get_bloginfo( 'version' );
		$compatible = $backup_wp ? ( version_compare( $backup_wp, $current_wp, '<=' ) ? 'Compatível para restauração nesta versão' : 'Atenção: o backup é de uma versão mais nova do WordPress' ) : 'Não informado em backups antigos';
		$package = pw_printway_backup_package_data( $file );
		$package_path = ! empty( $package['file'] ) ? pw_printway_backup_packages_directory() . basename( sanitize_file_name( $package['file'] ) ) : '';
		$size_label = 'complete' === $package['state'] && $package_path && is_file( $package_path ) ? size_format( filesize( $package_path ), 2 ) : size_format( pw_printway_backup_total_size( $file ), 2 );
		$modal = 'pw-backup-details-' . absint( $index );
		$log_path = $file . '.log.txt';
		$backup_log = is_file( $log_path ) ? (string) @file_get_contents( $log_path ) : 'Log não disponível para este backup antigo.';
		echo '<tr><th scope="row" class="check-column"><input type="checkbox" class="pw-backup-delete-item" name="backups[]" value="' . esc_attr( $name ) . '"></th><td>' . esc_html( $name ) . '</td><td><strong>' . esc_html( $origin_label ) . '</strong>' . ( $origin_note ? '<br><span class="description">' . esc_html( $origin_note ) . '</span>' : '' ) . '</td><td>' . esc_html( $signature_label ) . '</td><td>' . esc_html( $method_label ) . '</td><td><strong>' . esc_html( $scope['label'] ) . '</strong><br><button type="button" class="button-link pw-backup-details-open" data-modal="' . esc_attr( $modal ) . '">Detalhes</button><br><button type="button" class="button-link pw-backup-compatibility-open" data-backup="' . esc_attr( $name ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pw_printway_backup_ajax' ) ) . '">Compatibilidade</button></td><td>' . esc_html( wp_date( 'd/m/Y H:i', filemtime( $file ), pw_printway_backup_timezone() ) ) . '</td><td>' . esc_html( pw_printway_backup_duration_label( $meta['duration'] ) ) . '</td><td>' . esc_html( $size_label ) . '</td><td><div class="pw-backup-action-stack">' . pw_printway_backup_download_set_controls( $file, 'pw-backup-download-' . $index ) . '</div></td></tr>';
		$modals .= '<div class="pw-backup-details-modal" id="' . esc_attr( $modal ) . '" role="dialog" aria-modal="true"><div class="pw-backup-details-panel"><div class="pw-backup-details-panel__head"><h2>Detalhes do backup</h2><button type="button" class="pw-backup-details-close" aria-label="Fechar">&times;</button></div><div class="pw-backup-detail-tabs"><button type="button" class="pw-backup-detail-tab is-active" data-panel="details">Detalhes</button><button type="button" class="pw-backup-detail-tab" data-panel="logs">Logs</button></div><div class="pw-backup-detail-panel is-active" data-panel="details"><p><strong>Arquivo:</strong> ' . esc_html( $name ) . '<br><strong>Origem:</strong> ' . esc_html( $origin_label ) . ( $origin_note ? ' — ' . esc_html( $origin_note ) : '' ) . '<br><strong>Assinatura:</strong> ' . esc_html( $signature_label ) . '<br><strong>Método:</strong> ' . esc_html( $method_label ) . '<br><strong>Escopo:</strong> ' . esc_html( $scope['label'] ) . '<br><strong>Arquivos registrados:</strong> ' . esc_html( number_format_i18n( absint( $meta['file_count'] ?? 0 ) ) ) . '<br><strong>WordPress do backup:</strong> ' . esc_html( $backup_wp ?: 'Não informado' ) . '<br><strong>WordPress atual:</strong> ' . esc_html( $current_wp ) . '<br><strong>Compatibilidade:</strong> ' . esc_html( $compatible ) . '</p><table class="widefat striped"><thead><tr><th>Categoria</th><th>Incluído no backup</th></tr></thead><tbody>';
		foreach ( $scope['rows'] as $row ) { $modals .= '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $row['text'] ) . '</td></tr>'; }
		$modals .= '</tbody></table></div><div class="pw-backup-detail-panel" data-panel="logs"><textarea class="pw-backup-log-area" readonly spellcheck="false">' . esc_textarea( $backup_log ) . '</textarea></div></div></div>';
	}
	echo '</tbody></table></form>' . $modals . '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-delete-form");if(form){var all=document.getElementById("pw-backup-delete-all"),items=Array.prototype.slice.call(form.querySelectorAll(".pw-backup-delete-item")),button=document.getElementById("pw-backup-delete-selected");function update(){var count=items.filter(function(item){return item.checked;}).length;button.disabled=!count;all.checked=count===items.length;all.indeterminate=count>0&&count<items.length;}all.addEventListener("change",function(){items.forEach(function(item){item.checked=all.checked;});update();});items.forEach(function(item){item.addEventListener("change",update);});form.addEventListener("submit",function(event){if(!items.some(function(item){return item.checked;})||!window.confirm("Apagar os backups selecionados? Esta ação não pode ser desfeita.")){event.preventDefault();}});update();}document.querySelectorAll(".pw-backup-details-open").forEach(function(button){button.addEventListener("click",function(){document.getElementById(button.dataset.modal).classList.add("is-open");});});document.querySelectorAll(".pw-backup-details-modal").forEach(function(modal){modal.addEventListener("click",function(event){if(event.target===modal||event.target.closest(".pw-backup-details-close")){modal.classList.remove("is-open");}});});});</script>';
	echo '<style>.pw-backup-stream-modal{display:none;position:fixed;inset:0;z-index:100300;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:20px}.pw-backup-stream-modal.is-open{display:flex}.pw-backup-stream-panel{width:min(520px,96vw);background:#fff;padding:24px;border-radius:10px;box-shadow:0 18px 65px rgba(0,0,0,.35)}.pw-backup-stream-track{height:10px;background:#dcdcde;border-radius:5px;overflow:hidden}.pw-backup-stream-bar{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}.pw-backup-stream-actions{margin-top:16px}</style>';
	echo '<style>.pw-backup-stream-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0}.pw-backup-stream-stat{padding:9px;background:#f6f7f7;border-radius:5px}.pw-backup-stream-stat small{display:block;color:#646970;margin-bottom:2px}</style>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){function bytes(n){if(n<1024){return n+" B";}if(n<1048576){return (n/1024).toFixed(1)+" KB";}if(n<1073741824){return (n/1048576).toFixed(1)+" MB";}return (n/1073741824).toFixed(2)+" GB";}function duration(s){s=Math.max(0,Math.round(s));var h=Math.floor(s/3600),m=Math.floor((s%3600)/60),q=s%60;return (h?h+"h ":"")+(m?m+"min ":"")+q+"s";}async function trackedDownload(entries,target,modal,markDone){var message=modal.querySelector(".pw-backup-stream-message"),bar=modal.querySelector(".pw-backup-stream-bar"),percent=modal.querySelector(".pw-backup-stream-percent"),speed=modal.querySelector(".pw-backup-stream-speed"),eta=modal.querySelector(".pw-backup-stream-eta"),retriesNode=modal.querySelector(".pw-backup-stream-retries"),cancel=modal.querySelector(".pw-backup-stream-cancel"),cancelled=false,writer,total=entries.reduce(function(sum,item){return sum+Number(item.size||0);},0),done=0,retries=0,started=performance.now();cancel.addEventListener("click",function(){cancelled=true;if(writer&&writer.abort){writer.abort();}modal.remove();});function update(label){var elapsed=Math.max(.001,(performance.now()-started)/1000),rate=done/elapsed,remaining=rate?(total-done)/rate:0,value=total?Math.round(100*done/total):0;bar.style.width=value+"%";percent.textContent=value+"%";message.textContent=label;speed.textContent=bytes(rate)+"/s";eta.textContent=rate?duration(remaining):"Calculando…";retriesNode.textContent=retries?retries+" retomada(s)":"Nenhuma";}for(var fileIndex=0;fileIndex<entries.length;fileIndex++){var item=entries[fileIndex];if(cancelled){return;}message.textContent="Preparando "+item.name+" ("+(fileIndex+1)+" de "+entries.length+")…";writer=await target(item);var fileDone=0,known=Number(item.size||0);if(!known){var small=await fetch(item.url,{credentials:"same-origin",cache:"no-store"});if(!small.ok){throw new Error("Não foi possível obter "+item.name);}var raw=await small.arrayBuffer();await writer.write(raw);done+=raw.byteLength;await writer.close();writer=null;update(item.name+" concluído.");continue;}while(fileDone<known){if(cancelled){return;}var start=fileDone,end=Math.min(known-1,start+8*1024*1024-1),buffer;for(;;){try{var response=await fetch(item.url,{method:"GET",headers:{Range:"bytes="+start+"-"+end},credentials:"same-origin",cache:"no-store"});if(!response.ok){throw new Error("HTTP "+response.status);}buffer=await response.arrayBuffer();if(buffer.byteLength!==end-start+1){throw new Error("Bloco incompleto");}break;}catch(error){if(cancelled){return;}retries++;update("Conexão interrompida. Retomando "+item.name+"…");await new Promise(function(resolve){setTimeout(resolve,Math.min(10000,1500+retries*400));});}}if(cancelled){return;}await writer.write({type:"write",position:start,data:buffer});fileDone=end+1;done+=buffer.byteLength;update(item.name+" — "+bytes(fileDone)+" de "+bytes(known)+".");}await writer.close();writer=null;}bar.style.width="100%";percent.textContent="100%";message.textContent="Download concluído com sucesso.";eta.textContent="Concluído";if(markDone){markDone();}cancel.textContent="Fechar";cancel.onclick=function(){modal.remove();};}function modal(title){var box=document.createElement("div");box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>"+title+"</h2><p class=\"pw-backup-stream-message\">Preparando…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante estimado</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Retomadas</small><strong class=\"pw-backup-stream-retries\">Nenhuma</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(box);return box;}document.querySelectorAll(".pw-backup-single-download").forEach(function(button){button.addEventListener("click",function(event){if(!window.showSaveFilePicker||!button.dataset.streamUrl||!Number(button.dataset.fileSize||0)){return;}event.preventDefault();event.stopImmediatePropagation();(async function(){var handle;try{handle=await window.showSaveFilePicker({suggestedName:button.dataset.fileName||"backup.zip",types:[{description:"Arquivo ZIP",accept:{"application/zip":[".zip"]}}]});}catch(error){return;}var box=modal("Baixando ZIP único");try{await trackedDownload([{url:button.dataset.streamUrl,name:button.dataset.fileName,size:Number(button.dataset.fileSize)}],function(){return handle.createWritable();},box,function(){button.classList.add("is-downloaded");fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_package_downloaded",nonce:button.dataset.ajaxNonce,file:button.dataset.backup})});});}catch(error){box.querySelector(".pw-backup-stream-message").textContent="Erro: "+(error.message||"não foi possível concluir o download")+".";}})();},true);});document.querySelectorAll(".pw-backup-parts-start").forEach(function(button){button.addEventListener("click",function(event){if(!window.showDirectoryPicker){return;}event.preventDefault();event.stopImmediatePropagation();(async function(){var directory;try{directory=await window.showDirectoryPicker({mode:"readwrite"});}catch(error){return;}var set=button.closest(".pw-backup-download-set"),chosen=Array.prototype.slice.call(set.querySelectorAll(".pw-backup-part-item:checked"));if(!chosen.length){return;}var files=[{url:button.dataset.indexUrl,name:button.dataset.indexName,size:0}].concat(chosen.map(function(item){return {url:item.dataset.url,name:item.dataset.name,size:Number(item.dataset.size||0)};}));var box=modal("Baixando volumes do backup");try{await trackedDownload(files,function(item){return directory.getFileHandle(item.name,{create:true}).then(function(handle){return handle.createWritable();});},box);}catch(error){box.querySelector(".pw-backup-stream-message").textContent="Erro: "+(error.message||"não foi possível concluir os downloads")+".";}})();},true);});});</script>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".pw-backup-single-download").forEach(function(button){button.addEventListener("click",function(){button.classList.add("is-downloaded");});});document.querySelectorAll(".pw-backup-download-set").forEach(function(set){var all=set.querySelector(".pw-backup-parts-all"),items=Array.prototype.slice.call(set.querySelectorAll(".pw-backup-part-item")),start=set.querySelector(".pw-backup-parts-start"),status=set.querySelector(".pw-backup-parts-status"),progress=set.querySelector(".pw-backup-parts-progress"),bar=set.querySelector(".pw-backup-parts-progress-bar");if(!all||!start){return;}function sync(){var chosen=items.filter(function(item){return item.checked;}).length;all.checked=chosen===items.length;all.indeterminate=chosen>0&&chosen<items.length;start.disabled=!chosen;}all.addEventListener("change",function(){items.forEach(function(item){item.checked=all.checked;});sync();});items.forEach(function(item){item.addEventListener("change",sync);});start.addEventListener("click",function(){var queue=items.filter(function(item){return item.checked;}).map(function(item){return item.dataset.url;});if(!queue.length){return;}start.disabled=true;all.disabled=true;items.forEach(function(item){item.disabled=true;});progress.hidden=false;bar.style.width="0%";status.textContent="Enviando o arquivo de índice ao navegador…";var urls=[start.dataset.indexUrl].concat(queue),position=0;function next(){if(position>=urls.length){bar.style.width="100%";status.textContent="Todos os downloads foram enviados ao navegador. Caso ele bloqueie múltiplos downloads, use os links individuais acima.";return;}var isIndex=position===0,volume=position;var link=document.createElement("a");link.href=urls[position];link.style.display="none";document.body.appendChild(link);link.click();link.remove();position++;if(!isIndex){bar.style.width=Math.round(100*volume/queue.length)+"%";status.textContent="Volume "+volume+" de "+queue.length+" enviado ao navegador. Aguardando antes do próximo…";}setTimeout(next,2500);}next();});sync();});});</script>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".pw-backup-details-modal").forEach(function(modal){modal.querySelectorAll(".pw-backup-detail-tab").forEach(function(button){button.addEventListener("click",function(){var panel=button.dataset.panel;modal.querySelectorAll(".pw-backup-detail-tab").forEach(function(tab){tab.classList.toggle("is-active",tab===button);});modal.querySelectorAll(".pw-backup-detail-panel").forEach(function(item){item.classList.toggle("is-active",item.dataset.panel===panel);});});});});});</script>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".pw-backup-single-download").forEach(function(button){button.addEventListener("click",function(){button.classList.add("is-downloaded");});});document.querySelectorAll(".pw-backup-download-set").forEach(function(set){var all=set.querySelector(".pw-backup-parts-all"),items=Array.prototype.slice.call(set.querySelectorAll(".pw-backup-part-item")),start=set.querySelector(".pw-backup-parts-start"),status=set.querySelector(".pw-backup-parts-status"),progress=set.querySelector(".pw-backup-parts-progress"),bar=set.querySelector(".pw-backup-parts-progress-bar");if(!all||!start){return;}function sync(){var chosen=items.filter(function(item){return item.checked;}).length;all.checked=chosen===items.length;all.indeterminate=chosen>0&&chosen<items.length;start.disabled=!chosen;}all.addEventListener("change",function(){items.forEach(function(item){item.checked=all.checked;});sync();});items.forEach(function(item){item.addEventListener("change",sync);});start.addEventListener("click",function(){var queue=items.filter(function(item){return item.checked;}).map(function(item){return item.dataset.url;});if(!queue.length){return;}start.disabled=true;all.disabled=true;items.forEach(function(item){item.disabled=true;});progress.hidden=false;bar.style.width="0%";status.textContent="Enviando o arquivo de índice ao navegador…";var urls=[start.dataset.indexUrl].concat(queue),position=0;function next(){if(position>=urls.length){bar.style.width="100%";status.textContent="Todos os downloads foram enviados ao navegador. Caso ele bloqueie múltiplos downloads, use os links individuais acima.";return;}var isIndex=position===0,volume=position;var link=document.createElement("a");link.href=urls[position];link.style.display="none";document.body.appendChild(link);link.click();link.remove();position++;if(!isIndex){bar.style.width=Math.round(100*volume/queue.length)+"%";status.textContent="Volume "+volume+" de "+queue.length+" enviado ao navegador. Aguardando antes do próximo…";}setTimeout(next,2500);}next();});sync();});});</script>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".pw-backup-compatibility-open").forEach(function(button){button.addEventListener("click",function(){var modal=document.createElement("div");modal.className="pw-backup-details-modal is-open";modal.innerHTML="<div class=\\"pw-backup-details-panel\\"><div class=\\"pw-backup-details-panel__head\\"><h2>Verificando compatibilidade</h2><button type=\\"button\\" class=\\"pw-backup-details-close\\">×</button></div><p class=\\"pw-compare-message\\">Preparando comparação segura…</p><div style=\\"height:10px;background:#dcdcde;border-radius:5px;overflow:hidden\\"><span class=\\"pw-compare-bar\\" style=\\"display:block;height:100%;width:0;background:#2271b1\\"></span></div><p class=\\"pw-compare-number\\">0%</p><div class=\\"pw-compare-results\\"></div></div>";document.body.appendChild(modal);modal.addEventListener("click",function(e){if(e.target===modal||e.target.closest(".pw-backup-details-close")){modal.remove();}});var message=modal.querySelector(".pw-compare-message"),bar=modal.querySelector(".pw-compare-bar"),number=modal.querySelector(".pw-compare-number"),results=modal.querySelector(".pw-compare-results"),nonce=button.dataset.nonce;function request(action,extra){var data=Object.assign({action:action,nonce:nonce},extra||{});return fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams(data)}).then(function(r){return r.json();});}function render(data){var pct=Number(data.percent||0);bar.style.width=pct+"%";number.textContent=pct+"%";message.textContent=data.current||"Comparando…";if(data.phase!=="complete"){setTimeout(function(){request("pw_printway_backup_compatibility_step",{token:data.token}).then(function(r){if(r.success){render(r.data||{});}else{message.textContent=(r.data&&r.data.message)||"Não foi possível continuar a comparação.";}}).catch(function(){message.textContent="A conexão foi interrompida. Tente novamente.";});},120);return;}var list=data.differences||[],title=document.createElement("p");title.innerHTML="<strong>Comparação concluída:</strong> "+Number(data.difference_total||0).toLocaleString("pt-BR")+" diferença(s) encontrada(s).";results.appendChild(title);if(!list.length){results.insertAdjacentHTML("beforeend","<p class=\\"notice notice-success inline\\">Os arquivos comparados possuem mesma presença, tamanho e data/hora.</p>");return;}var table=document.createElement("table");table.className="widefat striped";table.innerHTML="<thead><tr><th>Situação</th><th>Arquivo</th><th>Backup</th><th>Site atual</th></tr></thead>";var body=document.createElement("tbody");list.forEach(function(row){var tr=document.createElement("tr");[row.type,row.path,row.backup,row.current].forEach(function(value){var td=document.createElement("td");td.textContent=value||"—";tr.appendChild(td);});body.appendChild(tr);});table.appendChild(body);results.appendChild(table);if(Number(data.difference_total||0)>list.length){var note=document.createElement("p");note.textContent="A tela mostra as primeiras "+list.length+" diferenças; o total completo está indicado acima.";results.appendChild(note);}}request("pw_printway_backup_compatibility_start",{file:button.dataset.backup}).then(function(r){if(r.success){render(r.data||{});}else{message.textContent=(r.data&&r.data.message)||"Não foi possível iniciar a comparação.";}}).catch(function(){message.textContent="Não foi possível iniciar a comparação.";});});});});</script>';
	echo $ftp_html;
}

add_action( 'admin_footer', 'pw_printway_backup_download_eta_clock' );
function pw_printway_backup_download_eta_clock() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){function seconds(text){var h=(text.match(/(\\d+)h/)||[,0])[1],m=(text.match(/(\\d+)min/)||[,0])[1],s=(text.match(/(\\d+)s/)||[,0])[1];return Number(h)*3600+Number(m)*60+Number(s);}setInterval(function(){document.querySelectorAll(".pw-backup-stream-eta").forEach(function(node){var raw=node.textContent;if(!raw||/Calculando|Concluído|término às/.test(raw)){return;}var rest=seconds(raw);if(!rest){return;}var end=new Date(Date.now()+rest*1000),time=end.toLocaleTimeString("pt-BR",{hour:"2-digit",minute:"2-digit"});node.textContent=raw+" · término às "+time;});},500);});</script>';
}

add_action( 'admin_footer', 'pw_printway_backup_download_pause_controls', 99 );
function pw_printway_backup_download_pause_controls() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<style>.pw-backup-stream-stat{min-height:48px;box-sizing:border-box}.pw-backup-stream-finish{border-left:3px solid #2271b1}.pw-backup-stream-pause{margin-right:8px}</style><script>document.addEventListener("DOMContentLoaded",function(){var control=window.pwBackupDownloadControl={paused:false,waiters:[]},nativeFetch=window.fetch.bind(window);window.fetch=function(){var args=arguments,url=typeof args[0]==="string"?args[0]:(args[0]&&args[0].url||"");if(url.indexOf("action=pw_printway_backup_download")===-1||!control.paused){return nativeFetch.apply(window,args);}return new Promise(function(resolve){control.waiters.push(resolve);}).then(function(){return nativeFetch.apply(window,args);});};function resume(){control.paused=false;while(control.waiters.length){control.waiters.shift()();}}function remaining(text){var h=(text.match(/(\\d+)h/)||[,0])[1],m=(text.match(/(\\d+)min/)||[,0])[1],s=(text.match(/(\\d+)s/)||[,0])[1];return Number(h)*3600+Number(m)*60+Number(s);}function enhance(modal){if(!modal||modal.dataset.pauseReady){return;}modal.dataset.pauseReady="1";var stats=modal.querySelector(".pw-backup-stream-stats"),eta=modal.querySelector(".pw-backup-stream-eta"),actions=modal.querySelector(".pw-backup-stream-actions"),message=modal.querySelector(".pw-backup-stream-message");if(!stats||!eta||!actions){return;}var finish=document.createElement("div");finish.className="pw-backup-stream-stat pw-backup-stream-finish";finish.innerHTML="<small>Término previsto</small><strong class=\"pw-backup-stream-finish-time\">Calculando…</strong>";stats.appendChild(finish);var finishText=finish.querySelector(".pw-backup-stream-finish-time"),pause=document.createElement("button");pause.type="button";pause.className="button pw-backup-stream-pause";pause.textContent="Pausar";actions.insertBefore(pause,actions.firstChild);function refreshFinish(){var raw=eta.textContent;if(/Concluído/.test(raw)){finishText.textContent="Concluído";pause.disabled=true;return;}var seconds=remaining(raw);if(!seconds){return;}var end=new Date(Date.now()+seconds*1000);finishText.textContent="Término às "+end.toLocaleTimeString("pt-BR",{hour:"2-digit",minute:"2-digit"});if(raw.indexOf("término às")===-1){eta.textContent=raw+" · término às "+end.toLocaleTimeString("pt-BR",{hour:"2-digit",minute:"2-digit"});}}new MutationObserver(refreshFinish).observe(eta,{childList:true,characterData:true,subtree:true});pause.addEventListener("click",function(){if(control.paused){resume();pause.textContent="Pausar";message.textContent="Download retomado. Preparando o próximo bloco…";}else{control.paused=true;pause.textContent="Retomar";message.textContent="Pausa solicitada. O bloco atual terminará antes de pausar com segurança.";}});actions.querySelectorAll(".pw-backup-stream-cancel").forEach(function(button){button.addEventListener("click",resume);});refreshFinish();}new MutationObserver(function(records){records.forEach(function(record){record.addedNodes.forEach(function(node){if(node.nodeType!==1){return;}if(node.matches&&node.matches(".pw-backup-stream-modal")){enhance(node);}node.querySelectorAll&&node.querySelectorAll(".pw-backup-stream-modal").forEach(enhance);});});}).observe(document.body,{childList:true,subtree:true});document.querySelectorAll(".pw-backup-stream-modal").forEach(enhance);});</script>';
}

add_action( 'admin_footer', 'pw_printway_backup_parts_download_window', 100 );
function pw_printway_backup_parts_download_window() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){function fmt(n){return n<1048576?(n/1024).toFixed(1)+" KB":n<1073741824?(n/1048576).toFixed(1)+" MB":(n/1073741824).toFixed(2)+" GB";}function wait(ms){return new Promise(function(resolve){setTimeout(resolve,ms);});}document.addEventListener("click",function(event){var button=event.target.closest&&event.target.closest(".pw-backup-parts-start");if(!button||!window.showDirectoryPicker){return;}event.preventDefault();event.stopPropagation();event.stopImmediatePropagation();var set=button.closest(".pw-backup-download-set"),chosen=Array.prototype.slice.call(set.querySelectorAll(".pw-backup-part-item:checked"));if(!chosen.length){return;}var files=[{url:button.dataset.indexUrl,name:button.dataset.indexName,size:0}].concat(chosen.map(function(item){return {url:item.dataset.url,name:item.dataset.name,size:Number(item.dataset.size||0)};})),total=files.reduce(function(sum,item){return sum+item.size;},0),modal=document.createElement("div");modal.className="pw-backup-stream-modal is-open";modal.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Baixando volumes do backup</h2><p class=\"pw-backup-stream-message\">Selecione a pasta de destino para iniciar.</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante estimado</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Retomadas</small><strong class=\"pw-backup-stream-retries\">Nenhuma</strong></div></div><p class=\"description pw-backup-stream-folder-help\">Escolha uma subpasta criada por você, por exemplo <strong>Downloads/Backup PrintWay</strong>. O Edge bloqueia a pasta Downloads diretamente por segurança.</p><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button button-primary pw-backup-stream-choose\">Escolher pasta</button><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(modal);var message=modal.querySelector(".pw-backup-stream-message"),bar=modal.querySelector(".pw-backup-stream-bar"),percent=modal.querySelector(".pw-backup-stream-percent"),speed=modal.querySelector(".pw-backup-stream-speed"),eta=modal.querySelector(".pw-backup-stream-eta"),retryText=modal.querySelector(".pw-backup-stream-retries"),choose=modal.querySelector(".pw-backup-stream-choose"),cancel=modal.querySelector(".pw-backup-stream-cancel"),stopped=false,directory,writer,done=0,retries=0,started=performance.now();cancel.addEventListener("click",function(){stopped=true;if(writer&&writer.abort){writer.abort();}modal.remove();});function update(text){var elapsed=Math.max(.001,(performance.now()-started)/1000),rate=done/elapsed,rest=rate?(total-done)/rate:0,now=new Date(Date.now()+rest*1000);bar.style.width=(total?Math.round(100*done/total):0)+"%";percent.textContent=(total?Math.round(100*done/total):0)+"%";message.textContent=text;speed.textContent=fmt(rate)+"/s";eta.textContent=rate?(Math.floor(rest/60)+"min "+Math.round(rest%60)+"s · término às "+now.toLocaleTimeString("pt-BR",{hour:"2-digit",minute:"2-digit"})):"Calculando…";retryText.textContent=retries?retries+" retomada(s)":"Nenhuma";}async function run(){choose.hidden=true;for(var index=0;index<files.length;index++){var item=files[index],handle=await directory.getFileHandle(item.name,{create:true});writer=await handle.createWritable();if(!item.size){var indexResponse=await fetch(item.url,{credentials:"same-origin",cache:"no-store"});if(!indexResponse.ok){throw new Error("Não foi possível baixar o arquivo de índice.");}var indexData=await indexResponse.arrayBuffer();await writer.write(indexData);await writer.close();writer=null;continue;}var offset=0;while(offset<item.size){if(stopped){return;}var end=Math.min(item.size-1,offset+8*1024*1024-1),data;for(;;){try{var response=await fetch(item.url,{headers:{Range:"bytes="+offset+"-"+end},credentials:"same-origin",cache:"no-store"});if(!response.ok){throw new Error("HTTP "+response.status);}data=await response.arrayBuffer();if(data.byteLength!==end-offset+1){throw new Error("Bloco incompleto");}break;}catch(error){if(stopped){return;}retries++;update("Conexão interrompida. Retomando "+item.name+"…");await wait(Math.min(10000,1500+retries*400));}}if(stopped){return;}await writer.write({type:"write",position:offset,data:data});offset=end+1;done+=data.byteLength;update(item.name+" — "+fmt(offset)+" de "+fmt(item.size)+".");}await writer.close();writer=null;}bar.style.width="100%";percent.textContent="100%";message.textContent="Volumes baixados com sucesso.";eta.textContent="Concluído";cancel.textContent="Fechar";cancel.onclick=function(){modal.remove();};}async function pick(){try{directory=await window.showDirectoryPicker({mode:"readwrite"});message.textContent="Pasta selecionada. Iniciando download dos volumes…";await run();}catch(error){if(stopped){return;}message.textContent="O Edge não permite usar Downloads diretamente. Crie uma subpasta, por exemplo Downloads/Backup PrintWay, e clique em Escolher pasta novamente.";choose.hidden=false;}}choose.addEventListener("click",pick);pick();},true);});});</script>';
}

add_action( 'admin_footer', 'pw_printway_backup_transfer_modal_lock', 105 );
function pw_printway_backup_transfer_modal_lock() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){function active(){return document.querySelector(".pw-backup-stream-modal.is-open");}document.addEventListener("click",function(event){var modal=active();if(modal&&!event.target.closest(".pw-backup-stream-panel")){event.preventDefault();event.stopPropagation();event.stopImmediatePropagation();}},true);document.addEventListener("keydown",function(event){if(active()&&event.key==="Escape"){event.preventDefault();event.stopPropagation();event.stopImmediatePropagation();}},true);});</script>';
}

/* Toda transferência concluída usa o mesmo encerramento visual. */
add_action( 'admin_footer', 'pw_printway_backup_transfer_finish_style', 106 );
function pw_printway_backup_transfer_finish_style() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<style>.pw-backup-stream-cancel.is-finished{background:#2271b1!important;border-color:#2271b1!important;color:#fff!important}</style><script>document.addEventListener("DOMContentLoaded",function(){function finish(modal){var percent=modal.querySelector(".pw-backup-stream-percent"),button=modal.querySelector(".pw-backup-stream-cancel");if(!percent||!button||percent.textContent.trim()!=="100%"){return;}button.textContent="Fechar";button.classList.add("is-finished");}function imported(modal){var message=modal.querySelector(".pw-backup-stream-message"),button=modal.querySelector(".pw-backup-stream-cancel");if(!message||!button||!(/incluído na lista de backups/i.test(message.textContent))||modal.dataset.imported){return;}modal.dataset.imported="1";button.textContent="Fechar";button.classList.add("is-finished");button.onclick=function(){window.location.reload();};setTimeout(function(){if(document.body.contains(modal)){window.location.reload();}},1800);}new MutationObserver(function(){document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(function(modal){finish(modal);imported(modal);});}).observe(document.body,{childList:true,subtree:true,characterData:true});document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(function(modal){finish(modal);imported(modal);});});</script>';
}

add_action( 'admin_footer', 'pw_printway_backup_restore_progress_window', 107 );
function pw_printway_backup_restore_progress_window() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<style>.pw-restore-picker-panel{width:min(820px,96vw)!important;max-height:88vh;overflow:auto}.pw-restore-picker-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:16px 0}.pw-restore-card{border:1px solid #dcdcde;border-radius:7px;padding:13px;background:#fff}.pw-restore-card>label{font-weight:700}.pw-restore-items{max-height:180px;overflow:auto;margin:9px 0 0 22px;padding:0}.pw-restore-items label{display:block;margin:6px 0}.pw-restore-warning{padding:10px 12px;border-left:4px solid #dba617;background:#fcf9e8}.pw-restore-protected{padding:10px 12px;border-left:4px solid #00a32a;background:#edfaef}@media(max-width:700px){.pw-restore-picker-grid{grid-template-columns:1fr}}</style><script>document.addEventListener("DOMContentLoaded",function(){var forms=Array.prototype.slice.call(document.querySelectorAll("form")),form=forms.filter(function(item){return item.querySelector("input[name=action][value=pw_printway_backup_restore]");})[0],nonce=' . wp_json_encode( $nonce ) . ';if(!form){return;}var selection=form.querySelector("input[name=restore_selection]"),foreignInput=form.querySelector("input[name=confirm_foreign_site]");function esc(value){var d=document.createElement("div");d.textContent=value==null?"":String(value);return d.innerHTML;}function progress(){var button=form.querySelector("button[type=submit]");if(button){button.disabled=true;}var box=document.createElement("div");box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Restaurando backup</h2><p class=\"pw-backup-stream-message\">Validando os itens selecionados…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\" style=\"width:18%\"></span></div><p class=\"pw-backup-stream-percent\">Em andamento</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Etapa</small><strong class=\"pw-backup-restore-stage\">Validação de segurança</strong></div><div class=\"pw-backup-stream-stat\"><small>Importante</small><strong>A página será atualizada ao concluir</strong></div></div></div>";document.body.appendChild(box);var message=box.querySelector(".pw-backup-stream-message"),stage=box.querySelector(".pw-backup-restore-stage"),bar=box.querySelector(".pw-backup-stream-bar"),step=0,steps=[["Validando os itens selecionados…","Validação de segurança","18%"],["Criando backup de segurança do estado atual…","Proteção antes da restauração","42%"],["Aplicando somente os itens escolhidos…","Restauração seletiva","72%"],["Finalizando a restauração…","Aguardando confirmação do servidor","88%"]];setInterval(function(){step=Math.min(steps.length-1,step+1);message.textContent=steps[step][0];stage.textContent=steps[step][1];bar.style.width=steps[step][2];},3500);}function card(key,title,items,description){if(Array.isArray(items)&&!items.length){return "";}if(items===false){return "";}var html="<div class=\"pw-restore-card\"><label><input type=\"checkbox\" class=\"pw-restore-master\" data-key=\""+key+"\"> "+title+"</label>"+(description?"<p class=\"description\">"+description+"</p>":"");if(Array.isArray(items)){html+="<div class=\"pw-restore-items\">"+items.map(function(item){var id=typeof item==="object"?item.id:item,label=typeof item==="object"?item.label:item;return "<label><input type=\"checkbox\" class=\"pw-restore-child\" data-key=\""+key+"\" value=\""+esc(id)+"\"> "+esc(label)+"</label>";}).join("")+"</div>";}return html+"</div>";}function picker(data){var a=data.available||{},modal=document.createElement("div");modal.className="pw-backup-stream-modal is-open";var cards=card("database","Banco de dados completo",!!a.database,"Restaura todas as tabelas incluídas no backup.")+card("pages","Páginas individuais",a.pages||[],"Alternativa segura para recuperar somente páginas escolhidas.")+card("plugins","Arquivos de plugins",a.plugins||[],"Escolha exatamente quais plugins deseja restaurar.")+card("themes","Temas",a.themes||[],"Escolha exatamente quais temas deseja restaurar.")+card("core","Arquivos principais do site",!!a.core,"Arquivos nativos e da raiz do WordPress.")+card("uploads","Mídias e uploads",!!a.uploads,"Imagens e arquivos enviados ao WordPress.")+card("content_other","Demais arquivos",!!a.content_other,"Outros arquivos existentes em wp-content.")+card("config","Configurações do servidor",!!a.config,"wp-config.php e .htaccess; use somente quando necessário.");modal.innerHTML="<div class=\"pw-backup-stream-panel pw-restore-picker-panel\"><h2>O que deseja restaurar?</h2><p>O backup foi validado. Somente os itens realmente encontrados nele são exibidos abaixo.</p><div class=\"pw-restore-protected\"><strong>Proteção ativa:</strong> o plugin PrintWay que executa esta restauração e a pasta onde os backups ZIP ficam armazenados nunca serão substituídos.</div>"+(data.foreign?"<div class=\"pw-restore-warning\"><strong>Backup de outro site</strong>"+(data.site_url?"<br>Origem: "+esc(data.site_url):"")+"<p><label><input type=\"checkbox\" class=\"pw-foreign-confirm\"> Entendo os riscos e desejo continuar com este backup de outro site.</label></p></div>":"")+"<div class=\"pw-restore-picker-grid\">"+cards+"</div><p class=\"pw-restore-error\" style=\"color:#b32d2e\"></p><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-restore-cancel\">Cancelar</button> <button type=\"button\" class=\"button button-primary pw-restore-continue\">Continuar restauração</button></div></div>";document.body.appendChild(modal);modal.querySelectorAll(".pw-restore-master").forEach(function(master){master.addEventListener("change",function(){modal.querySelectorAll(".pw-restore-child[data-key=\""+master.dataset.key+"\"]").forEach(function(child){child.checked=master.checked;});});});modal.querySelectorAll(".pw-restore-child").forEach(function(child){child.addEventListener("change",function(){var children=Array.prototype.slice.call(modal.querySelectorAll(".pw-restore-child[data-key=\""+child.dataset.key+"\"]")),master=modal.querySelector(".pw-restore-master[data-key=\""+child.dataset.key+"\"]");master.checked=children.some(function(item){return item.checked;});master.indeterminate=master.checked&&!children.every(function(item){return item.checked;});});});modal.querySelector(".pw-restore-cancel").onclick=function(){modal.remove();};modal.querySelector(".pw-restore-continue").onclick=function(){var chosen={};modal.querySelectorAll(".pw-restore-master").forEach(function(master){var children=Array.prototype.slice.call(modal.querySelectorAll(".pw-restore-child[data-key=\""+master.dataset.key+"\"]"));chosen[master.dataset.key]=children.length?children.filter(function(item){return item.checked;}).map(function(item){return item.value;}):master.checked;});var has=Object.keys(chosen).some(function(key){return Array.isArray(chosen[key])?chosen[key].length:chosen[key];}),error=modal.querySelector(".pw-restore-error"),foreign=modal.querySelector(".pw-foreign-confirm");if(!has){error.textContent="Selecione ao menos um item para restaurar.";return;}if(data.foreign&&(!foreign||!foreign.checked)){error.textContent="Confirme que deseja restaurar um backup de outro site.";return;}selection.value=JSON.stringify(chosen);if(foreignInput){foreignInput.checked=!!data.foreign;}modal.remove();form.requestSubmit();};}form.addEventListener("submit",function(event){if(selection&&selection.value){progress();return;}event.preventDefault();event.stopImmediatePropagation();var select=form.querySelector("select[name=stored_file]");if(!select||!select.value){return;}var button=form.querySelector("button[type=submit]");if(button){button.disabled=true;}fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_restore_catalog",nonce:nonce,file:select.value})}).then(function(response){return response.json();}).then(function(result){if(button){button.disabled=false;}if(!result.success){throw new Error((result.data&&result.data.message)||"Não foi possível validar o backup.");}picker(result.data||{});}).catch(function(error){if(button){button.disabled=false;}alert(error.message||"Não foi possível validar o backup.");});},true);});</script>';
}

/* A restauração entre sites exige uma confirmação visível e específica. */
add_action( 'admin_footer', 'pw_printway_backup_foreign_restore_confirmation', 108 );
function pw_printway_backup_foreign_restore_confirmation() {
	/* A confirmação de backup externo agora faz parte da janela única de seleção. */
}

/* O envio em blocos possui seu próprio controlador para que a janela de
 * andamento apareça mesmo se outro script do painel atrasar a inicialização. */
add_action( 'admin_footer', 'pw_printway_backup_external_upload_progress_window', 109 );
function pw_printway_backup_external_upload_progress_window() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<style>.pw-backup-stream-modal{display:none;position:fixed;inset:0;z-index:100300;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:20px}.pw-backup-stream-modal.is-open{display:flex}.pw-backup-stream-panel{width:min(520px,96vw);background:#fff;padding:24px;border-radius:10px;box-shadow:0 18px 65px rgba(0,0,0,.35)}.pw-backup-stream-track{height:10px;background:#dcdcde;border-radius:5px;overflow:hidden}.pw-backup-stream-bar{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}.pw-backup-stream-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:14px 0}.pw-backup-stream-stat{padding:9px;background:#f6f7f7;border-radius:5px}.pw-backup-stream-stat small{display:block;color:#646970;margin-bottom:2px}.pw-backup-stream-actions{margin-top:16px}</style><script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-external-upload"),input=document.getElementById("pw-backup-external-file"),nonce=' . wp_json_encode( $nonce ) . ';if(!form||!input){return;}form.addEventListener("submit",function(event){event.preventDefault();event.stopImmediatePropagation();if(form.dataset.pwUploading){return;}var file=input.files&&input.files[0];if(!file){return;}form.dataset.pwUploading="1";var submit=form.querySelector("button[type=submit]"),box=document.createElement("div");box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Enviando ZIP único</h2><p class=\"pw-backup-stream-message\">Preparando envio…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Blocos enviados</small><strong class=\"pw-backup-stream-retries\">0</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(box);if(submit){submit.disabled=true;}var message=box.querySelector(".pw-backup-stream-message"),bar=box.querySelector(".pw-backup-stream-bar"),percent=box.querySelector(".pw-backup-stream-percent"),speed=box.querySelector(".pw-backup-stream-speed"),eta=box.querySelector(".pw-backup-stream-eta"),blocks=box.querySelector(".pw-backup-stream-retries"),cancel=box.querySelector(".pw-backup-stream-cancel"),size=1024*1024,offset=0,part=0,stopped=false,started=performance.now(),id="upload-"+Date.now()+"-"+Math.random().toString(36).slice(2);function reset(){delete form.dataset.pwUploading;if(submit){submit.disabled=false;}}cancel.addEventListener("click",function(){stopped=true;reset();box.remove();});function update(){var elapsed=Math.max(.01,(performance.now()-started)/1000),rate=offset/elapsed,remaining=rate?(file.size-offset)/rate:0,value=Math.round(offset*100/file.size);bar.style.width=value+"%";percent.textContent=value+"%";speed.textContent=(rate/1048576).toFixed(1)+" MB/s";eta.textContent=rate?Math.floor(remaining/60)+"min "+Math.round(remaining%60)+"s":"Calculando…";blocks.textContent=part+" de "+Math.ceil(file.size/size);}function send(){if(stopped){return;}var end=Math.min(file.size,offset+size),data=new FormData();data.append("action","pw_printway_backup_upload_chunk");data.append("nonce",nonce);data.append("upload_id",id);data.append("offset",String(offset));data.append("total",String(file.size));data.append("last",end===file.size?"1":"");data.append("chunk",file.slice(offset,end),file.name);message.textContent="Enviando "+(offset/1048576).toFixed(1)+" MB de "+(file.size/1048576).toFixed(1)+" MB…";fetch(ajaxurl,{method:"POST",body:data,credentials:"same-origin"}).then(function(response){return response.json();}).then(function(result){if(!result.success){throw new Error((result.data&&result.data.message)||"Falha no envio.");}offset=end;part++;update();if(offset<file.size){send();return;}message.textContent=result.data.message||"ZIP validado e incluído na lista de backups.";bar.style.width="100%";percent.textContent="100%";eta.textContent="Concluído";cancel.textContent="Fechar";cancel.classList.add("is-finished");cancel.onclick=function(){window.location.reload();};setTimeout(function(){window.location.reload();},1800);}).catch(function(error){message.textContent="Erro: "+(error.message||"não foi possível concluir o envio")+".";cancel.textContent="Fechar";cancel.classList.add("is-finished");cancel.onclick=function(){reset();box.remove();};});}send();},true);});</script>';
}

/* Continua a tela de andamento enquanto o servidor extrai cada volume. */
add_action( 'admin_footer', 'pw_printway_backup_import_progress_window', 110 );
function pw_printway_backup_import_progress_window() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( wp_create_nonce( 'pw_printway_backup_ajax' ) ) . ';function inspect(modal){if(!modal||modal.dataset.importPolling){return;}var message=modal.querySelector(".pw-backup-stream-message");if(!message){return;}var found=(message.textContent||"").match(/\[PWIMPORT:([A-Za-z0-9_-]+):(\d+)\]/);if(!found){return;}modal.dataset.importPolling="1";var token=found[1],total=Math.max(1,Number(found[2])),bar=modal.querySelector(".pw-backup-stream-bar"),percent=modal.querySelector(".pw-backup-stream-percent"),blocks=modal.querySelector(".pw-backup-stream-retries"),eta=modal.querySelector(".pw-backup-stream-eta"),cancel=modal.querySelector(".pw-backup-stream-cancel");message.textContent="Upload concluído. Incluindo os volumes no servidor…";if(cancel){cancel.disabled=true;}function step(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_import_step",nonce:nonce,token:token})}).then(function(response){return response.json();}).then(function(result){if(!result.success){throw new Error((result.data&&result.data.message)||"Falha ao incluir o backup.");}var data=result.data||{},done=Number(data.done||0),all=Math.max(1,Number(data.total||total)),value=Math.min(99,Math.round(99*done/all));bar.style.width=value+"%";percent.textContent=value+"%";if(blocks){blocks.textContent=done+" de "+all;}eta.textContent=data.complete?"Concluído":"Incluindo volumes";message.textContent=data.complete?"ZIP validado e incluído na lista de backups.":"Incluindo volume "+done+" de "+all+": "+(data.current||"");if(data.complete){bar.style.width="100%";percent.textContent="100%";if(cancel){cancel.disabled=false;cancel.textContent="Fechar";cancel.classList.add("is-finished");cancel.onclick=function(){window.location.reload();};}setTimeout(function(){window.location.reload();},1800);return;}setTimeout(step,80);}).catch(function(error){message.textContent="Erro na inclusão: "+(error.message||"tente novamente")+".";if(cancel){cancel.disabled=false;cancel.textContent="Fechar";cancel.classList.add("is-finished");cancel.onclick=function(){modal.remove();};}});}step();}new MutationObserver(function(){document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(inspect);}).observe(document.body,{childList:true,subtree:true,characterData:true});document.querySelectorAll(".pw-backup-stream-modal.is-open").forEach(inspect);});</script>';
}

/* Se a página for recarregada após o upload, retoma a inclusão pendente na
 * mesma janela, sem exigir que o administrador envie o ZIP novamente. */
add_action( 'admin_footer', 'pw_printway_backup_resume_import_progress', 111 );
function pw_printway_backup_resume_import_progress() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$token = get_transient( 'pw_printway_import_pending_' . get_current_user_id() );
	$state = $token ? get_transient( pw_printway_backup_import_state_key( $token ) ) : false;
	if ( ! is_array( $state ) || get_current_user_id() !== absint( $state['user_id'] ?? 0 ) ) { return; }
	$done = absint( $state['index'] ?? 0 ); $total = max( 1, count( (array) ( $state['parts'] ?? array() ) ) );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){if(document.querySelector(".pw-backup-stream-modal.is-open")){return;}var box=document.createElement("div"),done=' . wp_json_encode( $done ) . ',total=' . wp_json_encode( $total ) . ',token=' . wp_json_encode( $token ) . ';box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Incluindo ZIP no servidor</h2><p class=\"pw-backup-stream-message\">[PWIMPORT:"+token+":"+total+"] Retomando a inclusão do backup…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\" style=\"width:"+Math.min(99,Math.round(99*done/total))+"%\"></span></div><p class=\"pw-backup-stream-percent\">"+Math.min(99,Math.round(99*done/total))+"%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Etapa</small><strong class=\"pw-backup-stream-speed\">Importando volumes</strong></div><div class=\"pw-backup-stream-stat\"><small>Andamento</small><strong class=\"pw-backup-stream-eta\">Incluindo volumes</strong></div><div class=\"pw-backup-stream-stat\"><small>Volumes</small><strong class=\"pw-backup-stream-retries\">"+done+" de "+total+"</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\" disabled>Em andamento</button></div></div>";document.body.appendChild(box);});</script>';
}

/* O navegador ou a rede podem perder a resposta de um único bloco, embora o
 * servidor já o tenha recebido. Este controlador retoma do último byte aceito
 * e continua tentando até o administrador cancelar explicitamente. */
add_action( 'admin_footer', 'pw_printway_backup_resilient_upload_window', 112 );
function pw_printway_backup_resilient_upload_window() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-external-upload"),input=document.getElementById("pw-backup-external-file"),nonce=' . wp_json_encode( $nonce ) . ';if(!form||!input){return;}function wait(ms){return new Promise(function(resolve){setTimeout(resolve,ms);});}function openModal(){var box=document.createElement("div");box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Enviando ZIP único</h2><p class=\"pw-backup-stream-message\">Preparando envio…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tentativas de retomada</small><strong class=\"pw-backup-stream-retries\">Nenhuma</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(box);return box;}form.addEventListener("submit",function(event){event.preventDefault();event.stopImmediatePropagation();if(form.dataset.pwResilientUploading){return;}var file=input.files&&input.files[0];if(!file){return;}form.dataset.pwResilientUploading="1";var submit=form.querySelector("button[type=submit]"),box=openModal(),message=box.querySelector(".pw-backup-stream-message"),bar=box.querySelector(".pw-backup-stream-bar"),percent=box.querySelector(".pw-backup-stream-percent"),speed=box.querySelector(".pw-backup-stream-speed"),eta=box.querySelector(".pw-backup-stream-eta"),retriesNode=box.querySelector(".pw-backup-stream-retries"),cancel=box.querySelector(".pw-backup-stream-cancel"),chunk=1024*1024,offset=0,sent=0,retries=0,started=performance.now(),stopped=false,id="upload-"+Date.now()+"-"+Math.random().toString(36).slice(2);if(submit){submit.disabled=true;}function reset(){delete form.dataset.pwResilientUploading;if(submit){submit.disabled=false;}}cancel.addEventListener("click",function(){stopped=true;reset();box.remove();});function update(){var elapsed=Math.max(.01,(performance.now()-started)/1000),rate=offset/elapsed,remaining=rate?(file.size-offset)/rate:0,value=Math.min(99,Math.round(offset*100/file.size));bar.style.width=value+"%";percent.textContent=value+"%";speed.textContent=(rate/1048576).toFixed(1)+" MB/s";eta.textContent=rate?Math.floor(remaining/60)+"min "+Math.round(remaining%60)+"s":"Calculando…";retriesNode.textContent=retries?retries+" tentativa(s)":"Nenhuma";}async function oneRequest(end){var data=new FormData(),controller=window.AbortController?new AbortController():null,timer=controller?setTimeout(function(){controller.abort();},45000):null;data.append("action","pw_printway_backup_upload_chunk");data.append("nonce",nonce);data.append("upload_id",id);data.append("offset",String(offset));data.append("total",String(file.size));data.append("last",end===file.size?"1":"");data.append("chunk",file.slice(offset,end),file.name);try{var response=await fetch(ajaxurl,{method:"POST",body:data,credentials:"same-origin",signal:controller?controller.signal:undefined}),result=await response.json();if(!response.ok||!result.success){var problem=new Error((result.data&&result.data.message)||("HTTP "+response.status));problem.serverOffset=result.data&&result.data.offset;throw problem;}return result.data||{};}finally{if(timer){clearTimeout(timer);}}}async function send(){while(offset<file.size&&!stopped){var end=Math.min(file.size,offset+chunk);message.textContent="Enviando "+(offset/1048576).toFixed(1)+" MB de "+(file.size/1048576).toFixed(1)+" MB…";try{var result=await oneRequest(end);offset=end;sent=Math.ceil(offset/chunk);retries=0;update();if(offset>=file.size){message.textContent=result.message||"Upload concluído.";bar.style.width="100%";percent.textContent="100%";eta.textContent="Incluindo no servidor";return;}}catch(error){if(stopped){return;}if(typeof error.serverOffset!=="undefined"&&Number(error.serverOffset)>=0){offset=Math.min(file.size,Number(error.serverOffset));sent=Math.ceil(offset/chunk);update();}retries++;message.textContent="Conexão interrompida. Retomando automaticamente em alguns segundos (tentativa "+retries+")…";retriesNode.textContent=retries+" tentativa(s)";await wait(Math.min(12000,1500+retries*500));}}}send().catch(function(error){message.textContent="Erro inesperado: "+(error.message||"tente novamente")+".";cancel.textContent="Fechar";cancel.onclick=function(){reset();box.remove();};});},true);});</script>';
}

/* Este ouvinte fica no documento, na fase de captura. Ele bloqueia os
 * controladores antigos antes que possam encerrar a janela ao primeiro erro. */
add_action( 'admin_footer', 'pw_printway_backup_upload_capture_guard', 113 );
function pw_printway_backup_upload_capture_guard() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-external-upload"),input=document.getElementById("pw-backup-external-file"),nonce=' . wp_json_encode( $nonce ) . ';if(!form||!input){return;}document.addEventListener("submit",function(event){if(event.target!==form||form.dataset.pwCaptureUpload){return;}event.preventDefault();event.stopImmediatePropagation();var file=input.files&&input.files[0];if(!file){return;}form.dataset.pwCaptureUpload="1";var box=document.createElement("div");box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Enviando ZIP único</h2><p class=\"pw-backup-stream-message\">Preparando envio…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tentativas de retomada</small><strong class=\"pw-backup-stream-retries\">Nenhuma</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(box);var message=box.querySelector(".pw-backup-stream-message"),bar=box.querySelector(".pw-backup-stream-bar"),percent=box.querySelector(".pw-backup-stream-percent"),speed=box.querySelector(".pw-backup-stream-speed"),eta=box.querySelector(".pw-backup-stream-eta"),tries=box.querySelector(".pw-backup-stream-retries"),cancel=box.querySelector(".pw-backup-stream-cancel"),size=1024*1024,offset=0,retry=0,stopped=false,start=performance.now(),id="upload-"+Date.now()+"-"+Math.random().toString(36).slice(2);function close(){delete form.dataset.pwCaptureUpload;box.remove();}cancel.addEventListener("click",function(){stopped=true;close();});function paint(){var seconds=Math.max(.01,(performance.now()-start)/1000),rate=offset/seconds,left=rate?(file.size-offset)/rate:0,p=Math.min(99,Math.round(100*offset/file.size));bar.style.width=p+"%";percent.textContent=p+"%";speed.textContent=(rate/1048576).toFixed(1)+" MB/s";eta.textContent=rate?Math.floor(left/60)+"min "+Math.round(left%60)+"s":"Calculando…";tries.textContent=retry?retry+" tentativa(s)":"Nenhuma";}function later(ms){return new Promise(function(resolve){setTimeout(resolve,ms);});}async function send(){while(!stopped&&offset<file.size){var end=Math.min(file.size,offset+size),body=new FormData(),controller=window.AbortController?new AbortController():null,timer=controller?setTimeout(function(){controller.abort();},60000):null;body.append("action","pw_printway_backup_upload_chunk");body.append("nonce",nonce);body.append("upload_id",id);body.append("offset",String(offset));body.append("total",String(file.size));body.append("last",end===file.size?"1":"");body.append("chunk",file.slice(offset,end),file.name);message.textContent="Enviando "+(offset/1048576).toFixed(1)+" MB de "+(file.size/1048576).toFixed(1)+" MB…";try{var response=await fetch(ajaxurl,{method:"POST",body:body,credentials:"same-origin",signal:controller?controller.signal:undefined}),result=await response.json();if(!response.ok||!result.success){var error=new Error((result.data&&result.data.message)||"Falha temporária de conexão.");error.offset=result.data&&result.data.offset;throw error;}offset=end;retry=0;paint();if(offset===file.size){message.textContent=result.data&&result.data.message?result.data.message:"Upload concluído.";bar.style.width="100%";percent.textContent="100%";eta.textContent="Incluindo no servidor";return;}}catch(error){if(stopped){return;}if(typeof error.offset!=="undefined"&&Number(error.offset)>=0){offset=Math.min(file.size,Number(error.offset));paint();}retry++;message.textContent="Conexão interrompida. Nova tentativa automática em alguns segundos (tentativa "+retry+")…";tries.textContent=retry+" tentativa(s)";await later(Math.min(12000,2000+retry*500));}finally{if(timer){clearTimeout(timer);}}}}send();},true);});</script>';
}

/* O total de retomadas é informativo: não volta a zero depois que um bloco
 * posterior consegue ser enviado. */
add_action( 'admin_footer', 'pw_printway_backup_upload_retry_total', 114 );
function pw_printway_backup_upload_retry_total() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){function keep(node){if(!node||node.dataset.retryTotal){return;}node.dataset.retryTotal="1";var maximum=0,watch=new MutationObserver(function(){var text=node.textContent||"",match=text.match(/(\\d+) tentativa/);if(match){maximum=Math.max(maximum,Number(match[1]));return;}if(maximum&&/^Nenhuma$/.test(text.trim())){node.textContent=maximum+" tentativa(s)";}});watch.observe(node,{childList:true,characterData:true,subtree:true});}new MutationObserver(function(){document.querySelectorAll(".pw-backup-stream-retries").forEach(keep);}).observe(document.body,{childList:true,subtree:true});document.querySelectorAll(".pw-backup-stream-retries").forEach(keep);});</script>';
}

/* Os controladores antigos atualizavam a tela a cada 1 MB e mantinham vários
 * observadores globais ativos. Em um ZIP de 3 GB isso gera milhares de ciclos
 * no navegador. Este único controlador usa blocos de 8 MB e só atualiza o DOM
 * após cada resposta do servidor. */
remove_action( 'admin_footer', 'pw_printway_backup_external_upload_progress_window', 109 );
remove_action( 'admin_footer', 'pw_printway_backup_resilient_upload_window', 112 );
remove_action( 'admin_footer', 'pw_printway_backup_upload_capture_guard', 113 );
remove_action( 'admin_footer', 'pw_printway_backup_upload_retry_total', 114 );
remove_action( 'admin_footer', 'pw_printway_backup_import_progress_window', 110 );

add_action( 'admin_footer', 'pw_printway_backup_compact_upload_controller', 120 );
function pw_printway_backup_compact_upload_controller() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-external-upload"),input=document.getElementById("pw-backup-external-file"),nonce=' . wp_json_encode( $nonce ) . ';if(!form||!input){return;}function pause(ms){return new Promise(function(resolve){setTimeout(resolve,ms);});}function modal(title){var node=document.createElement("div");node.className="pw-backup-stream-modal is-open";node.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>"+title+"</h2><p class=\"pw-backup-stream-message\">Preparando…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tentativas de retomada</small><strong class=\"pw-backup-stream-retries\">Nenhuma</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(node);return node;}function ui(node){return{message:node.querySelector(".pw-backup-stream-message"),bar:node.querySelector(".pw-backup-stream-bar"),percent:node.querySelector(".pw-backup-stream-percent"),speed:node.querySelector(".pw-backup-stream-speed"),eta:node.querySelector(".pw-backup-stream-eta"),tries:node.querySelector(".pw-backup-stream-retries"),cancel:node.querySelector(".pw-backup-stream-cancel")};}function beginImport(node,token,total){var x=ui(node),done=0,attempts=0,stopped=false;x.message.textContent="Upload concluído. Conferindo o pacote no servidor…";x.cancel.disabled=true;async function step(){if(stopped){return;}try{var response=await fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_import_step",nonce:nonce,token:token})}),result=await response.json();if(!response.ok||!result.success){throw new Error((result.data&&result.data.message)||"Falha temporária ao incluir o backup.");}var data=result.data||{};done=Number(data.done||0);total=Math.max(1,Number(data.total||total||1));var p=data.complete?100:Math.min(99,Math.round(done*99/total));x.bar.style.width=p+"%";x.percent.textContent=p+"%";x.tries.textContent=attempts?attempts+" tentativa(s)":"Nenhuma";x.message.textContent=data.complete?"ZIP validado e incluído na lista de backups.":"Incluindo volume "+done+" de "+total+": "+(data.current||"");x.eta.textContent=data.complete?"Concluído":"Incluindo volumes";if(data.complete){x.cancel.disabled=false;x.cancel.textContent="Fechar";x.cancel.onclick=function(){window.location.reload();};return;}await pause(120);step();}catch(error){attempts++;x.message.textContent="Conexão interrompida durante a inclusão. Nova tentativa automática em alguns segundos (tentativa "+attempts+")…";x.tries.textContent=attempts+" tentativa(s)";x.cancel.disabled=false;x.cancel.textContent="Cancelar";x.cancel.onclick=function(){stopped=true;node.remove();};await pause(Math.min(15000,2500+attempts*500));if(!stopped){x.cancel.disabled=true;x.cancel.textContent="Cancelar";step();}}}step();}document.addEventListener("submit",function(event){if(event.target!==form||form.dataset.pwCompactUpload){return;}event.preventDefault();event.stopImmediatePropagation();var file=input.files&&input.files[0];if(!file){return;}form.dataset.pwCompactUpload="1";var submit=form.querySelector("button[type=submit]"),node=modal("Enviando ZIP único"),x=ui(node),chunk=8*1024*1024,offset=0,retries=0,started=performance.now(),stopped=false,id="upload-"+Date.now()+"-"+Math.random().toString(36).slice(2);if(submit){submit.disabled=true;}x.cancel.onclick=function(){stopped=true;delete form.dataset.pwCompactUpload;if(submit){submit.disabled=false;}node.remove();};function paint(){var elapsed=Math.max(.01,(performance.now()-started)/1000),rate=offset/elapsed,left=rate?(file.size-offset)/rate:0,p=Math.min(99,Math.round(offset*100/file.size));x.bar.style.width=p+"%";x.percent.textContent=p+"%";x.speed.textContent=(rate/1048576).toFixed(1)+" MB/s";x.eta.textContent=rate?Math.floor(left/60)+"min "+Math.round(left%60)+"s":"Calculando…";x.tries.textContent=retries?retries+" tentativa(s)":"Nenhuma";}async function upload(){while(!stopped&&offset<file.size){var end=Math.min(file.size,offset+chunk),data=new FormData(),controller=window.AbortController?new AbortController():null,timer=controller?setTimeout(function(){controller.abort();},90000):null;data.append("action","pw_printway_backup_upload_chunk");data.append("nonce",nonce);data.append("upload_id",id);data.append("offset",String(offset));data.append("total",String(file.size));data.append("last",end===file.size?"1":"");data.append("chunk",file.slice(offset,end),file.name);x.message.textContent="Enviando "+(offset/1048576).toFixed(1)+" MB de "+(file.size/1048576).toFixed(1)+" MB…";try{var response=await fetch(ajaxurl,{method:"POST",body:data,credentials:"same-origin",signal:controller?controller.signal:undefined}),result=await response.json();if(!response.ok||!result.success){var error=new Error((result.data&&result.data.message)||"Falha temporária de conexão.");error.offset=result.data&&result.data.offset;throw error;}offset=end;paint();if(offset===file.size){x.bar.style.width="100%";x.percent.textContent="100%";beginImport(node,result.data&&result.data.import_token,Number(result.data&&result.data.import_total||0));return;}}catch(error){if(stopped){return;}if(typeof error.offset!=="undefined"&&Number(error.offset)>=0){offset=Math.min(file.size,Number(error.offset));paint();}retries++;x.message.textContent="Conexão interrompida. Nova tentativa automática em alguns segundos (tentativa "+retries+")…";x.tries.textContent=retries+" tentativa(s)";await pause(Math.min(15000,2500+retries*500));}finally{if(timer){clearTimeout(timer);}}}}upload();},true);});</script>';
}

add_action( 'admin_head', 'pw_printway_backup_stream_window_style' );
function pw_printway_backup_stream_window_style() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<style>.pw-backup-stream-modal{display:none;position:fixed;inset:0;z-index:100300;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:20px;box-sizing:border-box}.pw-backup-stream-modal.is-open{display:flex}.pw-backup-stream-panel{width:min(620px,96vw);max-height:calc(100vh - 40px);overflow:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 18px 65px rgba(0,0,0,.35);box-sizing:border-box}.pw-backup-stream-track{height:10px;background:#dcdcde;border-radius:5px;overflow:hidden}.pw-backup-stream-bar{display:block;height:100%;width:0;background:#2271b1;transition:width .2s}.pw-backup-stream-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:14px 0}.pw-backup-stream-stat{padding:10px;background:#f6f7f7;border-radius:6px}.pw-backup-stream-stat small{display:block;color:#646970;margin-bottom:3px}.pw-backup-stream-actions{margin-top:16px}</style>';
}

/* Retoma uma inclusão que ficou pendente depois de atualizar a página. */
add_action( 'admin_footer', 'pw_printway_backup_resume_import_compact', 121 );
function pw_printway_backup_resume_import_compact() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var modal=document.querySelector(".pw-backup-stream-modal.is-open"),nonce=' . wp_json_encode( $nonce ) . ';if(!modal||modal.dataset.resumeCompact){return;}var message=modal.querySelector(".pw-backup-stream-message"),found=message&&(message.textContent||"").match(/\[PWIMPORT:([A-Za-z0-9_-]+):(\d+)\]/);if(!found){return;}modal.dataset.resumeCompact="1";var bar=modal.querySelector(".pw-backup-stream-bar"),percent=modal.querySelector(".pw-backup-stream-percent"),tries=modal.querySelector(".pw-backup-stream-retries"),eta=modal.querySelector(".pw-backup-stream-eta"),cancel=modal.querySelector(".pw-backup-stream-cancel"),token=found[1],total=Math.max(1,Number(found[2])),attempts=0,stopped=false;message.textContent="Retomando a inclusão do backup no servidor…";function wait(ms){return new Promise(function(resolve){setTimeout(resolve,ms);});}async function step(){if(stopped){return;}try{var response=await fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_import_step",nonce:nonce,token:token})}),result=await response.json();if(!response.ok||!result.success){throw new Error((result.data&&result.data.message)||"Falha temporária.");}var data=result.data||{},done=Number(data.done||0);total=Math.max(1,Number(data.total||total));var p=data.complete?100:Math.min(99,Math.round(done*99/total));bar.style.width=p+"%";percent.textContent=p+"%";tries.textContent=attempts?attempts+" tentativa(s)":"Nenhuma";eta.textContent=data.complete?"Concluído":"Incluindo volumes";message.textContent=data.complete?"ZIP validado e incluído na lista de backups.":"Incluindo volume "+done+" de "+total+": "+(data.current||"");if(data.complete){cancel.disabled=false;cancel.textContent="Fechar";cancel.onclick=function(){window.location.reload();};return;}await wait(120);step();}catch(error){attempts++;message.textContent="Conexão interrompida. Nova tentativa automática em alguns segundos (tentativa "+attempts+")…";tries.textContent=attempts+" tentativa(s)";cancel.disabled=false;cancel.textContent="Cancelar";cancel.onclick=function(){stopped=true;modal.remove();};await wait(Math.min(15000,2500+attempts*500));if(!stopped){cancel.disabled=true;step();}}}step();});</script>';
}

add_action( 'admin_footer', 'pw_printway_backup_cleanup_cancelled_upload', 122 );
function pw_printway_backup_cleanup_cancelled_upload() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( wp_create_nonce( 'pw_printway_backup_ajax' ) ) . ';document.addEventListener("click",function(event){var button=event.target.closest&&event.target.closest(".pw-backup-stream-cancel"),modal=button&&button.closest(".pw-backup-stream-modal");if(!button||!modal||!/^Enviando ZIP único$/i.test((modal.querySelector("h2")||{}).textContent||"")){return;}fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_upload_cleanup",nonce:nonce,all:"1"})});},true);});</script>';
}

/* Após o arquivo chegar por completo, uma renovação curta libera os recursos
 * acumulados pelo navegador durante o upload de vários GB. A própria página
 * retoma a inclusão pelo token salvo no servidor. Ao concluir, a janela fecha
 * e a listagem é atualizada automaticamente. */
add_action( 'admin_footer', 'pw_printway_backup_upload_import_auto_transition', 123 );
function pw_printway_backup_upload_import_auto_transition() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var timer=setInterval(function(){var modal=document.querySelector(".pw-backup-stream-modal.is-open"),title=modal&&modal.querySelector("h2"),message=modal&&modal.querySelector(".pw-backup-stream-message");if(!modal||!message){return;}var text=(message.textContent||"").trim();if(title&&/^Enviando ZIP único$/i.test(title.textContent||"")&&/^Upload concluído\./i.test(text)&&!modal.dataset.autoUploadReload){modal.dataset.autoUploadReload="1";message.textContent="Upload concluído. Reiniciando a tela para incluir o ZIP no servidor…";setTimeout(function(){window.location.reload();},250);return;}if(/^ZIP validado e incluído na lista de backups\.$/i.test(text)&&!modal.dataset.autoImportReload){modal.dataset.autoImportReload="1";message.textContent="Backup incluído com sucesso. Atualizando a lista…";setTimeout(function(){modal.remove();window.location.reload();},700);}},150);window.addEventListener("beforeunload",function(){clearInterval(timer);});});</script>';
}

add_action( 'admin_footer', 'pw_printway_backup_import_volume_counter', 124 );
function pw_printway_backup_import_volume_counter() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	echo '<script>document.addEventListener("DOMContentLoaded",function(){setInterval(function(){var modal=document.querySelector(".pw-backup-stream-modal.is-open"),message=modal&&modal.querySelector(".pw-backup-stream-message"),counter=modal&&modal.querySelector(".pw-backup-stream-retries");if(!message||!counter){return;}var found=(message.textContent||"").match(/Incluindo volume\s+(\d+)\s+de\s+(\d+)/i);if(found){counter.textContent=found[1]+" de "+found[2];}},300);});</script>';
}

/* Envio com XHR isolado por bloco. Cada FormData deixa de existir antes que o
 * próximo bloco seja criado, evitando o consumo crescente de memória do
 * navegador observado em uploads muito grandes. */
remove_action( 'admin_footer', 'pw_printway_backup_compact_upload_controller', 120 );
add_action( 'admin_footer', 'pw_printway_backup_xhr_upload_controller', 125 );
function pw_printway_backup_xhr_upload_controller() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-external-upload"),input=document.getElementById("pw-backup-external-file"),nonce=' . wp_json_encode( $nonce ) . ';if(!form||!input){return;}function wait(ms,fn){setTimeout(fn,ms);}function buildModal(){var box=document.createElement("div");box.className="pw-backup-stream-modal is-open";box.innerHTML="<div class=\"pw-backup-stream-panel\"><h2>Enviando ZIP único</h2><p class=\"pw-backup-stream-message\">Preparando envio…</p><div class=\"pw-backup-stream-track\"><span class=\"pw-backup-stream-bar\"></span></div><p class=\"pw-backup-stream-percent\">0%</p><div class=\"pw-backup-stream-stats\"><div class=\"pw-backup-stream-stat\"><small>Velocidade atual</small><strong class=\"pw-backup-stream-speed\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tempo restante</small><strong class=\"pw-backup-stream-eta\">Calculando…</strong></div><div class=\"pw-backup-stream-stat\"><small>Tentativas de retomada</small><strong class=\"pw-backup-stream-retries\">Nenhuma</strong></div></div><div class=\"pw-backup-stream-actions\"><button type=\"button\" class=\"button pw-backup-stream-cancel\">Cancelar</button></div></div>";document.body.appendChild(box);return box;}document.addEventListener("submit",function(event){if(event.target!==form||form.dataset.pwXhrUpload){return;}event.preventDefault();event.stopImmediatePropagation();var file=input.files&&input.files[0];if(!file){return;}form.dataset.pwXhrUpload="1";var submit=form.querySelector("button[type=submit]"),box=buildModal(),message=box.querySelector(".pw-backup-stream-message"),bar=box.querySelector(".pw-backup-stream-bar"),percent=box.querySelector(".pw-backup-stream-percent"),speed=box.querySelector(".pw-backup-stream-speed"),eta=box.querySelector(".pw-backup-stream-eta"),tries=box.querySelector(".pw-backup-stream-retries"),cancel=box.querySelector(".pw-backup-stream-cancel"),size=512*1024,offset=0,retries=0,stopped=false,started=performance.now(),id="upload-"+Date.now()+"-"+Math.random().toString(36).slice(2),request=null;if(submit){submit.disabled=true;}function finishCancel(){stopped=true;if(request){request.abort();}delete form.dataset.pwXhrUpload;if(submit){submit.disabled=false;}box.remove();}cancel.onclick=finishCancel;function paint(){var elapsed=Math.max(.01,(performance.now()-started)/1000),rate=offset/elapsed,left=rate?(file.size-offset)/rate:0,p=Math.min(99,Math.round(offset*100/file.size));bar.style.width=p+"%";percent.textContent=p+"%";speed.textContent=(rate/1048576).toFixed(1)+" MB/s";eta.textContent=rate?Math.floor(left/60)+"min "+Math.round(left%60)+"s":"Calculando…";tries.textContent=retries?retries+" tentativa(s)":"Nenhuma";}function retry(reason,serverOffset){if(stopped){return;}if(typeof serverOffset!=="undefined"&&Number(serverOffset)>=0){offset=Math.min(file.size,Number(serverOffset));paint();if(offset>=file.size){message.textContent="Upload já recebido. Reiniciando a tela para incluir o ZIP…";wait(250,function(){window.location.reload();});return;}}retries++;message.textContent="Conexão interrompida. Nova tentativa automática em alguns segundos (tentativa "+retries+")…";tries.textContent=retries+" tentativa(s)";wait(Math.min(12000,1800+retries*400),sendNext);}function sendNext(){if(stopped){return;}if(offset>=file.size){message.textContent="Upload concluído. Reiniciando a tela para incluir o ZIP…";bar.style.width="100%";percent.textContent="100%";wait(250,function(){window.location.reload();});return;}var end=Math.min(file.size,offset+size),data=new FormData(),xhr=new XMLHttpRequest();request=xhr;data.append("action","pw_printway_backup_upload_chunk");data.append("nonce",nonce);data.append("upload_id",id);data.append("offset",String(offset));data.append("total",String(file.size));data.append("last",end===file.size?"1":"");data.append("chunk",file.slice(offset,end),file.name);message.textContent="Enviando "+(offset/1048576).toFixed(1)+" MB de "+(file.size/1048576).toFixed(1)+" MB…";xhr.open("POST",ajaxurl,true);xhr.timeout=120000;xhr.onload=function(){request=null;var result;try{result=JSON.parse(xhr.responseText||"{}");}catch(error){retry("Resposta inválida");return;}if(xhr.status<200||xhr.status>=300||!result.success){retry((result.data&&result.data.message)||("HTTP "+xhr.status),result.data&&result.data.offset);return;}offset=end;paint();data=null;xhr=null;wait(100,sendNext);};xhr.onerror=function(){request=null;data=null;xhr=null;retry("Falha de rede");};xhr.ontimeout=function(){request=null;data=null;xhr=null;retry("Tempo esgotado");};xhr.onabort=function(){request=null;data=null;xhr=null;};xhr.send(data);}sendNext();},true);});</script>';
}

/* O log fica disponível mesmo depois de atualizar a página, para que possa
 * ser copiado e enviado na análise de qualquer interrupção. */
add_action( 'admin_footer', 'pw_printway_backup_upload_log_viewer', 126 );
function pw_printway_backup_upload_log_viewer() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ( $_GET['tab'] ?? '' ) !== 'restore' ) { return; }
	$nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
	$script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var button = document.getElementById('pw-backup-show-upload-log');
	var nonce = __NONCE__;
	if (!button) { return; }
	function clientLog() {
		try { return JSON.parse(localStorage.getItem('pw_printway_upload_client_log') || '[]'); }
		catch (ignore) { return []; }
	}
	function show(id, serverText) {
		var local = clientLog();
		var combined = (serverText || '') + (local.length ? '\n\nDIAGNÓSTICO PRESERVADO NO NAVEGADOR:\n' + local.map(function (entry) { return JSON.stringify(entry); }).join('\n') : '');
		var box = document.createElement('div'); box.className = 'pw-backup-stream-modal is-open';
		box.innerHTML = '<div class="pw-backup-stream-panel"><h2>Log do último envio</h2><p>Identificador: <strong></strong></p><textarea readonly style="width:100%;min-height:320px;font-family:monospace;box-sizing:border-box"></textarea><div class="pw-backup-stream-actions"><button type="button" class="button button-primary pw-copy">Copiar log</button> <button type="button" class="button pw-close">Fechar</button></div></div>';
		box.querySelector('strong').textContent = id || '—'; var area = box.querySelector('textarea'); area.value = combined || 'Nenhum evento registrado.';
		box.querySelector('.pw-copy').onclick = function () { area.focus(); area.select(); if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(area.value); } else { document.execCommand('copy'); } this.textContent = 'Log copiado'; };
		box.querySelector('.pw-close').onclick = function () { box.remove(); }; document.body.appendChild(box);
	}
	function wordpressFallback() {
		return fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams({ action: 'pw_printway_backup_upload_log', nonce: nonce }) }).then(function (response) { return response.json(); }).then(function (result) { if (!result.success) { throw new Error((result.data && result.data.message) || 'Log indisponível.'); } show(result.data.id, result.data.log); });
	}
	button.addEventListener('click', function () {
		button.disabled = true; var session = null;
		try { session = JSON.parse(localStorage.getItem('pw_printway_upload_session') || 'null'); } catch (ignore) {}
		var task;
		if (session && session.endpoint && session.uploadId && session.token) {
			task = fetch(session.endpoint + '?operation=log', { method: 'POST', cache: 'no-store', headers: { 'X-PW-Upload-ID': session.uploadId, 'X-PW-Upload-Token': session.token } }).then(function (response) { return response.json(); }).then(function (result) { if (!result.success) { throw new Error('Log direto indisponível.'); } show(session.uploadId, result.data && result.data.log || ''); });
		} else { task = wordpressFallback(); }
		task.catch(function () { var local = clientLog(); if (local.length) { show(session && session.uploadId || 'navegador', ''); } else { return wordpressFallback().catch(function () { alert('Não foi possível carregar o log. Consulte também /wp-content/plugins/printway/backup/import.log pelo FTP.'); }); } }).finally(function () { button.disabled = false; });
	});
});
JS;
	$script = str_replace( '__NONCE__', wp_json_encode( $nonce ), $script );
	echo '<script>' . $script . '</script>';
}

/* A previsão tem um único lugar próprio: o cartão inferior. */
remove_action( 'admin_footer', 'pw_printway_backup_download_eta_clock' );
remove_action( 'admin_footer', 'pw_printway_backup_download_pause_controls', 99 );
add_action( 'admin_footer', 'pw_printway_backup_download_clean_display', 99 );
function pw_printway_backup_download_clean_display() {
	if ( empty( $_GET['page'] ) || 'pw-printway-backup' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	echo '<style>.pw-backup-stream-stat{min-height:48px;box-sizing:border-box}.pw-backup-stream-finish{border-left:3px solid #2271b1}</style><script>document.addEventListener("DOMContentLoaded",function(){function remaining(text){var h=(text.match(/(\\d+)h/)||[,0])[1],m=(text.match(/(\\d+)min/)||[,0])[1],s=(text.match(/(\\d+)s/)||[,0])[1];return Number(h)*3600+Number(m)*60+Number(s);}function enhance(modal){if(!modal||modal.dataset.cleanDisplay){return;}var title=modal.querySelector("h2");if(!title||!/^Baixando/i.test(title.textContent||"")){return;}modal.dataset.cleanDisplay="1";var stats=modal.querySelector(".pw-backup-stream-stats"),eta=modal.querySelector(".pw-backup-stream-eta");if(!stats||!eta){return;}var finish=document.createElement("div");finish.className="pw-backup-stream-stat pw-backup-stream-finish";finish.innerHTML="<small>Término previsto</small><strong class=\"pw-backup-stream-finish-time\">Calculando…</strong>";stats.appendChild(finish);var finishText=finish.querySelector(".pw-backup-stream-finish-time"),observer=new MutationObserver(update);function update(){var raw=eta.textContent,clean=raw.replace(/\\s*·\\s*término às.*$/i,"");if(clean!==raw){observer.disconnect();eta.textContent=clean;observer.observe(eta,{childList:true,characterData:true,subtree:true});raw=clean;}if(/Concluído/.test(raw)){finishText.textContent="Concluído";return;}var seconds=remaining(raw);if(!seconds){return;}var end=new Date(Date.now()+seconds*1000);finishText.textContent="Término às "+end.toLocaleTimeString("pt-BR",{hour:"2-digit",minute:"2-digit"});}observer.observe(eta,{childList:true,characterData:true,subtree:true});update();}new MutationObserver(function(records){records.forEach(function(record){record.addedNodes.forEach(function(node){if(node.nodeType!==1){return;}if(node.matches&&node.matches(".pw-backup-stream-modal")){enhance(node);}node.querySelectorAll&&node.querySelectorAll(".pw-backup-stream-modal").forEach(enhance);});});}).observe(document.body,{childList:true,subtree:true});document.querySelectorAll(".pw-backup-stream-modal").forEach(enhance);});</script>';
}

function pw_printway_backup_manifest( $zip_path, $allow_foreign_site = false ) {
	if ( ! class_exists( 'ZipArchive' ) || ! is_file( $zip_path ) ) {
		throw new RuntimeException( 'Arquivo de backup inválido.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		throw new RuntimeException( 'Não foi possível abrir o ZIP selecionado.' );
	}
	$raw = $zip->getFromName( 'manifest.json' );
	$zip->close();
	$manifest = $raw ? json_decode( $raw, true ) : array();
	if ( ! is_array( $manifest ) || 'printway-site-backup' !== ( $manifest['format'] ?? '' ) || empty( $manifest['signature'] ) ) {
		throw new RuntimeException( 'Este ZIP não é um backup PrintWay válido.' );
	}
	$signature = $manifest['signature'];
	unset( $manifest['signature'] );
	$signature_valid = hash_equals( hash_hmac( 'sha256', wp_json_encode( $manifest ), wp_salt( 'auth' ) ), $signature );
	if ( ! $signature_valid && ! $allow_foreign_site ) {
		throw new RuntimeException( 'A assinatura de segurança do backup não confere para este site. Este backup pode pertencer a outro site; confirme essa opção antes de restaurar.' );
	}
	/* A assinatura identifica o site de origem. O administrador pode restaurar um
	 * backup PrintWay de outro site somente após a confirmação extra no formulário. */
	$manifest['_pw_signature_valid'] = $signature_valid;
	return $manifest;
}

function pw_printway_backup_restore_catalog( $zip_path ) {
	$manifest = pw_printway_backup_manifest( $zip_path, true );
	$entries  = array();
	foreach ( pw_printway_backup_volume_paths( $zip_path ) as $volume_path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $volume_path ) ) { continue; }
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = isset( $stat['name'] ) ? (string) $stat['name'] : '';
			if ( $name && '/' !== substr( $name, -1 ) ) { $entries[ $name ] = true; }
		}
		$zip->close();
	}
	$top_items = static function( $prefix ) use ( $entries ) {
		$items = array();
		foreach ( array_keys( $entries ) as $entry ) {
			if ( 0 !== strpos( $entry, $prefix ) ) { continue; }
			$rest = substr( $entry, strlen( $prefix ) );
			$top  = strtok( $rest, '/' );
			if ( $top ) { $items[ $top ] = $top; }
		}
		uksort( $items, 'strnatcasecmp' );
		return array_values( $items );
	};
	$self_plugin = basename( untrailingslashit( wp_normalize_path( PW_PRINTWAY_DIR ) ) );
	$plugins = array_values( array_diff( $top_items( 'files/wp-content/plugins/' ), array( $self_plugin ) ) );
	$themes  = $top_items( 'files/wp-content/themes/' );
	$pages   = array();
	foreach ( (array) ( $manifest['pages'] ?? array() ) as $id => $page ) {
		if ( ! is_array( $page ) ) { continue; }
		$pages[] = array( 'id' => (string) absint( $id ), 'label' => (string) ( $page['post_title'] ?: '(sem título)' ) );
	}
	$has_prefix = static function( $prefix ) use ( $entries ) {
		foreach ( array_keys( $entries ) as $entry ) { if ( 0 === strpos( $entry, $prefix ) ) { return true; } }
		return false;
	};
	return array(
		'manifest' => $manifest,
		'available' => array(
			'database'      => isset( $entries['database/database.sql'] ),
			'pages'         => $pages,
			'core'          => $has_prefix( 'files/root/' ),
			'plugins'       => $plugins,
			'themes'        => $themes,
			'uploads'       => $has_prefix( 'files/wp-content/uploads/' ),
			'content_other' => $has_other_content,
			'config'        => $has_prefix( 'files/config/' ),
		),
	);
}

function pw_printway_backup_sanitize_restore_selection( $raw, $catalog ) {
	$posted = json_decode( (string) $raw, true );
	if ( ! is_array( $posted ) ) { throw new RuntimeException( 'Escolha o que deseja restaurar.' ); }
	$available = (array) $catalog['available'];
	$selection = array();
	foreach ( array( 'database', 'core', 'uploads', 'content_other', 'config' ) as $key ) {
		$selection[ $key ] = ! empty( $posted[ $key ] ) && ! empty( $available[ $key ] );
	}
	foreach ( array( 'plugins', 'themes' ) as $key ) {
		$requested = array_map( 'sanitize_file_name', (array) ( $posted[ $key ] ?? array() ) );
		$selection[ $key ] = array_values( array_intersect( $requested, (array) ( $available[ $key ] ?? array() ) ) );
	}
	$page_ids = array_map( 'strval', array_map( 'absint', (array) ( $posted['pages'] ?? array() ) ) );
	$available_page_ids = wp_list_pluck( (array) ( $available['pages'] ?? array() ), 'id' );
	$selection['pages'] = array_values( array_intersect( $page_ids, $available_page_ids ) );
	if ( ! array_filter( $selection ) ) { throw new RuntimeException( 'Selecione ao menos um item para restaurar.' ); }
	return $selection;
}

add_action( 'wp_ajax_pw_printway_backup_restore_catalog', 'pw_printway_backup_restore_catalog_ajax' );
function pw_printway_backup_restore_catalog_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$file = basename( sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ) );
	$path = trailingslashit( pw_printway_backup_directory() ) . $file;
	if ( ! $file || ! is_file( $path ) || ! in_array( $file, array_map( 'basename', pw_printway_backup_list() ), true ) ) {
		wp_send_json_error( array( 'message' => 'Escolha um backup válido.' ), 400 );
	}
	try {
		$catalog = pw_printway_backup_restore_catalog( $path );
		$is_foreign = empty( $catalog['manifest']['_pw_signature_valid'] );
		/* Credenciais e regras do servidor de origem nunca são oferecidas numa
		 * migração entre sites. */
		if ( $is_foreign ) { $catalog['available']['config'] = false; }
		unset( $catalog['manifest']['pages'] );
		wp_send_json_success( array(
			'available' => $catalog['available'],
			'foreign'   => $is_foreign,
			'site_url'  => (string) ( $catalog['manifest']['site_url'] ?? '' ),
		) );
	} catch ( Throwable $error ) {
		wp_send_json_error( array( 'message' => $error->getMessage() ), 400 );
	}
}

function pw_printway_backup_sql_statements( $sql ) {
	$statements = array(); $buffer = ''; $quote = ''; $length = strlen( $sql );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $sql[ $i ];
		if ( $quote ) {
			$buffer .= $char;
			if ( '\\' === $char && $i + 1 < $length ) { $buffer .= $sql[ ++$i ]; continue; }
			if ( $char === $quote ) { $quote = ''; }
			continue;
		}
		if ( "'" === $char || '"' === $char || '`' === $char ) { $quote = $char; $buffer .= $char; continue; }
		if ( ';' === $char ) { if ( trim( $buffer ) !== '' ) { $statements[] = trim( $buffer ); } $buffer = ''; continue; }
		$buffer .= $char;
	}
	if ( trim( $buffer ) !== '' ) { $statements[] = trim( $buffer ); }
	return $statements;
}

/* Limpeza opcional: usada apenas quando o administrador pede substituição completa. */
function pw_printway_backup_remove_tree( $directory, $preserve = array() ) {
	if ( ! is_dir( $directory ) ) {
		return;
	}
	$preserve = array_map( 'wp_normalize_path', $preserve );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) {
		$path = wp_normalize_path( $item->getPathname() );
		$keep = false;
		foreach ( $preserve as $preserved ) {
			if ( $path === $preserved || 0 === strpos( $path, trailingslashit( $preserved ) ) || 0 === strpos( trailingslashit( $preserved ), trailingslashit( $path ) ) ) {
				$keep = true;
				break;
			}
		}
		if ( $keep || $item->isLink() ) {
			continue;
		}
		if ( $item->isDir() ) { @rmdir( $path ); } else { @unlink( $path ); }
	}
}

function pw_printway_backup_remove_selected( $base, $selected, $preserve = array() ) {
	if ( null === $selected ) {
		pw_printway_backup_remove_tree( $base, $preserve );
		return;
	}
	foreach ( (array) $selected as $name ) {
		$name = basename( (string) $name );
		if ( '' === $name ) { continue; }
		$path = trailingslashit( $base ) . $name;
		if ( is_dir( $path ) ) { pw_printway_backup_remove_tree( $path, $preserve ); @rmdir( $path ); }
		elseif ( is_file( $path ) ) { @unlink( $path ); }
	}
}

function pw_printway_backup_manifest_selection( $components, $component ) {
	if ( ! isset( $components[ $component ] ) || ! is_array( $components[ $component ] ) || ! array_key_exists( 'selected', $components[ $component ] ) ) {
		return null;
	}
	return $components[ $component ]['selected'];
}

function pw_printway_backup_prepare_full_replace( $components, $preserve_destination_config = false ) {
	$backup_dir = pw_printway_backup_directory();
	if ( isset( $components['uploads'] ) ) {
		$uploads = wp_upload_dir(); pw_printway_backup_remove_selected( $uploads['basedir'], pw_printway_backup_manifest_selection( $components, 'uploads' ), array( $backup_dir ) );
	}
	if ( isset( $components['themes'] ) ) { pw_printway_backup_remove_selected( get_theme_root(), pw_printway_backup_manifest_selection( $components, 'themes' ) ); }
	if ( isset( $components['plugins'] ) ) {
		/* Mantém o plugin ativo até o fim da operação para não interromper a restauração. */
		pw_printway_backup_remove_selected( WP_PLUGIN_DIR, pw_printway_backup_manifest_selection( $components, 'plugins' ), array( pw_printway_backup_protected_plugin_directory(), $backup_dir ) );
	}
	if ( isset( $components['content_other'] ) ) {
		pw_printway_backup_remove_selected( WP_CONTENT_DIR, pw_printway_backup_manifest_selection( $components, 'content_other' ), array( WP_PLUGIN_DIR, get_theme_root(), wp_upload_dir()['basedir'], $backup_dir ) );
	}
	if ( isset( $components['core'] ) ) {
		pw_printway_backup_remove_selected( ABSPATH, pw_printway_backup_manifest_selection( $components, 'core' ), array( WP_CONTENT_DIR, ABSPATH . 'wp-config.php', ABSPATH . '.htaccess' ) );
	}
	if ( isset( $components['config'] ) && ! $preserve_destination_config ) {
		$selected = pw_printway_backup_manifest_selection( $components, 'config' );
		foreach ( array( 'wp-config.php', '.htaccess' ) as $name ) { if ( ( null === $selected || in_array( $name, $selected, true ) ) && is_file( ABSPATH . $name ) ) { @unlink( ABSPATH . $name ); } }
	}
}

function pw_printway_backup_restore_pages( $snapshots, $selected_ids ) {
	$id_map = array();
	foreach ( $selected_ids as $source_id ) {
		if ( empty( $snapshots[ $source_id ] ) || ! is_array( $snapshots[ $source_id ] ) ) { continue; }
		$page = $snapshots[ $source_id ];
		$existing = get_page_by_path( sanitize_title( (string) ( $page['post_name'] ?? '' ) ), OBJECT, 'page' );
		$postarr = array(
			'post_type'      => 'page',
			'post_title'     => wp_slash( (string) ( $page['post_title'] ?? '' ) ),
			'post_name'      => sanitize_title( (string) ( $page['post_name'] ?? '' ) ),
			'post_content'   => wp_slash( (string) ( $page['post_content'] ?? '' ) ),
			'post_excerpt'   => wp_slash( (string) ( $page['post_excerpt'] ?? '' ) ),
			'post_status'    => sanitize_key( (string) ( $page['post_status'] ?? 'draft' ) ),
			'menu_order'     => (int) ( $page['menu_order'] ?? 0 ),
			'comment_status' => (string) ( $page['comment_status'] ?? 'closed' ),
			'ping_status'    => (string) ( $page['ping_status'] ?? 'closed' ),
			'post_password'  => (string) ( $page['post_password'] ?? '' ),
		);
		if ( $existing ) { $postarr['ID'] = (int) $existing->ID; }
		$result = wp_insert_post( $postarr, true );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Falha ao restaurar a página "' . (string) ( $page['post_title'] ?? '' ) . '": ' . $result->get_error_message() ); }
		$id_map[ (string) $source_id ] = (int) $result;
		foreach ( (array) ( $page['meta'] ?? array() ) as $key => $values ) {
			$key = (string) $key;
			if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) { continue; }
			delete_post_meta( $result, $key );
			foreach ( (array) $values as $value ) { add_post_meta( $result, $key, maybe_unserialize( $value ) ); }
		}
	}
	foreach ( $selected_ids as $source_id ) {
		if ( empty( $id_map[ $source_id ] ) || empty( $snapshots[ $source_id ]['post_parent'] ) ) { continue; }
		$parent_source = (string) absint( $snapshots[ $source_id ]['post_parent'] );
		if ( isset( $id_map[ $parent_source ] ) ) { wp_update_post( array( 'ID' => $id_map[ $source_id ], 'post_parent' => $id_map[ $parent_source ] ) ); }
	}
	return count( $id_map );
}

function pw_printway_backup_restore( $path, $full_replace = false, $allow_foreign_site = false, $selection = null ) {
	global $wpdb;
	$manifest = pw_printway_backup_manifest( $path, $allow_foreign_site );
	/* Guarde estes dados antes de substituir o banco: um backup externo não pode
	 * alterar o domínio nem as credenciais do site que está recebendo os dados. */
	$destination_home = untrailingslashit( home_url( '/' ) );
	$destination_siteurl = untrailingslashit( site_url( '/' ) );
	$volume_paths = pw_printway_backup_volume_paths( $path );
	if ( empty( $volume_paths ) ) { throw new RuntimeException( 'Os volumes deste backup não foram encontrados.' ); }
	$component_data = (array) $manifest['components'];
	if ( is_array( $selection ) ) {
		$filtered = array();
		foreach ( array( 'database', 'core', 'uploads', 'content_other', 'config' ) as $key ) {
			if ( ! empty( $selection[ $key ] ) && isset( $component_data[ $key ] ) ) { $filtered[ $key ] = $component_data[ $key ]; }
		}
		foreach ( array( 'plugins', 'themes' ) as $key ) {
			if ( ! empty( $selection[ $key ] ) && isset( $component_data[ $key ] ) ) { $filtered[ $key ] = array( 'selected' => array_values( $selection[ $key ] ) ); }
		}
		$component_data = $filtered;
	}
	$components = array_keys( $component_data );
	/* Mantém compatibilidade com chamadas internas antigas que restauravam o
	 * componente inteiro sem enviar uma seleção explícita. */
	$selected_plugins = is_array( $selection )
		? (array) ( $selection['plugins'] ?? array() )
		: (array) ( $component_data['plugins']['selected'] ?? array() );
	$selected_themes = is_array( $selection )
		? (array) ( $selection['themes'] ?? array() )
		: (array) ( $component_data['themes']['selected'] ?? array() );
	if ( $full_replace ) {
		pw_printway_backup_prepare_full_replace( $component_data, $allow_foreign_site );
	}
	if ( in_array( 'database', $components, true ) ) {
		$sql = false;
		foreach ( $volume_paths as $volume_path ) {
			$database_zip = new ZipArchive();
			$validation_flags = defined( 'ZipArchive::CHECKCONS' ) ? ZipArchive::CHECKCONS : 0;
			if ( true !== $database_zip->open( $volume_path, $validation_flags ) ) { throw new RuntimeException( 'Não foi possível validar o volume ' . basename( $volume_path ) . '.' ); }
			$sql = $database_zip->getFromName( 'database/database.sql' );
			$database_zip->close();
			if ( false !== $sql ) { break; }
		}
		if ( ! $sql ) { throw new RuntimeException( 'O banco de dados não foi encontrado no backup.' ); }
		foreach ( pw_printway_backup_sql_statements( $sql ) as $statement ) {
			if ( false === $wpdb->query( $statement ) ) { throw new RuntimeException( 'Falha ao restaurar o banco de dados: ' . $wpdb->last_error ); }
		}
		if ( $allow_foreign_site ) {
			$wpdb->update( $wpdb->options, array( 'option_value' => $destination_home ), array( 'option_name' => 'home' ) );
			$wpdb->update( $wpdb->options, array( 'option_value' => $destination_siteurl ), array( 'option_name' => 'siteurl' ) );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'home', 'options' );
			wp_cache_delete( 'siteurl', 'options' );
		}
	}
	foreach ( $volume_paths as $volume_path ) {
		$zip = new ZipArchive();
		$validation_flags = defined( 'ZipArchive::CHECKCONS' ) ? ZipArchive::CHECKCONS : 0;
		if ( true !== $zip->open( $volume_path, $validation_flags ) ) { throw new RuntimeException( 'Não foi possível validar o volume ' . basename( $volume_path ) . '.' ); }
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i ); $inside = (string) $stat['name'];
			if ( 'manifest.json' === $inside || 0 !== strpos( $inside, 'files/' ) || substr( $inside, -1 ) === '/' || false !== strpos( $inside, '..' ) ) { continue; }
			$relative = substr( $inside, 6 );
			if ( $allow_foreign_site && ( 0 === strpos( $relative, 'config/' ) || in_array( $relative, array( 'root/wp-config.php', 'root/.htaccess' ), true ) ) ) { continue; }
			$allowed = false;
			if ( 0 === strpos( $relative, 'root/' ) ) { $allowed = in_array( 'core', $components, true ); $target = ABSPATH . substr( $relative, 5 ); }
			elseif ( 0 === strpos( $relative, 'wp-content/plugins/' ) ) {
				$plugin = strtok( substr( $relative, strlen( 'wp-content/plugins/' ) ), '/' );
				$allowed = in_array( 'plugins', $components, true ) && in_array( $plugin, $selected_plugins, true );
				$target = WP_CONTENT_DIR . '/' . substr( $relative, 11 );
			} elseif ( 0 === strpos( $relative, 'wp-content/themes/' ) ) {
				$theme = strtok( substr( $relative, strlen( 'wp-content/themes/' ) ), '/' );
				$allowed = in_array( 'themes', $components, true ) && in_array( $theme, $selected_themes, true );
				$target = WP_CONTENT_DIR . '/' . substr( $relative, 11 );
			} elseif ( 0 === strpos( $relative, 'wp-content/uploads/' ) ) { $allowed = in_array( 'uploads', $components, true ); $target = WP_CONTENT_DIR . '/' . substr( $relative, 11 ); }
			elseif ( 0 === strpos( $relative, 'wp-content/' ) ) { $allowed = in_array( 'content_other', $components, true ); $target = WP_CONTENT_DIR . '/' . substr( $relative, 11 ); }
			elseif ( 0 === strpos( $relative, 'config/' ) ) { $allowed = in_array( 'config', $components, true ); $target = ABSPATH . substr( $relative, 7 ); }
			else { continue; }
			if ( ! $allowed ) { continue; }
			$target = wp_normalize_path( $target );
			if ( 0 !== strpos( $target, wp_normalize_path( ABSPATH ) ) || pw_printway_backup_is_protected_destination( $target ) ) { continue; }
			wp_mkdir_p( dirname( $target ) );
			$data = $zip->getFromIndex( $i );
			/* Grava primeiro num arquivo temporário e só então troca o destino. Assim
			 * uma interrupção não deixa o arquivo ativo parcialmente escrito. */
			$temp_target = $target . '.pw-restore-' . wp_generate_password( 10, false, false );
			if ( false === $data || false === file_put_contents( $temp_target, $data, LOCK_EX ) || ! @rename( $temp_target, $target ) ) {
				@unlink( $temp_target );
				$zip->close();
				throw new RuntimeException( 'Não foi possível restaurar o arquivo ' . basename( $target ) . '.' );
			}
		}
		$zip->close();
	}
	if ( is_array( $selection ) && ! empty( $selection['pages'] ) ) {
		pw_printway_backup_restore_pages( (array) ( $manifest['pages'] ?? array() ), $selection['pages'] );
	}
	$manifest['_pw_restored_components'] = array_keys( $component_data );
	if ( is_array( $selection ) && ! empty( $selection['pages'] ) ) { $manifest['_pw_restored_components'][] = 'pages'; }
	return $manifest;
}

add_action( 'admin_post_pw_printway_backup_save', 'pw_printway_backup_save' );
function pw_printway_backup_save() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_printway_backup_save' );
	$settings = pw_printway_backup_defaults();
	$settings['selection'] = array();
	foreach ( array_keys( pw_printway_backup_components() ) as $key ) {
		$settings[ $key ] = empty( $_POST[ 'components' ][ $key ] ) ? 0 : 1;
		$available = array_keys( pw_printway_backup_component_items( $key ) );
		$posted    = isset( $_POST['selection'][ $key ] ) ? (array) wp_unslash( $_POST['selection'][ $key ] ) : array();
		$posted    = array_values( array_unique( array_map( 'sanitize_text_field', $posted ) ) );
		$settings['selection'][ $key ] = array_values( array_intersect( $available, $posted ) );
	}
	$settings['schedule'] = in_array( $_POST['schedule'] ?? '', array( 'manual', 'hourly', 'pw_12_hours', 'daily', 'weekly', 'pw_monthly' ), true ) ? sanitize_key( $_POST['schedule'] ) : 'manual';
	$settings['retention'] = max( 1, min( 100, absint( $_POST['retention'] ?? 5 ) ) );
	$rules = isset( $_POST['retention_rules'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['retention_rules'] ) ) : array();
	$settings['retention_rules'] = array_values( array_intersect( $rules, array( 'count', 'size', 'age' ) ) );
	$size = str_replace( ',', '.', (string) wp_unslash( $_POST['retention_size_gb'] ?? '0' ) );
	$settings['retention_size_gb'] = max( 0, min( 100000, (float) $size ) );
	$settings['retention_age_days'] = max( 0, min( 36500, absint( $_POST['retention_age_days'] ?? 0 ) ) );
	$settings['retention_minimum'] = max( 1, min( 100, absint( $_POST['retention_minimum'] ?? 1 ) ) );
	$volume_size = absint( $_POST['zip_volume_size_mb'] ?? 256 );
	$settings['zip_volume_size_mb'] = $volume_size ? max( 16, min( 8192, $volume_size ) ) : 0;
	update_option( PW_PRINTWAY_BACKUP_OPTION, $settings, false );
	pw_printway_backup_schedule(); pw_printway_backup_prune();
	pw_printway_backup_set_notice( 'Configurações de backup salvas.' ); pw_printway_backup_redirect( 'config' );
}

add_action( 'admin_post_pw_printway_backup_create', 'pw_printway_backup_create_post' );
function pw_printway_backup_create_post() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_printway_backup_create' );
	try { $backup = pw_printway_backup_create( pw_printway_backup_settings() ); pw_printway_backup_set_notice( 'Backup criado e validado: ' . basename( $backup['path'] ) ); }
	catch ( Throwable $error ) { pw_printway_backup_set_notice( $error->getMessage(), 'error' ); }
	pw_printway_backup_redirect( 'backup' );
}

add_action( 'wp_ajax_pw_printway_backup_status', 'pw_printway_backup_ajax_status' );
function pw_printway_backup_ajax_status() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$status = get_transient( 'pw_printway_backup_progress' );
	$lock   = get_transient( 'pw_printway_backup_lock' );
	$queued = get_transient( PW_PRINTWAY_BACKUP_QUEUE );
	if ( ! is_array( $status ) && ( $lock || $queued ) ) {
		$status = array( 'percent' => 1, 'message' => 'Backup em andamento iniciado antes desta atualização.', 'state' => 'running', 'updated' => time() );
	}
	if ( ! is_array( $status ) ) {
		$status = array( 'percent' => 0, 'message' => 'Nenhum backup em andamento.', 'state' => 'idle', 'updated' => time() );
	}
	$status['queued'] = is_array( $queued );
	$status['locked'] = (bool) ( $lock || $queued );
	$status['stale']  = (bool) $lock && pw_printway_backup_status_is_stale( $status );
	$status['restore_count'] = count( pw_printway_backup_list() );
	wp_send_json_success( $status );
}

/* Consulta pequena e sem cache usada apenas para atualizar a guia Restauração
 * quando um backup acaba de ser gravado. */
add_action( 'wp_ajax_pw_printway_backup_restore_count', 'pw_printway_backup_ajax_restore_count' );
function pw_printway_backup_ajax_restore_count() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	nocache_headers();
	clearstatcache();
	$files = pw_printway_backup_list();
	$data  = array( 'restore_count' => count( $files ), 'checked_at' => microtime( true ) );
	if ( ! empty( $files ) ) {
		$latest = $files[0];
		$meta   = pw_printway_backup_file_meta( $latest );
		$data['latest_backup'] = wp_date( 'd/m/Y H:i', filemtime( $latest ), pw_printway_backup_timezone() ) . ' — ' . pw_printway_backup_kind_label( $meta['kind'] ) . ' — duração: ' . pw_printway_backup_duration_label( $meta['duration'] ) . '.';
		$data['latest_file'] = basename( $latest );
		$data['latest_mtime'] = (int) filemtime( $latest );
	}
	wp_send_json_success( $data );
}

add_action( 'wp_ajax_pw_printway_backup_schedule_status', 'pw_printway_backup_ajax_schedule_status' );
function pw_printway_backup_ajax_schedule_status() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	wp_send_json_success( array(
		'next'       => (int) wp_next_scheduled( PW_PRINTWAY_BACKUP_HOOK ),
		'last_visit' => absint( get_option( PW_PRINTWAY_BACKUP_LAST_VISIT_OPTION, 0 ) ),
		'server_now' => time(),
	) );
}

add_action( 'wp_ajax_pw_printway_backup_cancel', 'pw_printway_backup_ajax_cancel' );
function pw_printway_backup_ajax_cancel() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$status = get_transient( 'pw_printway_backup_progress' );
	pw_printway_backup_record_failure( 'cancelled', 'Interrupção solicitada manualmente pelo administrador.', is_array( $status ) ? $status : array() );
	$queued = get_transient( PW_PRINTWAY_BACKUP_QUEUE );
	if ( is_array( $queued ) && ! empty( $queued['token'] ) ) {
		wp_clear_scheduled_hook( PW_PRINTWAY_BACKUP_RUN_HOOK, array( (string) $queued['token'], (string) ( $queued['kind'] ?? 'manual' ) ) );
		delete_transient( PW_PRINTWAY_BACKUP_QUEUE );
		pw_printway_backup_progress( 0, 'Backup em espera cancelado pelo administrador.', 'cancelled' );
		wp_send_json_success( array( 'message' => 'Backup cancelado. Nenhum novo backup será iniciado automaticamente.' ) );
	}
	set_transient( 'pw_printway_backup_cancel', 1, HOUR_IN_SECONDS );
	/* Uma trava acima do limite inteligente normalmente foi deixada por um erro crítico. */
	if ( pw_printway_backup_status_is_stale( $status ) ) {
		delete_transient( 'pw_printway_backup_lock' );
		pw_printway_backup_progress( 0, 'Processo interrompido e liberado.', 'cancelled' );
		wp_send_json_success( array(
			'message'       => 'Processo interrompido e liberado. Inicie outro backup manualmente quando desejar.',
			'restart_ready' => false,
		) );
	}
	pw_printway_backup_progress( is_array( $status ) ? (int) ( $status['percent'] ?? 0 ) : 0, 'Solicitação de interrupção enviada. Aguarde a etapa atual terminar.', 'cancelling' );
	wp_send_json_success( array( 'message' => 'Solicitação de interrupção enviada. Aguarde alguns segundos.' ) );
}

add_action( 'wp_ajax_pw_printway_backup_create_async', 'pw_printway_backup_ajax_create' );
add_action( 'wp_ajax_pw_printway_backup_package_create', 'pw_printway_backup_ajax_package_create' );
add_action( 'wp_ajax_pw_printway_backup_package_status', 'pw_printway_backup_ajax_package_status' );
add_action( 'wp_ajax_pw_printway_backup_compatibility_start', 'pw_printway_backup_ajax_compatibility_start' );
add_action( 'wp_ajax_pw_printway_backup_compatibility_step', 'pw_printway_backup_ajax_compatibility_step' );
add_action( 'wp_ajax_pw_printway_backup_package_downloaded', 'pw_printway_backup_ajax_package_downloaded' );

function pw_printway_backup_ajax_package_downloaded() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$name = basename( sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ) );
	$file = trailingslashit( pw_printway_backup_directory() ) . $name;
	if ( ! $name || ! in_array( $file, pw_printway_backup_list(), true ) ) { wp_send_json_error( array( 'message' => 'Backup não encontrado.' ), 404 ); }
	$package = pw_printway_backup_package_data( $file );
	$package_path = ! empty( $package['file'] ) ? pw_printway_backup_packages_directory() . basename( sanitize_file_name( $package['file'] ) ) : '';
	if ( 'complete' !== $package['state'] || ! $package_path || ! is_file( $package_path ) ) { wp_send_json_error( array( 'message' => 'ZIP único indisponível.' ), 409 ); }
	$meta = pw_printway_backup_file_meta( $file );
	$meta['package']['downloaded_at'] = time();
	pw_printway_backup_update_meta( $file, $meta );
	wp_send_json_success();
}

function pw_printway_backup_comparisons_directory() {
	$directory = trailingslashit( pw_printway_backup_directory() ) . 'comparisons';
	if ( ! is_dir( $directory ) ) { wp_mkdir_p( $directory ); }
	return trailingslashit( $directory );
}

function pw_printway_backup_compare_path( $entry ) {
	$entry = ltrim( str_replace( '\\', '/', (string) $entry ), '/' );
	$maps = array(
		/* Estrutura usada pelos backups atuais. Os prefixos específicos precisam
		 * vir antes de files/wp-content/ para não serem capturados pelo caminho
		 * genérico. */
		'files/wp-content/plugins/' => array( WP_PLUGIN_DIR . '/', 'files/wp-content/plugins/' ),
		'files/wp-content/themes/'  => array( get_theme_root() . '/', 'files/wp-content/themes/' ),
		'files/wp-content/uploads/' => array( trailingslashit( wp_upload_dir()['basedir'] ), 'files/wp-content/uploads/' ),
		'files/wp-content/'         => array( WP_CONTENT_DIR . '/', 'files/wp-content/' ),
		'files/root/'               => array( ABSPATH, 'files/root/' ),
		'files/config/'             => array( ABSPATH, 'files/config/' ),
		/* Compatibilidade com pacotes criados pelas versões antigas. Todos são
		 * convertidos para a chave canônica atual antes da comparação. */
		'files/plugins/'       => array( WP_PLUGIN_DIR . '/', 'files/wp-content/plugins/' ),
		'files/themes/'        => array( get_theme_root() . '/', 'files/wp-content/themes/' ),
		'files/uploads/'       => array( trailingslashit( wp_upload_dir()['basedir'] ), 'files/wp-content/uploads/' ),
		'files/content_other/' => array( WP_CONTENT_DIR . '/', 'files/wp-content/' ),
	);
	foreach ( $maps as $prefix => $mapping ) {
		if ( 0 === strpos( $entry, $prefix ) ) {
			$relative = substr( $entry, strlen( $prefix ) );
			return array( $mapping[1] . $relative, $mapping[0] . $relative );
		}
	}
	return array( '', '' );
}

function pw_printway_backup_compare_add_difference( &$state, $type, $path, $backup = '', $current = '' ) {
	$state['difference_total'] = absint( $state['difference_total'] ?? 0 ) + 1;
	if ( count( (array) ( $state['differences'] ?? array() ) ) < 500 ) { $state['differences'][] = array( 'type' => $type, 'path' => $path, 'backup' => $backup, 'current' => $current ); }
}

function pw_printway_backup_compare_site_only( &$state, $known_file ) {
	$known = array_flip( file( $known_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: array() );
	$sources = array(
		array( ABSPATH, 'files/root/', array( WP_CONTENT_DIR ) ),
		array( WP_PLUGIN_DIR, 'files/wp-content/plugins/', array() ),
		array( get_theme_root(), 'files/wp-content/themes/', array() ),
		array( wp_upload_dir()['basedir'], 'files/wp-content/uploads/', array( pw_printway_backup_directory() ) ),
		array( WP_CONTENT_DIR, 'files/wp-content/', array( WP_PLUGIN_DIR, get_theme_root(), wp_upload_dir()['basedir'] ) ),
	);
	foreach ( $sources as $source ) {
		if ( ! is_dir( $source[0] ) ) { continue; }
		$base = wp_normalize_path( realpath( $source[0] ) ); $excluded = array_map( 'wp_normalize_path', $source[2] );
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() || $item->isLink() ) { continue; }
			$real = wp_normalize_path( $item->getRealPath() ); $skip = false;
			foreach ( $excluded as $excluded_path ) { if ( $real === $excluded_path || 0 === strpos( $real, trailingslashit( $excluded_path ) ) ) { $skip = true; break; } }
			if ( $skip || ( $source[0] === ABSPATH && in_array( basename( $real ), array( 'wp-config.php', '.htaccess' ), true ) ) ) { continue; }
			$key = $source[1] . ltrim( substr( $real, strlen( $base ) ), '/' );
			if ( ! isset( $known[ $key ] ) ) { pw_printway_backup_compare_add_difference( $state, 'Existe apenas no site atual', $key, '—', size_format( $item->getSize(), 2 ) . ' · ' . wp_date( 'd/m/Y H:i', $item->getMTime(), wp_timezone() ) ); }
		}
	}
}

function pw_printway_backup_ajax_compatibility_start() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$name = basename( sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ) ); $file = trailingslashit( pw_printway_backup_directory() ) . $name;
	if ( ! $name || ! in_array( $file, pw_printway_backup_list(), true ) ) { wp_send_json_error( array( 'message' => 'Backup não encontrado.' ), 404 ); }
	$parts = pw_printway_backup_volume_paths( $file ); $total = 0;
	foreach ( $parts as $part ) { $zip = new ZipArchive(); if ( true === $zip->open( $part ) ) { $total += (int) $zip->numFiles; $zip->close(); } }
	$token = wp_generate_uuid4(); $index = pw_printway_backup_comparisons_directory() . $token . '.paths.txt'; @file_put_contents( $index, '' );
	$state = array( 'token' => $token, 'file' => $name, 'parts' => array_map( 'basename', $parts ), 'part' => 0, 'entry' => 0, 'total' => max( 1, $total ), 'processed' => 0, 'phase' => 'backup', 'current' => 'Lendo arquivos do backup…', 'differences' => array(), 'difference_total' => 0, 'index' => basename( $index ), 'expires' => time() + HOUR_IN_SECONDS );
	set_transient( 'pw_printway_backup_compare_' . $token, $state, HOUR_IN_SECONDS ); wp_send_json_success( $state );
}

function pw_printway_backup_ajax_compatibility_step() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' ); $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	$state = get_transient( 'pw_printway_backup_compare_' . $token ); if ( ! is_array( $state ) ) { wp_send_json_error( array( 'message' => 'A comparação expirou. Inicie novamente.' ), 410 ); }
	$index_path = pw_printway_backup_comparisons_directory() . basename( $state['index'] ?? '' );
	if ( 'backup' === $state['phase'] ) {
		$directory = trailingslashit( pw_printway_backup_directory() ); $limit = 250; $handled = 0;
		while ( $handled < $limit && $state['part'] < count( $state['parts'] ) ) {
			$part_path = $directory . basename( $state['parts'][ $state['part'] ] ); $zip = new ZipArchive();
			if ( true !== $zip->open( $part_path ) ) { pw_printway_backup_compare_add_difference( $state, 'Volume não pode ser aberto', basename( $part_path ) ); $state['part']++; $state['entry'] = 0; continue; }
			while ( $handled < $limit && $state['entry'] < $zip->numFiles ) {
				$stat = $zip->statIndex( $state['entry']++ ); $handled++; $state['processed']++;
				if ( ! is_array( $stat ) ) { continue; } list( $key, $actual ) = pw_printway_backup_compare_path( $stat['name'] ?? '' ); if ( ! $key ) { continue; }
				file_put_contents( $index_path, $key . PHP_EOL, FILE_APPEND | LOCK_EX );
				$backup_info = size_format( (int) ( $stat['size'] ?? 0 ), 2 ) . ' · ' . wp_date( 'd/m/Y H:i', (int) ( $stat['mtime'] ?? 0 ), wp_timezone() );
				if ( ! is_file( $actual ) ) { pw_printway_backup_compare_add_difference( $state, 'Existe apenas no backup', $key, $backup_info, '—' ); continue; }
				$current_info = size_format( filesize( $actual ), 2 ) . ' · ' . wp_date( 'd/m/Y H:i', filemtime( $actual ), wp_timezone() );
				if ( (int) filesize( $actual ) !== (int) ( $stat['size'] ?? 0 ) ) { pw_printway_backup_compare_add_difference( $state, 'Tamanho diferente', $key, $backup_info, $current_info ); }
				else {
					/* A data do arquivo costuma mudar ao copiar, migrar ou restaurar um
					 * WordPress, mesmo quando o conteúdo permanece idêntico. Por isso a
					 * compatibilidade usa o CRC do ZIP, e mantém data/hora apenas como
					 * informação visual. */
					$backup_crc  = strtolower( sprintf( '%08x', (int) ( $stat['crc'] ?? 0 ) ) );
					$current_crc = strtolower( (string) hash_file( 'crc32b', $actual ) );
					if ( $backup_crc !== $current_crc ) {
						pw_printway_backup_compare_add_difference( $state, 'Conteúdo diferente', $key, $backup_info, $current_info );
					}
				}
			}
			if ( $state['entry'] >= $zip->numFiles ) { $state['part']++; $state['entry'] = 0; } $zip->close();
		}
		if ( $state['part'] >= count( $state['parts'] ) ) { $state['phase'] = 'site'; $state['current'] = 'Conferindo arquivos que existem apenas no site atual…'; }
	} elseif ( 'site' === $state['phase'] ) {
		pw_printway_backup_compare_site_only( $state, $index_path ); $state['phase'] = 'complete'; $state['current'] = 'Comparação concluída.';
	}
	$state['percent'] = 'complete' === $state['phase'] ? 100 : ( 'site' === $state['phase'] ? 96 : min( 95, round( 95 * $state['processed'] / $state['total'] ) ) );
	if ( 'complete' === $state['phase'] ) { @unlink( $index_path ); delete_transient( 'pw_printway_backup_compare_' . $token ); } else { set_transient( 'pw_printway_backup_compare_' . $token, $state, HOUR_IN_SECONDS ); }
	wp_send_json_success( $state );
}
function pw_printway_backup_ajax_package_status() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$name = basename( sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ) );
	$file = trailingslashit( pw_printway_backup_directory() ) . $name;
	if ( ! $name || ! in_array( $file, pw_printway_backup_list(), true ) ) { wp_send_json_error( array( 'message' => 'Backup não encontrado.' ), 404 ); }
	$package = pw_printway_backup_package_data( $file );
	$package['percent'] = ! empty( $package['bytes_total'] ) ? min( 100, round( 100 * (float) $package['bytes_done'] / (float) $package['bytes_total'] ) ) : ( 'complete' === $package['state'] ? 100 : 0 );
	if ( 'complete' === $package['state'] ) { $package['download_url'] = pw_printway_backup_download_url( $file, 'package' ); }
	wp_send_json_success( $package );
}

function pw_printway_backup_ajax_package_create() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	$name = basename( sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) ) );
	$file = trailingslashit( pw_printway_backup_directory() ) . $name;
	if ( ! $name || ! in_array( $file, pw_printway_backup_list(), true ) ) { wp_send_json_error( array( 'message' => 'Backup não encontrado.' ), 404 ); }
	if ( ! pw_printway_backup_queue_package( $file ) ) { wp_send_json_error( array( 'message' => 'Não foi possível preparar o ZIP único.' ), 500 ); }
	$package = pw_printway_backup_package_data( $file );
	$package['percent'] = ! empty( $package['bytes_total'] ) ? min( 100, round( 100 * (float) $package['bytes_done'] / (float) $package['bytes_total'] ) ) : 0;
	wp_send_json_success( $package );
}

function pw_printway_backup_ajax_create() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	check_ajax_referer( 'pw_printway_backup_ajax', 'nonce' );
	if ( get_transient( 'pw_printway_backup_lock' ) || get_transient( PW_PRINTWAY_BACKUP_QUEUE ) ) {
		wp_send_json_error( array( 'message' => 'Já existe um backup em andamento.' ), 409 );
	}
	$token = wp_generate_uuid4();
	$kind  = 'manual';
	set_transient( PW_PRINTWAY_BACKUP_QUEUE, array( 'token' => $token, 'kind' => $kind, 'created' => time() ), 10 * MINUTE_IN_SECONDS );
	$GLOBALS['pw_printway_backup_kind'] = $kind;
	pw_printway_backup_progress( 1, 'Backup colocado na fila do servidor…' );
	unset( $GLOBALS['pw_printway_backup_kind'] );
	if ( ! wp_schedule_single_event( time(), PW_PRINTWAY_BACKUP_RUN_HOOK, array( $token, $kind ) ) ) {
		delete_transient( PW_PRINTWAY_BACKUP_QUEUE );
		pw_printway_backup_progress( 0, 'O WordPress não conseguiu agendar o backup em segundo plano.', 'error' );
		wp_send_json_error( array( 'message' => 'O WordPress não conseguiu iniciar o backup em segundo plano.' ), 500 );
	}
	if ( function_exists( 'spawn_cron' ) ) {
		spawn_cron( time() );
	}
	wp_send_json_success( array( 'message' => 'Backup iniciado em segundo plano.', 'queued' => true ) );
}

add_action( 'admin_post_pw_printway_backup_download', 'pw_printway_backup_download' );
function pw_printway_backup_download() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_printway_backup_download' );
	$file  = basename( sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) ) );
	$asset = sanitize_key( wp_unslash( $_GET['asset'] ?? 'zip' ) );
	$path  = '';
	$mime  = 'application/octet-stream';
	foreach ( pw_printway_backup_list() as $backup_file ) {
		if ( 'meta' === $asset && $file === basename( $backup_file ) && is_file( $backup_file . '.json' ) ) {
			$path = $backup_file . '.json';
			$mime = 'application/json';
			break;
		}
		if ( in_array( $asset, array( 'package', 'package_stream' ), true ) && $file === basename( $backup_file ) ) {
			$package = pw_printway_backup_package_data( $backup_file );
			$candidate = ! empty( $package['file'] ) ? pw_printway_backup_packages_directory() . basename( sanitize_file_name( $package['file'] ) ) : '';
			if ( 'complete' === $package['state'] && $candidate && is_file( $candidate ) ) { $path = $candidate; break; }
		}
		if ( 'zip' !== $asset ) { continue; }
		foreach ( pw_printway_backup_volume_paths( $backup_file ) as $volume_path ) {
			if ( $file === basename( $volume_path ) ) { $path = $volume_path; break 2; }
		}
	}
	if ( ! $path || ! is_file( $path ) ) { wp_die( 'Arquivo do conjunto de backup não encontrado.' ); }
	if ( 'package' === $asset ) {
		/* Arquivos únicos podem ter vários GB. Entregá-los pelo PHP pode encerrar a conexão antes do diretório central do ZIP. */
		foreach ( pw_printway_backup_list() as $backup_file ) {
			if ( $file === basename( $backup_file ) ) {
				$meta = pw_printway_backup_file_meta( $backup_file );
				$meta['package']['downloaded_at'] = time();
				pw_printway_backup_update_meta( $backup_file, $meta );
				break;
			}
		}
		$public_url = pw_printway_backup_public_download_url( $path );
		if ( $public_url ) { wp_redirect( $public_url, 302 ); exit; }
	}

	/*
	 * Backups grandes não podem depender de readfile(): em hospedagens compartilhadas
	 * ele pode manter toda a resposta presa no buffer e o navegador recebe
	 * ERR_INVALID_RESPONSE. Enviamos em blocos, sem compressão do PHP, com suporte
	 * a Range para permitir retomada do download pelo navegador.
	 */
	@set_time_limit( 0 );
	@ini_set( 'zlib.output_compression', 'Off' );
	@ini_set( 'output_buffering', 'Off' );
	if ( function_exists( 'apache_setenv' ) ) { @apache_setenv( 'no-gzip', '1' ); }
	while ( ob_get_level() > 0 ) { @ob_end_clean(); }

	$size  = (int) filesize( $path );
	$start = 0;
	$end   = max( 0, $size - 1 );
	$status = 200;
	$range = isset( $_SERVER['HTTP_RANGE'] ) ? trim( (string) $_SERVER['HTTP_RANGE'] ) : '';
	if ( $range && preg_match( '/bytes=(\d*)-(\d*)/i', $range, $matches ) ) {
		if ( '' === $matches[1] && '' !== $matches[2] ) {
			$length = min( $size, absint( $matches[2] ) );
			$start  = max( 0, $size - $length );
		} elseif ( '' !== $matches[1] ) {
			$start = absint( $matches[1] );
			$end   = '' !== $matches[2] ? min( $end, absint( $matches[2] ) ) : $end;
		}
		if ( $start > $end || $start >= $size ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}
		$status = 206;
	}

	$length = $end - $start + 1;
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: ' . $mime );
	header( 'Content-Transfer-Encoding: binary' );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( basename( $path ) ) . '"; filename*=UTF-8\'\'' . rawurlencode( basename( $path ) ) );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Length: ' . $length );
	if ( 206 === $status ) { header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size ); }

	$handle = @fopen( $path, 'rb' );
	if ( ! $handle ) { wp_die( 'Não foi possível abrir o arquivo de backup para download.' ); }
	fseek( $handle, $start );
	$remaining = $length;
	$chunk_size = 4 * MB_IN_BYTES;
	while ( $remaining > 0 && ! feof( $handle ) ) {
		$chunk = fread( $handle, min( $chunk_size, $remaining ) );
		if ( false === $chunk || '' === $chunk ) { break; }
		echo $chunk;
		$remaining -= strlen( $chunk );
		flush();
	}
	fclose( $handle );
	exit;
}

add_action( 'admin_post_pw_printway_backup_delete_selected', 'pw_printway_backup_delete_selected' );
function pw_printway_backup_delete_selected() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_printway_backup_delete_selected' );
	$lock = get_transient( 'pw_printway_backup_lock' );
	if ( $lock ) {
		$status = get_transient( 'pw_printway_backup_progress' );
		if ( pw_printway_backup_status_is_stale( $status ) ) {
			delete_transient( 'pw_printway_backup_lock' );
		} else {
			pw_printway_backup_set_notice( 'Não é possível apagar backups enquanto há um backup em andamento.', 'error' );
			pw_printway_backup_redirect( 'restore' );
		}
	}
	$selected = isset( $_POST['backups'] ) ? (array) wp_unslash( $_POST['backups'] ) : array();
	$selected = array_values( array_unique( array_filter( array_map( 'sanitize_file_name', $selected ) ) ) );
	$available = array_map( 'basename', pw_printway_backup_list() );
	$removed = 0;
	foreach ( $selected as $file ) {
		if ( ! in_array( $file, $available, true ) || 'zip' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) { continue; }
		$path = trailingslashit( pw_printway_backup_directory() ) . $file;
		if ( is_file( $path ) && pw_printway_backup_delete_set( $path ) ) {
			$removed++;
		}
	}
	pw_printway_backup_set_notice( $removed ? $removed . ' backup(s) apagado(s) com sucesso.' : 'Selecione ao menos um backup válido para apagar.', $removed ? 'success' : 'error' );
	pw_printway_backup_redirect( 'restore' );
}

add_action( 'admin_post_pw_printway_backup_restore', 'pw_printway_backup_restore_post' );
function pw_printway_backup_restore_post() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_printway_backup_restore' );
	if ( 'RESTAURAR' !== strtoupper( trim( (string) ( $_POST['confirmation'] ?? '' ) ) ) || empty( $_POST['confirm_restore'] ) ) {
		pw_printway_backup_set_notice( 'Confirme a restauração marcando a caixa e digitando RESTAURAR.', 'error' ); pw_printway_backup_redirect( 'restore' );
	}
	$path = '';
	if ( ! empty( $_FILES['backup_file']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['backup_file']['error'] ) {
		if ( 'zip' !== strtolower( pathinfo( $_FILES['backup_file']['name'], PATHINFO_EXTENSION ) ) || ! is_uploaded_file( $_FILES['backup_file']['tmp_name'] ) ) { pw_printway_backup_set_notice( 'Envie um arquivo ZIP válido.', 'error' ); pw_printway_backup_redirect( 'restore' ); }
		$path = trailingslashit( pw_printway_backup_directory() ) . 'restore-' . wp_generate_uuid4() . '.zip';
		if ( ! move_uploaded_file( $_FILES['backup_file']['tmp_name'], $path ) ) { pw_printway_backup_set_notice( 'Não foi possível guardar o ZIP enviado.', 'error' ); pw_printway_backup_redirect( 'restore' ); }
	} else {
		$file = basename( sanitize_file_name( wp_unslash( $_POST['stored_file'] ?? '' ) ) );
		$path = trailingslashit( pw_printway_backup_directory() ) . $file;
		if ( '' === $file || ! is_file( $path ) ) {
			pw_printway_backup_set_notice( 'Escolha um backup válido já armazenado no servidor ou envie um ZIP externo válido.', 'error' );
			pw_printway_backup_redirect( 'restore' );
		}
	}
	$full_replace = ! empty( $_POST['full_replace'] );
	$allow_foreign_site = ! empty( $_POST['confirm_foreign_site'] );
	$maintenance_file = ABSPATH . '.maintenance';
	$maintenance_enabled = false;
	try {
		/* Antes de alterar qualquer arquivo, confirma assinatura e todos os volumes.
		 * No modo completo, novas visitas ficam em manutenção durante a troca para
		 * não encontrar uma mistura temporária de arquivos antigos e novos. */
		$catalog = pw_printway_backup_restore_catalog( $path );
		$manifest_check = $catalog['manifest'];
		if ( empty( $manifest_check['_pw_signature_valid'] ) && ! $allow_foreign_site ) {
			throw new RuntimeException( 'Este backup foi criado em outro site. Marque a confirmação adicional para continuar.' );
		}
		$selection = pw_printway_backup_sanitize_restore_selection( wp_unslash( $_POST['restore_selection'] ?? '' ), $catalog );
		foreach ( pw_printway_backup_volume_paths( $path ) as $volume_path ) {
			pw_printway_backup_validate_zip_volume( $volume_path );
		}
		if ( $full_replace && ( isset( $manifest_check['components']['core'] ) || isset( $manifest_check['components']['config'] ) || isset( $manifest_check['components']['plugins'] ) ) ) {
			$maintenance = '<?php $upgrading = ' . ( time() + 600 ) . ';';
			$maintenance_enabled = false !== file_put_contents( $maintenance_file, $maintenance, LOCK_EX );
		}
		pw_printway_backup_create( pw_printway_backup_settings(), 'antes-da-restauracao' );
		$manifest = pw_printway_backup_restore( $path, $full_replace, $allow_foreign_site, $selection );
		$labels = array( 'database' => 'banco de dados', 'pages' => 'páginas selecionadas', 'core' => 'arquivos do site', 'plugins' => 'plugins selecionados', 'themes' => 'temas selecionados', 'uploads' => 'mídias e uploads', 'content_other' => 'demais arquivos', 'config' => 'configurações' );
		$restored = array_map( static function( $key ) use ( $labels ) { return $labels[ $key ] ?? $key; }, (array) ( $manifest['_pw_restored_components'] ?? array() ) );
		pw_printway_backup_set_notice( 'Restauração concluída. Um backup de segurança foi criado antes da operação. Itens restaurados: ' . implode( ', ', $restored ) . '.' );
	} catch ( Throwable $error ) { pw_printway_backup_set_notice( 'Restauração interrompida: ' . $error->getMessage(), 'error' ); }
	finally { if ( $maintenance_enabled && is_file( $maintenance_file ) ) { @unlink( $maintenance_file ); } }
	pw_printway_backup_redirect( 'restore' );
}

add_action( 'admin_post_pw_printway_backup_failures_action', 'pw_printway_backup_failures_action' );
function pw_printway_backup_failures_action() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_printway_backup_failures_action' );
	$operation = sanitize_key( $_POST['failure_operation'] ?? '' );
	$selected = isset( $_POST['failures'] ) ? array_values( array_unique( array_map( 'sanitize_file_name', (array) wp_unslash( $_POST['failures'] ) ) ) ) : array();
	$available = array();
	foreach ( pw_printway_backup_failure_files() as $json_file ) { $available[ basename( $json_file, '.json' ) ] = $json_file; }
	$selected = array_values( array_intersect( $selected, array_keys( $available ) ) );
	if ( empty( $selected ) ) { pw_printway_backup_set_notice( 'Selecione ao menos um registro de falha.', 'error' ); pw_printway_backup_redirect( 'failures' ); }
	if ( 'delete' === $operation ) {
		foreach ( $selected as $id ) { @unlink( $available[ $id ] ); @unlink( preg_replace( '/\.json$/', '.log.txt', $available[ $id ] ) ); }
		pw_printway_backup_set_notice( count( $selected ) . ' registro(s) de falha apagado(s).' ); pw_printway_backup_redirect( 'failures' );
	}
	if ( 'send' !== $operation ) { pw_printway_backup_set_notice( 'Operação inválida.', 'error' ); pw_printway_backup_redirect( 'failures' ); }
	$temp = wp_tempnam( 'printway-falhas.zip' );
	$archive = new ZipArchive();
	if ( ! $temp || true !== $archive->open( $temp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { pw_printway_backup_set_notice( 'Não foi possível preparar os logs para envio.', 'error' ); pw_printway_backup_redirect( 'failures' ); }
	$summary = array();
	foreach ( $selected as $id ) {
		$json_file = $available[ $id ]; $log_file = preg_replace( '/\.json$/', '.log.txt', $json_file );
		$data = json_decode( (string) @file_get_contents( $json_file ), true );
		$archive->addFile( $json_file, basename( $json_file ) );
		if ( is_file( $log_file ) ) { $archive->addFile( $log_file, basename( $log_file ) ); }
		$summary[] = '<li><strong>' . esc_html( $id ) . '</strong>: ' . esc_html( (string) ( $data['message'] ?? 'sem mensagem' ) ) . '</li>';
	}
	$archive->close();
	$recipient = 'ro.frezzarin@hotmail.com';
	$subject = '[PrintWay] Logs de falhas do backup para análise — ' . wp_date( 'd/m/Y H:i', time(), wp_timezone() );
	$body = '<h2>Logs do Backup PrintWay</h2><p>Foram selecionados ' . count( $selected ) . ' registro(s) para análise.</p><ul>' . implode( '', $summary ) . '</ul><p>Os detalhes técnicos e logs completos seguem anexados em um arquivo ZIP.</p>';
	$sent = wp_mail( $recipient, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), array( $temp ) );
	@unlink( $temp );
	if ( ! $sent ) { pw_printway_backup_set_notice( 'O WordPress não aceitou o envio dos logs. Os registros não foram marcados como enviados.', 'error' ); pw_printway_backup_redirect( 'failures' ); }
	$sent_at = time();
	foreach ( $selected as $id ) {
		$json_file = $available[ $id ]; $data = json_decode( (string) @file_get_contents( $json_file ), true );
		if ( ! is_array( $data ) ) { continue; }
		$data['sent_at'] = $sent_at; $data['sent_to'] = $recipient; $data['sent_count'] = absint( $data['sent_count'] ?? 0 ) + 1;
		file_put_contents( $json_file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
	pw_printway_backup_set_notice( count( $selected ) . ' registro(s) enviado(s) para análise em ' . $recipient . '.' ); pw_printway_backup_redirect( 'failures' );
}

function pw_printway_backup_failures_page() {
	$files = pw_printway_backup_failure_files();
	echo '<h2>Falhas, travamentos e cancelamentos</h2><p>Esta aba mantém registros técnicos separados das execuções que não foram concluídas. Selecione os itens para apagar ou enviar os logs anexados para análise.</p>';
	if ( empty( $files ) ) { echo '<div class="notice notice-success inline"><p>Nenhuma falha registrada.</p></div>'; return; }
	echo '<style>.pw-failure-actions{display:flex;gap:8px;align-items:center;margin:14px 0}.pw-failure-modal{display:none;position:fixed;inset:0;z-index:100200;padding:24px;background:rgba(0,0,0,.58);align-items:center;justify-content:center}.pw-failure-modal.is-open{display:flex}.pw-failure-panel{width:min(900px,96vw);max-height:88vh;overflow:auto;padding:20px;border-radius:12px;background:#fff;box-shadow:0 20px 70px rgba(0,0,0,.4)}.pw-failure-panel__head{display:flex;justify-content:space-between;align-items:center}.pw-failure-close{border:0;background:transparent;font-size:26px;cursor:pointer}.pw-failure-log{width:100%;min-height:300px;box-sizing:border-box;font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace}.pw-failure-sent{color:#007017;font-weight:600}.pw-failure-pending{color:#8a5500}</style>';
	echo '<form id="pw-failures-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pw_printway_backup_failures_action">'; wp_nonce_field( 'pw_printway_backup_failures_action' );
	echo '<div class="pw-failure-actions"><button class="button button-primary" name="failure_operation" value="send" disabled id="pw-failure-send">Enviar selecionados para análise</button><button class="button" name="failure_operation" value="delete" disabled id="pw-failure-delete">Apagar selecionados</button><span class="description">Destino: ro.frezzarin@hotmail.com</span></div><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="pw-failure-all"></td><th>Data e hora</th><th>Tipo</th><th>Etapa</th><th>Progresso</th><th>Mensagem</th><th>Envio para análise</th><th>Detalhes</th></tr></thead><tbody>';
	$modals = '';
	foreach ( $files as $json_file ) {
		$data = json_decode( (string) @file_get_contents( $json_file ), true ); if ( ! is_array( $data ) ) { continue; }
		$id = basename( $json_file, '.json' ); $log_file = preg_replace( '/\.json$/', '.log.txt', $json_file ); $log = is_file( $log_file ) ? (string) @file_get_contents( $log_file ) : 'Log textual não disponível.';
		$sent = ! empty( $data['sent_at'] ) ? 'Enviado em ' . wp_date( 'd/m/Y H:i', absint( $data['sent_at'] ), wp_timezone() ) : 'Não enviado';
		echo '<tr><th class="check-column"><input class="pw-failure-item" type="checkbox" name="failures[]" value="' . esc_attr( $id ) . '"></th><td>' . esc_html( wp_date( 'd/m/Y H:i:s', absint( $data['created_at'] ?? 0 ), wp_timezone() ) ) . '</td><td>' . esc_html( 'cancelled' === ( $data['state'] ?? '' ) ? 'Cancelamento' : 'Falha' ) . '</td><td>' . esc_html( $data['phase'] ?: 'não informada' ) . '</td><td>' . esc_html( absint( $data['percent'] ?? 0 ) . '%' ) . '</td><td>' . esc_html( $data['message'] ?? '' ) . '</td><td class="' . ( ! empty( $data['sent_at'] ) ? 'pw-failure-sent' : 'pw-failure-pending' ) . '">' . esc_html( $sent ) . '</td><td><button type="button" class="button pw-failure-open" data-modal="modal-' . esc_attr( $id ) . '">Ver detalhes</button></td></tr>';
		$technical = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$modals .= '<div class="pw-failure-modal" id="modal-' . esc_attr( $id ) . '"><div class="pw-failure-panel"><div class="pw-failure-panel__head"><h2>Detalhes — ' . esc_html( $id ) . '</h2><button type="button" class="pw-failure-close">&times;</button></div><p><strong>Mensagem:</strong> ' . esc_html( $data['message'] ?? '' ) . '<br><strong>Item atual:</strong> ' . esc_html( $data['current'] ?? '' ) . '<br><strong>Duração:</strong> ' . esc_html( pw_printway_backup_duration_label( $data['duration'] ?? 0 ) ) . '<br><strong>Arquivos ZIP:</strong> ' . esc_html( number_format_i18n( absint( $data['zip_files'] ?? 0 ) ) ) . ' — ' . esc_html( size_format( absint( $data['zip_bytes'] ?? 0 ), 2 ) ) . '<br><strong>PHP / WordPress:</strong> ' . esc_html( ( $data['php_version'] ?? '' ) . ' / ' . ( $data['wordpress_version'] ?? '' ) ) . '</p><h3>Dados técnicos</h3><textarea class="pw-failure-log" readonly>' . esc_textarea( $technical ) . '</textarea><h3>Log completo</h3><textarea class="pw-failure-log" readonly>' . esc_textarea( $log ) . '</textarea><p><button type="button" class="button pw-failure-copy">Copiar log</button></p></div></div>';
	}
	echo '</tbody></table></form>' . $modals;
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-failures-form"),all=document.getElementById("pw-failure-all"),items=Array.from(form.querySelectorAll(".pw-failure-item")),send=document.getElementById("pw-failure-send"),remove=document.getElementById("pw-failure-delete");function sync(){var count=items.filter(function(item){return item.checked;}).length;send.disabled=!count;remove.disabled=!count;all.checked=count===items.length;all.indeterminate=count>0&&count<items.length;}all.addEventListener("change",function(){items.forEach(function(item){item.checked=all.checked;});sync();});items.forEach(function(item){item.addEventListener("change",sync);});remove.addEventListener("click",function(event){if(!window.confirm("Apagar definitivamente os registros selecionados?")){event.preventDefault();}});document.querySelectorAll(".pw-failure-open").forEach(function(button){button.addEventListener("click",function(){document.getElementById(button.dataset.modal).classList.add("is-open");});});document.querySelectorAll(".pw-failure-modal").forEach(function(modal){modal.addEventListener("click",function(event){if(event.target===modal||event.target.closest(".pw-failure-close")){modal.classList.remove("is-open");}});modal.querySelector(".pw-failure-copy").addEventListener("click",function(){var area=modal.querySelectorAll(".pw-failure-log")[1];area.select();if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(area.value);}else{document.execCommand("copy");}});});sync();});</script>';
}

function pw_printway_backup_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab = in_array( $_GET['tab'] ?? '', array( 'config', 'backup', 'restore', 'failures' ), true ) ? sanitize_key( $_GET['tab'] ) : 'config';
	$notice = get_transient( 'pw_printway_backup_notice_' . get_current_user_id() ); delete_transient( 'pw_printway_backup_notice_' . get_current_user_id() );
	$base = admin_url( 'admin.php?page=pw-printway-backup' ); $settings = pw_printway_backup_settings();
	$restore_count = count( pw_printway_backup_list() ); $failure_count = count( pw_printway_backup_failure_files() );
	$next_scheduled = wp_next_scheduled( PW_PRINTWAY_BACKUP_HOOK );
	$automatic_schedules = array( 'hourly', 'pw_12_hours', 'daily', 'weekly', 'pw_monthly' );
	$backup_overdue = in_array( $settings['schedule'], $automatic_schedules, true ) && $next_scheduled && $next_scheduled < time() - 5 * MINUTE_IN_SECONDS;
	echo '<div class="wrap pw-printway-backup"><h1>Backup e restauração</h1><p>Proteção completa do WordPress: arquivos, banco de dados, plugins, temas e mídias.</p>';
	if ( $notice ) { echo '<div class="notice notice-' . esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; }
	echo '<h2 class="nav-tab-wrapper"><a class="nav-tab ' . ( 'config' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'config', $base ) ) . '">Configuração</a><a class="nav-tab ' . ( 'backup' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'backup', $base ) ) . '">Backup' . ( $backup_overdue ? ' (!)' : '' ) . '</a><a id="pw-backup-restore-tab" class="nav-tab ' . ( 'restore' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'restore', $base ) ) . '">Restauração (' . esc_html( $restore_count ) . ')</a><a class="nav-tab ' . ( 'failures' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', 'failures', $base ) ) . '">Falhas (' . esc_html( $failure_count ) . ')</a></h2>';
	if ( 'config' === $tab ) {
		echo '<style>.pw-backup-component-row td{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.pw-backup-count{color:#646970}.pw-backup-modal{display:none;position:fixed;inset:0;z-index:100100;background:rgba(0,0,0,.56);align-items:center;justify-content:center;padding:24px}.pw-backup-modal.is-open{display:flex}.pw-backup-modal__panel{width:min(720px,96vw);max-height:84vh;display:flex;flex-direction:column;background:#fff;border-radius:12px;box-shadow:0 22px 70px rgba(0,0,0,.35);overflow:hidden}.pw-backup-modal__header,.pw-backup-modal__footer{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:15px;border-bottom:1px solid #ddd}.pw-backup-modal__footer{border-top:1px solid #ddd;border-bottom:0;justify-content:flex-end}.pw-backup-modal__body{padding:16px 20px;overflow:auto}.pw-backup-modal__items{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px;margin-top:14px}.pw-backup-modal__item{display:flex;align-items:flex-start;gap:8px;padding:7px;border-radius:6px}.pw-backup-modal__item:hover{background:#f6f7f7}.pw-backup-modal__close{border:0;background:transparent;font-size:24px;cursor:pointer}@media(max-width:700px){.pw-backup-modal__items{grid-template-columns:1fr}}</style>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pw_printway_backup_save">'; wp_nonce_field( 'pw_printway_backup_save' );
		echo '<h2>O que incluir no backup</h2><p>Marque uma categoria e clique em <strong>Escolher itens</strong> para selecionar seu conteúdo individualmente.</p><table class="form-table"><tbody>';
		foreach ( pw_printway_backup_components() as $key => $label ) {
			$items = pw_printway_backup_component_items( $key );
			$saved = pw_printway_backup_selected_items( $settings, $key );
			$chosen = null === $saved ? array_keys( $items ) : array_values( array_intersect( array_keys( $items ), $saved ) );
			$modal_id = 'pw-backup-modal-' . sanitize_html_class( $key );
			echo '<tr class="pw-backup-component-row"><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="components[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . '> Incluir categoria</label><button type="button" class="button pw-backup-open" data-modal="' . esc_attr( $modal_id ) . '" ' . disabled( empty( $items ), true, false ) . '>Escolher itens</button><span class="pw-backup-count" data-count-for="' . esc_attr( $key ) . '">' . esc_html( count( $chosen ) . ' de ' . count( $items ) . ' selecionados' ) . '</span>';
			echo '<div class="pw-backup-modal" id="' . esc_attr( $modal_id ) . '" role="dialog" aria-modal="true" aria-label="Itens de ' . esc_attr( $label ) . '"><div class="pw-backup-modal__panel"><div class="pw-backup-modal__header"><div><strong>' . esc_html( $label ) . '</strong><div class="description">Marque somente o que deseja incluir no backup.</div></div><button type="button" class="pw-backup-modal__close" aria-label="Fechar">&times;</button></div><div class="pw-backup-modal__body"><label><input type="checkbox" class="pw-backup-select-all" ' . checked( count( $chosen ) === count( $items ) && ! empty( $items ), true, false ) . '> Selecionar todos</label><div class="pw-backup-modal__items">';
			foreach ( $items as $item_key => $item_label ) {
				echo '<label class="pw-backup-modal__item"><input class="pw-backup-item" data-component="' . esc_attr( $key ) . '" type="checkbox" name="selection[' . esc_attr( $key ) . '][]" value="' . esc_attr( $item_key ) . '" ' . checked( in_array( $item_key, $chosen, true ), true, false ) . '> <span>' . esc_html( $item_label ) . '</span></label>';
			}
			echo '</div></div><div class="pw-backup-modal__footer"><button type="button" class="button button-primary pw-backup-modal__done">Concluir seleção</button></div></div></div></td></tr>';
		}
		$stored_files = pw_printway_backup_list(); $stored_size = array_sum( array_map( 'filesize', $stored_files ) );
		echo '<tr><th>Tamanho máximo de cada volume ZIP</th><td><input type="number" name="zip_volume_size_mb" min="0" max="8192" step="16" value="' . esc_attr( $settings['zip_volume_size_mb'] ) . '"> MB<p class="description">Recomendado: 128 a 256 MB. Valores menores geram mais volumes e evitam que o servidor precise fechar um ZIP gigantesco. Use 0 para limitar somente pela proteção automática de quantidade de arquivos.</p></td></tr>';
		echo '<tr><th>Backup automático</th><td><select name="schedule"><option value="manual" ' . selected( $settings['schedule'], 'manual', false ) . '>Somente manual</option><option value="hourly" ' . selected( $settings['schedule'], 'hourly', false ) . '>A cada 1 hora</option><option value="pw_12_hours" ' . selected( $settings['schedule'], 'pw_12_hours', false ) . '>A cada 12 horas</option><option value="daily" ' . selected( $settings['schedule'], 'daily', false ) . '>Diário</option><option value="weekly" ' . selected( $settings['schedule'], 'weekly', false ) . '>Semanal</option><option value="pw_monthly" ' . selected( $settings['schedule'], 'pw_monthly', false ) . '>Mensal</option></select><p class="description">O agendamento depende das visitas ao site, como todo Cron do WordPress.</p></td></tr><tr><th>Métodos de armazenamento</th><td><p><strong>Uso atual:</strong> ' . esc_html( count( $stored_files ) ) . ' backup(s) concluído(s), ocupando ' . esc_html( size_format( $stored_size, 2 ) ) . '.</p><fieldset><label><input type="checkbox" name="retention_rules[]" value="count" ' . checked( in_array( 'count', (array) $settings['retention_rules'], true ), true, false ) . '> Limitar quantidade de backups</label><br><input type="number" name="retention" min="1" max="100" value="' . esc_attr( $settings['retention'] ) . '"> backups concluídos</fieldset><fieldset style="margin-top:12px"><label><input type="checkbox" name="retention_rules[]" value="size" ' . checked( in_array( 'size', (array) $settings['retention_rules'], true ), true, false ) . '> Limitar espaço ocupado no servidor</label><br><input type="number" name="retention_size_gb" min="0" max="100000" step="0.1" value="' . esc_attr( $settings['retention_size_gb'] ) . '"> GB <span class="description">(0 desativa este limite)</span></fieldset><fieldset style="margin-top:12px"><label><input type="checkbox" name="retention_rules[]" value="age" ' . checked( in_array( 'age', (array) $settings['retention_rules'], true ), true, false ) . '> Apagar backups antigos por data</label><br><input type="number" name="retention_age_days" min="0" max="36500" value="' . esc_attr( $settings['retention_age_days'] ) . '"> dias <span class="description">(0 desativa este limite)</span></fieldset><p style="margin-top:14px"><label><strong>Proteção mínima:</strong> manter pelo menos <input type="number" name="retention_minimum" min="1" max="100" value="' . esc_attr( $settings['retention_minimum'] ) . '" style="width:72px"> backup(s) concluído(s), mesmo quando outro limite for atingido.</label></p><p class="description">As regras selecionadas trabalham juntas. O sistema sempre remove primeiro os backups mais antigos e nunca oferece para restauração um ZIP ainda em andamento.</p></td></tr></tbody></table><p><button class="button button-primary">Salvar configuração</button></p></form>';
		echo '<p class="description" style="margin-top:22px">Versão atual do plugin PrintWay: <strong>' . esc_html( pw_printway_backup_plugin_version() ) . '</strong></p>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){function refresh(modal){var items=Array.from(modal.querySelectorAll(".pw-backup-item")),checked=items.filter(function(item){return item.checked;}).length,key=items[0]&&items[0].dataset.component,count=document.querySelector("[data-count-for=\""+key+"\"]"),all=modal.querySelector(".pw-backup-select-all");if(count){count.textContent=checked+" de "+items.length+" selecionados";}if(all){all.checked=items.length>0&&checked===items.length;all.indeterminate=checked>0&&checked<items.length;}}document.querySelectorAll(".pw-backup-open").forEach(function(button){button.addEventListener("click",function(){var modal=document.getElementById(button.dataset.modal);if(modal){modal.classList.add("is-open");refresh(modal);}});});document.querySelectorAll(".pw-backup-modal").forEach(function(modal){modal.querySelectorAll(".pw-backup-modal__close,.pw-backup-modal__done").forEach(function(button){button.addEventListener("click",function(){refresh(modal);modal.classList.remove("is-open");});});modal.addEventListener("click",function(event){if(event.target===modal){refresh(modal);modal.classList.remove("is-open");}});var all=modal.querySelector(".pw-backup-select-all");if(all){all.addEventListener("change",function(){modal.querySelectorAll(".pw-backup-item").forEach(function(item){item.checked=all.checked;});refresh(modal);});}modal.querySelectorAll(".pw-backup-item").forEach(function(item){item.addEventListener("change",function(){refresh(modal);});});refresh(modal);});document.addEventListener("keydown",function(event){if(event.key==="Escape"){document.querySelectorAll(".pw-backup-modal.is-open").forEach(function(modal){refresh(modal);modal.classList.remove("is-open");});}});});</script>';
	} elseif ( 'backup' === $tab ) {
		$ajax_nonce = wp_create_nonce( 'pw_printway_backup_ajax' );
		$initial_status = get_transient( 'pw_printway_backup_progress' );
		$initial_lock   = get_transient( 'pw_printway_backup_lock' );
		$initial_queue  = get_transient( PW_PRINTWAY_BACKUP_QUEUE );
		$initial_active = (bool) ( $initial_lock || $initial_queue || ( is_array( $initial_status ) && in_array( $initial_status['state'] ?? '', array( 'running', 'cancelling' ), true ) ) );
		if ( ! is_array( $initial_status ) ) {
			$initial_status = array( 'percent' => 0, 'message' => 'Pronto para iniciar.', 'state' => 'idle', 'updated' => time() );
		}
		$initial_status['locked'] = $initial_active;
		$initial_status['queued'] = (bool) $initial_queue;
		$initial_status['restore_count'] = count( pw_printway_backup_list() );
		echo '<script>(function(){if(window.pwBackupStatusFetchOptimized){return;}window.pwBackupStatusFetchOptimized=true;window.pwBackupPollingEnabled=' . ( $initial_active ? 'true' : 'false' ) . ';window.pwBackupInitialStatus=' . wp_json_encode( $initial_status ) . ';var originalFetch=window.fetch.bind(window),inFlight=null,cachedResponse=null,cachedAt=0,minimumInterval=2500;function localStatus(){return Promise.resolve(new Response(JSON.stringify({success:true,data:window.pwBackupInitialStatus||{percent:0,message:"Pronto para iniciar.",state:"idle"}}),{headers:{"Content-Type":"application/json"}}));}function reset(){cachedResponse=null;cachedAt=0;window.pwBackupPollingEnabled=true;}window.fetch=function(input,options){var action="";try{var body=options&&options.body;action=body&&typeof body.get==="function"?String(body.get("action")||""):"";}catch(error){}if(action!=="pw_printway_backup_status"){return originalFetch(input,options);}if(!window.pwBackupPollingEnabled){return localStatus();}var now=Date.now();if(cachedResponse&&now-cachedAt<minimumInterval){return Promise.resolve(cachedResponse.clone());}if(inFlight){return inFlight.then(function(response){return response.clone();});}var controller=typeof AbortController!=="undefined"?new AbortController():null,requestOptions=options,timeout;if(controller){requestOptions=Object.assign({},options||{},{signal:controller.signal});timeout=setTimeout(function(){controller.abort();},12000);}inFlight=originalFetch(input,requestOptions).then(function(response){cachedResponse=response.clone();cachedAt=Date.now();return response;}).finally(function(){if(timeout){clearTimeout(timeout);}inFlight=null;});return inFlight.then(function(response){return response.clone();});};document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-create-form"),cancel=document.getElementById("pw-backup-cancel");if(form){form.addEventListener("submit",reset,true);}if(cancel){cancel.addEventListener("click",reset,true);}});})();</script>';
		$latest_backups = pw_printway_backup_list();
		if ( ! empty( $latest_backups ) ) {
			$latest_file = $latest_backups[0];
			$latest_meta = pw_printway_backup_file_meta( $latest_file );
			echo '<div id="pw-backup-latest-notice" class="notice notice-info inline"><p><strong>Último backup:</strong> <span id="pw-backup-latest-value">' . esc_html( wp_date( 'd/m/Y H:i', filemtime( $latest_file ), pw_printway_backup_timezone() ) ) . ' — ' . esc_html( pw_printway_backup_kind_label( $latest_meta['kind'] ) ) . ' — duração: ' . esc_html( pw_printway_backup_duration_label( $latest_meta['duration'] ) ) . '.</span></p></div>';
		} else {
			echo '<div id="pw-backup-latest-notice" class="notice notice-warning inline"><p><strong>Ainda não há backup concluído.</strong> Crie o primeiro backup para proteger o site.</p></div>';
		}
		$automatic_schedules = array( 'hourly', 'pw_12_hours', 'daily', 'weekly', 'pw_monthly' );
		$next_automatic = wp_next_scheduled( PW_PRINTWAY_BACKUP_HOOK );
		if ( in_array( $settings['schedule'], $automatic_schedules, true ) && $next_automatic ) {
			$last_visit = absint( get_option( PW_PRINTWAY_BACKUP_LAST_VISIT_OPTION, 0 ) );
			echo '<div id="pw-backup-auto-schedule" class="notice notice-info inline" data-next="' . esc_attr( $next_automatic ) . '" data-last-visit="' . esc_attr( $last_visit ) . '"><p><strong>Próximo backup automático:</strong> <span id="pw-backup-next-date">' . esc_html( wp_date( 'd/m/Y H:i', $next_automatic, wp_timezone() ) ) . '</span> <strong id="pw-backup-next-countdown"></strong>. Ele será iniciado na primeira visita ao site após esse horário. <span id="pw-backup-last-visit"></span></p></div>';
			echo '<script>document.addEventListener("DOMContentLoaded",function(){var box=document.getElementById("pw-backup-auto-schedule"),date=document.getElementById("pw-backup-next-date"),countdown=document.getElementById("pw-backup-next-countdown"),visit=document.getElementById("pw-backup-last-visit"),nonce=' . wp_json_encode( $ajax_nonce ) . ',next=Number(box&&box.dataset.next||0),lastVisit=Number(box&&box.dataset.lastVisit||0),serverOffset=0;function pad(value){return String(value).padStart(2,"0");}function dateTime(timestamp){if(!timestamp){return "não registrada";}var value=new Date(timestamp*1000);return pad(value.getDate())+"/"+pad(value.getMonth()+1)+"/"+value.getFullYear()+" às "+pad(value.getHours())+":"+pad(value.getMinutes());}function render(){if(!box){return;}var now=Math.floor(Date.now()/1000)+serverOffset,difference=Math.max(0,next-now),days=Math.floor(difference/86400),hours=Math.floor((difference%86400)/3600),minutes=Math.floor((difference%3600)/60);date.textContent=next?dateTime(next).replace(" às "," "):"aguardando programação";var parts=[];if(days){parts.push(days+" dia"+(days===1?"":"s"));}if(hours){parts.push(hours+" hora"+(hours===1?"":"s"));}if(minutes){parts.push(minutes+" minuto"+(minutes===1?"":"s"));}if(!parts.length){parts.push("menos de 1 minuto");}var remainingText=parts.length>1?parts.slice(0,-1).join(", ")+" e "+parts[parts.length-1]:parts[0];countdown.textContent=next?"(faltam "+remainingText+")":"";visit.textContent=lastVisit?"(última visita ao site em "+dateTime(lastVisit)+")":"(nenhuma visita pública registrada desde a ativação deste controle)";}render();setInterval(render,1000);});</script>';
		} elseif ( in_array( $settings['schedule'], $automatic_schedules, true ) ) {
			echo '<div class="notice notice-warning inline"><p><strong>Backup automático ativo, aguardando programação.</strong> Atualize esta página em alguns instantes.</p></div>';
		} else {
			echo '<div class="notice notice-info inline"><p><strong>Backup automático desativado.</strong> Ative um intervalo na aba Configuração para programar o próximo backup.</p></div>';
		}
		echo '<style>.pw-backup-progress~table.widefat,.pw-backup-progress~hr,.pw-backup-progress~hr+h2{display:none}</style>';
		echo '<p id="pw-backup-run-kind" class="description">Verificando execução atual…</p><script>document.addEventListener("DOMContentLoaded",function(){var target=document.getElementById("pw-backup-run-kind"),nonce=' . wp_json_encode( $ajax_nonce ) . ';function update(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_status",nonce:nonce})}).then(function(response){return response.json();}).then(function(result){var data=result.success?(result.data||{}):{},running=data.state==="running"||data.state==="cancelling",kind=data.kind==="automatico"?"automático":"manual";target.textContent=running?"Backup "+kind+" em andamento — acompanhe o progresso abaixo.":"Nenhum backup em andamento.";}).catch(function(){target.textContent="Não foi possível consultar a execução atual.";});}update();setInterval(update,3000);});</script>';
		echo '<style>.pw-backup-progress{max-width:980px;margin:18px 0;padding:18px;border:1px solid #c3c4c7;border-radius:10px;background:#fff}.pw-backup-progress__track{height:14px;margin:12px 0;border-radius:99px;overflow:hidden;background:#e9ecef}.pw-backup-progress__bar{height:100%;width:0;background:linear-gradient(90deg,#2271b1,#00a32a);transition:width .35s ease}.pw-backup-progress__info{display:flex;justify-content:space-between;gap:12px;align-items:center}.pw-backup-health{margin:12px 0;padding:10px 12px;border-left:4px solid;border-radius:6px;background:#f6f7f7}.pw-backup-health.is-normal{border-color:#00a32a;background:#edfaef;color:#075b18}.pw-backup-health.is-warning{border-color:#dba617;background:#fff8e5;color:#725500}.pw-backup-health.is-critical{border-color:#d63638;background:#fcf0f1;color:#8a1113}.pw-backup-health small{display:block;margin-top:3px}.pw-backup-progress__times{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:14px 0}.pw-backup-progress__time{padding:10px;border-radius:7px;background:#f6f7f7}.pw-backup-progress__time small{display:block;color:#646970}.pw-backup-progress__current{padding:10px 12px;border-left:3px solid #2271b1;background:#f6f7f7;word-break:break-word}.pw-backup-progress__current small,.pw-backup-progress__zip small{display:block;margin-bottom:6px;color:#50575e}.pw-backup-progress__current strong,.pw-backup-progress__zip strong{display:block;line-height:1.45}.pw-backup-progress__zip{margin-top:8px;padding:10px 12px;border-left:3px solid #00a32a;background:#f0f8f1}.pw-backup-progress__technical{display:block;margin-top:6px;color:#3c434a;line-height:1.45}.pw-backup-progress__log-details{margin-top:12px}.pw-backup-progress__log-details>summary{display:inline-block;cursor:pointer;color:#2271b1;font-weight:600}.pw-backup-progress__log{width:100%;min-height:185px;box-sizing:border-box;margin-top:10px;padding:10px;font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace;resize:vertical}.pw-backup-progress__actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}@media(max-width:680px){.pw-backup-progress__times{grid-template-columns:1fr}}</style>';
		echo '<h2>Criar novo backup</h2><p>O arquivo ficará guardado no servidor e poderá ser baixado imediatamente ou depois.</p><div id="pw-backup-progress" class="pw-backup-progress" aria-live="polite"><div class="pw-backup-progress__info"><strong id="pw-backup-progress-message">Pronto para iniciar.</strong><span id="pw-backup-progress-number">0%</span></div><div class="pw-backup-progress__track"><div id="pw-backup-progress-bar" class="pw-backup-progress__bar"></div></div><div id="pw-backup-health" class="pw-backup-health is-normal"><strong id="pw-backup-health-title">Status normal</strong><small id="pw-backup-health-detail">Aguardando início.</small></div><div class="pw-backup-progress__times"><div class="pw-backup-progress__time"><small>Início</small><strong id="pw-backup-start">—</strong></div><div class="pw-backup-progress__time"><small>Tempo decorrido</small><strong id="pw-backup-elapsed">—</strong></div><div class="pw-backup-progress__time"><small>Tempo restante estimado</small><strong id="pw-backup-eta">—</strong></div></div><div class="pw-backup-progress__current"><small>Arquivo ou item atual</small><strong id="pw-backup-current">Aguardando início.</strong></div><div class="pw-backup-progress__zip"><small>Detalhes técnicos do ZIP</small><strong id="pw-backup-zip-details">—</strong><span id="pw-backup-technical" class="pw-backup-progress__technical">Os detalhes aparecem durante a execução do backup.</span></div><details id="pw-backup-log-details" class="pw-backup-progress__log-details"><summary>Exibir log</summary><textarea id="pw-backup-log" class="pw-backup-progress__log" readonly spellcheck="false">Aguardando início.</textarea></details><div class="pw-backup-progress__actions"><button type="button" id="pw-backup-copy-log" class="button" disabled>Copiar log</button><button type="button" id="pw-backup-cancel" class="button" style="display:none">Interromper backup</button></div></div><script>document.addEventListener("DOMContentLoaded",function(){var details=document.getElementById("pw-backup-log-details"),summary=details&&details.querySelector("summary");if(!details||!summary){return;}function sync(){summary.textContent=details.open?"Ocultar log":"Exibir log";}details.addEventListener("toggle",sync);sync();});</script>';
		echo '<form id="pw-backup-create-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pw_printway_backup_create">'; wp_nonce_field( 'pw_printway_backup_create' ); echo '<button id="pw-backup-create" class="button button-primary button-hero">Criar backup agora</button></form>';
		echo '<style>.pw-backup-start-notice{position:fixed;top:54px;left:50%;z-index:100200;width:min(560px,calc(100vw - 32px));padding:16px 20px;box-sizing:border-box;border-left:5px solid #2271b1;border-radius:10px;background:#fff;color:#1d2327;box-shadow:0 14px 44px rgba(0,0,0,.28);transform:translate(-50%,-18px);opacity:0;pointer-events:none;transition:opacity .25s ease,transform .25s ease}.pw-backup-start-notice.is-visible{transform:translate(-50%,0);opacity:1}.pw-backup-start-notice strong{display:block;margin-bottom:4px}</style><div id="pw-backup-start-notice" class="pw-backup-start-notice" role="status" aria-live="polite"><strong>Backup iniciado</strong>Você pode fechar esta página. O backup continuará sendo processado pelo servidor.</div><script>document.addEventListener("DOMContentLoaded",function(){var form=document.getElementById("pw-backup-create-form"),notice=document.getElementById("pw-backup-start-notice"),timer;if(!form||!notice){return;}form.addEventListener("submit",function(){clearTimeout(timer);notice.classList.add("is-visible");timer=setTimeout(function(){notice.classList.remove("is-visible");},5000);});});</script>';
		echo '<style>.pw-backup-item-progress{margin:8px 0 14px;padding:11px 12px;border:1px solid #c3c4c7;border-radius:8px;background:#f8f9fa}.pw-backup-item-progress__head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end}.pw-backup-item-progress__title small{display:block;margin-bottom:4px;color:#646970}.pw-backup-item-progress__title strong{display:block;word-break:break-word}.pw-backup-item-progress__number{font-weight:700;color:#2271b1}.pw-backup-item-progress__track{height:9px;margin:9px 0 5px;overflow:hidden;border-radius:99px;background:#dcdcde}.pw-backup-item-progress__bar{height:100%;width:0;background:#2271b1;transition:width .3s ease}.pw-backup-item-progress__bar.is-indeterminate{width:34%;animation:pw-backup-item-moving 1.25s ease-in-out infinite}.pw-backup-item-progress__detail{color:#50575e}@keyframes pw-backup-item-moving{0%{transform:translateX(-110%)}100%{transform:translateX(300%)}}</style>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var mainTrack=document.querySelector(".pw-backup-progress__track"),old=document.querySelector(".pw-backup-progress__current"),current=document.getElementById("pw-backup-current"),nonce=' . wp_json_encode( $ajax_nonce ) . ';if(!mainTrack||!old||!current){return;}var card=document.createElement("div");card.className="pw-backup-item-progress";card.innerHTML="<div class=\"pw-backup-item-progress__head\"><div class=\"pw-backup-item-progress__title\"><small>Arquivo ou item atual</small><div id=\"pw-backup-item-title\"></div></div><span id=\"pw-backup-item-number\" class=\"pw-backup-item-progress__number\">—</span></div><div class=\"pw-backup-item-progress__track\"><div id=\"pw-backup-item-bar\" class=\"pw-backup-item-progress__bar\"></div></div><small id=\"pw-backup-item-detail\" class=\"pw-backup-item-progress__detail\">Aguardando início.</small>";mainTrack.insertAdjacentElement("afterend",card);card.querySelector("#pw-backup-item-title").appendChild(current);old.remove();var bar=card.querySelector("#pw-backup-item-bar"),number=card.querySelector("#pw-backup-item-number"),detail=card.querySelector("#pw-backup-item-detail");function render(data){var running=data.state==="running"||data.state==="cancelling",percent=Number(data.current_percent),done=Number(data.current_done||0),total=Number(data.current_total||0),unit=data.current_unit||"itens";bar.classList.remove("is-indeterminate");if(running&&percent>=0&&total>0){bar.style.width=Math.max(0,Math.min(100,percent))+"%";number.textContent=percent+"%";detail.textContent=done.toLocaleString("pt-BR")+" de aproximadamente "+total.toLocaleString("pt-BR")+" "+unit+" processados.";}else if(running){bar.style.width="34%";bar.classList.add("is-indeterminate");number.textContent="em andamento";detail.textContent="Este item não informa um total mensurável; a animação indica que o processamento continua.";}else{bar.style.width="0";number.textContent="—";detail.textContent=data.state==="complete"?"Aguardando novo backup.":"Aguardando início.";}}function poll(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_status",nonce:nonce})}).then(function(response){return response.json();}).then(function(result){if(result.success){render(result.data||{});}}).catch(function(){});}poll();setInterval(poll,1200);});</script>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( $ajax_nonce ) . ',bar=document.getElementById("pw-backup-progress-bar"),message=document.getElementById("pw-backup-progress-message"),number=document.getElementById("pw-backup-progress-number"),start=document.getElementById("pw-backup-start"),elapsed=document.getElementById("pw-backup-elapsed"),eta=document.getElementById("pw-backup-eta"),current=document.getElementById("pw-backup-current"),zipDetails=document.getElementById("pw-backup-zip-details"),health=document.getElementById("pw-backup-health"),healthTitle=document.getElementById("pw-backup-health-title"),healthDetail=document.getElementById("pw-backup-health-detail"),log=document.getElementById("pw-backup-log"),copy=document.getElementById("pw-backup-copy-log"),form=document.getElementById("pw-backup-create-form"),create=document.getElementById("pw-backup-create"),cancel=document.getElementById("pw-backup-cancel"),timer,clock,last={};function request(action){return fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:action,nonce:nonce})}).then(function(response){return response.json();});}function duration(seconds){seconds=Math.max(0,Math.round(seconds||0));var h=Math.floor(seconds/3600),m=Math.floor((seconds%3600)/60),s=seconds%60;return(h?h+"h ":"")+String(m).padStart(2,"0")+"min "+String(s).padStart(2,"0")+"s";}function bytes(value){value=Number(value||0);if(value<1024){return value+" B";}var units=["KB","MB","GB","TB"],i=-1;do{value=value/1024;i++;}while(value>=1024&&i<units.length-1);return value.toFixed(value>=10?0:1)+" "+units[i];}function healthCheck(){var running=last.state==="running"||last.state==="cancelling",age=Math.max(0,Date.now()/1000-Number(last.updated||Date.now()/1000)),zipPhase=/ZIP|Compactando|Finalizando/i.test(last.message||""),warning=zipPhase?45:20,critical=zipPhase?120:60,level="normal",title="Status normal",detail="Atualização recebida agora.";if(!running){if(last.state==="complete"){title="Backup concluído";detail="A validação foi finalizada com sucesso.";}else if(last.state==="error"){level="critical";title="Falha no backup";detail=last.message||"Verifique o log.";}else if(last.state==="cancelled"){level="warning";title="Backup interrompido";detail=last.message||"";}}else if(age>=critical){level="critical";title="Demora acima do esperado";detail="Sem atualização há "+duration(age)+". Use Interromper backup se permanecer assim.";}else if(age>=warning){level="warning";title="Atenção: etapa lenta";detail="Sem atualização há "+duration(age)+". Ainda pode ser normal para arquivos grandes.";}else{detail="Normal — última atualização há "+duration(age)+".";}health.className="pw-backup-health is-"+level;healthTitle.textContent=title;healthDetail.textContent=detail;}function timing(){var percent=Number(last.percent||0),started=Number(last.started||0),running=last.state==="running"||last.state==="cancelling";if(!started){start.textContent="—";elapsed.textContent="—";eta.textContent="—";healthCheck();return;}var passed=Math.max(0,Date.now()/1000-started);start.textContent=new Date(started*1000).toLocaleString("pt-BR");elapsed.textContent=duration(passed);eta.textContent=running&&percent>0&&percent<100?duration(passed*(100-percent)/percent):percent>=100?"Concluído":"Calculando…";healthCheck();}function show(data){last=data||{};var percent=Number(last.percent||0),state=last.state||"idle";bar.style.width=percent+"%";number.textContent=percent+"%";message.textContent=last.message||"Pronto para iniciar.";current.textContent=last.current||"Aguardando próxima etapa.";zipDetails.textContent=Number(last.zip_files||0).toLocaleString("pt-BR")+" arquivos — "+bytes(last.zip_bytes);log.value=Array.isArray(last.log)&&last.log.length?last.log.join("\n"):(last.message||"Aguardando início.");log.scrollTop=log.scrollHeight;timing();var running=state==="running"||state==="cancelling",terminal=state==="complete"||state==="error"||state==="cancelled";cancel.style.display=running?"inline-block":"none";create.disabled=running;copy.disabled=!terminal;if(running&&!timer){timer=setInterval(poll,1100);}if(running&&!clock){clock=setInterval(timing,1000);}if(terminal&&timer){clearInterval(timer);timer=null;}if(terminal&&clock){clearInterval(clock);clock=null;}}function poll(){request("pw_printway_backup_status").then(function(result){if(result.success){show(result.data);}}).catch(function(){});}function beginPoll(){poll();if(!timer){timer=setInterval(poll,1100);}}form.addEventListener("submit",function(event){event.preventDefault();create.disabled=true;copy.disabled=true;show({percent:1,message:"Iniciando backup…",state:"running",started:Date.now()/1000,updated:Date.now()/1000,log:["Iniciando backup…"]});beginPoll();request("pw_printway_backup_create_async").then(function(result){if(result.success){message.textContent=result.data&&result.data.message?result.data.message:"Backup iniciado em segundo plano.";beginPoll();return;}show({percent:0,message:result.data&&result.data.message?result.data.message:"Falha ao iniciar o backup.",state:"error",started:last.started,updated:Date.now()/1000,log:(last.log||[]).concat([result.data&&result.data.message?result.data.message:"Falha ao iniciar o backup."])});}).catch(function(){message.textContent="A conexão com o painel foi interrompida. Consultando o servidor…";beginPoll();});});cancel.addEventListener("click",function(){if(!window.confirm("Deseja solicitar a interrupção do backup atual?")){return;}cancel.disabled=true;request("pw_printway_backup_cancel").then(function(result){message.textContent=result.data&&result.data.message?result.data.message:"Solicitação enviada.";cancel.disabled=false;beginPoll();}).catch(function(){cancel.disabled=false;});});copy.addEventListener("click",function(){if(copy.disabled){return;}log.select();var done=false;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(log.value).then(function(){copy.textContent="Log copiado";setTimeout(function(){copy.textContent="Copiar log";},1400);});done=true;}if(!done){document.execCommand("copy");copy.textContent="Log copiado";setTimeout(function(){copy.textContent="Copiar log";},1400);}});poll();});</script><hr><h2>Backups armazenados</h2>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( $ajax_nonce ) . ',form=document.getElementById("pw-backup-create-form"),create=document.getElementById("pw-backup-create"),technical=document.getElementById("pw-backup-technical"),zipDetails=document.getElementById("pw-backup-zip-details"),health=document.getElementById("pw-backup-health"),healthTitle=document.getElementById("pw-backup-health-title"),healthDetail=document.getElementById("pw-backup-health-detail"),eta=document.getElementById("pw-backup-eta"),state={};function duration(seconds){seconds=Math.max(0,Math.round(seconds||0));var h=Math.floor(seconds/3600),m=Math.floor((seconds%3600)/60),s=seconds%60;return(h?h+"h ":"")+String(m).padStart(2,"0")+"min "+String(s).padStart(2,"0")+"s";}function bytes(value){value=Number(value||0);if(value<1024){return value+" B";}var units=["KB","MB","GB","TB"],i=-1;do{value/=1024;i++;}while(value>=1024&&i<units.length-1);return value.toFixed(value>=10?0:1)+" "+units[i];}function request(action){return fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:action,nonce:nonce})}).then(function(response){return response.json();});}function updateView(){var running=state.state==="running"||state.state==="cancelling",zipPhase=state.phase==="zip_close",now=Date.now()/1000,phaseAge=Math.max(0,now-Number(state.phase_started||state.updated||now)),age=Math.max(0,now-Number(state.updated||now)),expected=Math.max(30,Number(state.expected_seconds||0)),warning=zipPhase?Math.max(60,expected*1.25):45,critical=zipPhase?Math.max(180,expected*2.5):180,level="normal",title="Processamento normal",detail="Última atualização há "+duration(age)+".";zipDetails.textContent=Number(state.zip_files||0).toLocaleString("pt-BR")+" arquivos — "+bytes(state.zip_bytes||0);if(zipPhase){technical.textContent="Etapa técnica: fechamento e validação do ZIP. "+Number(state.zip_stored_files||0).toLocaleString("pt-BR")+" arquivo(s) já compactados foram gravados sem recompressão e "+Number(state.zip_compressed_files||0).toLocaleString("pt-BR")+" foram compactados pelo servidor. Todos os arquivos já foram preparados; nesta etapa a biblioteca ZIP não informa progresso interno por arquivo.";if(phaseAge>expected){eta.textContent="Acima da estimativa em "+duration(phaseAge-expected);}else{eta.textContent=duration(expected-phaseAge);}detail="Fechamento do ZIP em execução há "+duration(phaseAge)+". Estimativa calculada: "+duration(expected)+".";}else{technical.textContent="Preparando o ZIP: "+Number(state.zip_stored_files||0).toLocaleString("pt-BR")+" arquivo(s) sem recompressão e "+Number(state.zip_compressed_files||0).toLocaleString("pt-BR")+" para compactação.";}if(state.stale||running&&phaseAge>=critical){level="critical";title="Processo provavelmente travado";detail="A etapa excedeu bastante o tempo calculado. Interrompa o backup e inicie outro manualmente quando desejar.";}else if(running&&phaseAge>=warning){level="warning";title="Etapa mais lenta que o esperado";detail+=" O processo ainda pode concluir, mas já merece acompanhamento.";}else if(state.state==="complete"){title="Backup concluído";detail="O ZIP foi gravado e validado com sucesso.";}else if(state.state==="error"){level="critical";title="Falha no backup";detail=state.message||"Consulte o log.";}else if(state.state==="cancelled"){level="warning";title="Backup anterior liberado";detail=state.message||"Processo interrompido.";}health.className="pw-backup-health is-"+level;healthTitle.textContent=title;healthDetail.textContent=detail;if(running){create.disabled=true;create.textContent="Criar backup agora";}else{create.disabled=false;create.textContent="Criar backup agora";}}function poll(){request("pw_printway_backup_status").then(function(result){if(result.success){state=result.data||{};updateView();}}).catch(function(){});}form.addEventListener("submit",function(event){var running=state.state==="running"||state.state==="cancelling"||state.locked;if(running){event.preventDefault();event.stopImmediatePropagation();}},true);poll();setInterval(poll,900);setInterval(updateView,500);});</script>';
		/* Scripts suplementares antigos abaixo atualizavam a mesma tela em paralelo e podiam travar o navegador. */
		if ( false ) {
		echo '<style>#pw-backup-health{display:none!important}.pw-backup-diagnostic{min-height:74px;margin:12px 0;padding:12px 14px;border-left:4px solid #00a32a;border-radius:6px;background:#edf8ef;color:#075b18;box-sizing:border-box}.pw-backup-diagnostic.is-warning{border-color:#dba617;background:#fff8e5;color:#725500}.pw-backup-diagnostic.is-critical{border-color:#d63638;background:#fcf0f1;color:#8a1113}.pw-backup-diagnostic__title{display:block;min-height:20px;font-weight:700}.pw-backup-diagnostic__text{display:block;min-height:38px;margin-top:4px;line-height:1.4}</style>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( $ajax_nonce ) . ',old=document.getElementById("pw-backup-health"),lastKey="";if(!old){return;}var box=document.createElement("div");box.id="pw-backup-diagnostic";box.className="pw-backup-diagnostic";box.innerHTML="<strong class=\"pw-backup-diagnostic__title\">Aguardando início</strong><span class=\"pw-backup-diagnostic__text\">O sistema mostrará a etapa atual e avisará somente quando houver algo que exija atenção.</span>";old.parentNode.insertBefore(box,old);var title=box.querySelector(".pw-backup-diagnostic__title"),text=box.querySelector(".pw-backup-diagnostic__text");function duration(value){value=Math.max(0,Math.round(value||0));var m=Math.floor(value/60),s=value%60;return String(m).padStart(2,"0")+"min "+String(s).padStart(2,"0")+"s";}function render(data){var now=Date.now()/1000,phase=data.phase||"preparing",running=data.state==="running"||data.state==="cancelling",started=Number(data.phase_started||data.updated||now),elapsed=Math.max(0,now-started),expected=Math.max(30,Number(data.expected_seconds||0)),level="normal",heading="Etapa atual: preparando o backup",body="O processo está organizando os itens selecionados.";if(phase==="zip_flush"){heading="Etapa atual: gravando lote do ZIP";body="O servidor está salvando uma parte do backup no disco. Assim a etapa final não precisa concentrar todo o conteúdo.";}else if(phase==="zip_close"){heading="Etapa atual: finalizando e validando o ZIP";body="Todos os arquivos já foram gravados em lotes. Restam apenas o índice final e a validação do arquivo.";if(elapsed>Math.max(60,expected*1.25)){level="warning";heading="Atenção: finalização mais lenta que o esperado";body="A validação final está em andamento há "+duration(elapsed)+"; estimativa desta etapa: "+duration(expected)+".";}if(data.stale||elapsed>Math.max(180,expected*2.5)){level="critical";heading="Atenção: processo provavelmente travado";body="A etapa final excedeu o limite calculado. Interrompa o backup e inicie outro manualmente quando desejar.";}}else if(phase==="copying"){heading="Etapa atual: copiando arquivos para o ZIP";body="Os arquivos são salvos em lotes para reduzir o risco de travamento no encerramento.";}if(data.state==="complete"){level="normal";heading="Backup concluído";body="Arquivo ZIP salvo e validado com sucesso.";}else if(data.state==="error"){level="critical";heading="Falha no backup";body=data.message||"Consulte o log abaixo.";}else if(data.state==="cancelled"){level="warning";heading="Backup anterior interrompido";body=data.message||"Aguardando você iniciar outro backup.";}var key=level+"|"+heading+"|"+body;if(key===lastKey){return;}lastKey=key;box.className="pw-backup-diagnostic is-"+level;title.textContent=heading;text.textContent=body;}function poll(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_status",nonce:nonce})}).then(function(response){return response.json();}).then(function(result){if(result.success){render(result.data||{});}}).catch(function(){});}poll();setInterval(poll,5000);});</script>';
		echo '<style>.pw-backup-progress__time.is-warning{background:#fff8e5;box-shadow:inset 3px 0 #dba617}.pw-backup-progress__time.is-critical{background:#fcf0f1;box-shadow:inset 3px 0 #d63638}.pw-backup-progress__time.is-warning strong{color:#725500}.pw-backup-progress__time.is-critical strong{color:#8a1113}</style>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( $ajax_nonce ) . ',bar=document.getElementById("pw-backup-progress-bar"),number=document.getElementById("pw-backup-progress-number"),eta=document.getElementById("pw-backup-eta"),etaCard=eta&&eta.closest(".pw-backup-progress__time"),status={},highest=0,run="";function apply(data){status=data||{};var id=status.run_id||("legacy-"+String(status.started||""));if(id!==run){run=id;highest=0;}var percent=Math.max(0,Math.min(100,Number(status.percent||0))),running=status.state==="running"||status.state==="cancelling";if(percent>highest){highest=percent;}if(running){bar.style.width=highest+"%";number.textContent=highest+"%";}if(!etaCard){return;}etaCard.classList.remove("is-warning","is-critical");var phase=status.phase||"",now=Date.now()/1000,started=Number(status.phase_started||status.updated||now),elapsed=Math.max(0,now-started),expected=Math.max(30,Number(status.expected_seconds||0));if(running&&(phase==="zip_flush"||phase==="zip_close")){if(status.stale||elapsed>=Math.max(180,expected*2.5)){etaCard.classList.add("is-critical");}else if(elapsed>=Math.max(60,expected*1.25)){etaCard.classList.add("is-warning");}}}new MutationObserver(function(){var running=status.state==="running"||status.state==="cancelling",shown=parseFloat(bar.style.width||"0");if(running&&shown<highest){bar.style.width=highest+"%";number.textContent=highest+"%";}}).observe(bar,{attributes:true,attributeFilter:["style"]});function poll(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_status",nonce:nonce})}).then(function(response){return response.json();}).then(function(result){if(result.success){apply(result.data);}}).catch(function(){});}poll();setInterval(poll,1100);setInterval(function(){apply(status);},1000);});</script>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var nonce=' . wp_json_encode( $ajax_nonce ) . ',diagnostic=document.getElementById("pw-backup-diagnostic"),technical=document.getElementById("pw-backup-technical"),eta=document.getElementById("pw-backup-eta"),etaCard=eta&&eta.closest(".pw-backup-progress__time"),state={};function duration(value){value=Math.max(0,Math.round(value||0));var h=Math.floor(value/3600),m=Math.floor((value%3600)/60),s=value%60;return(h?h+"h ":"")+String(m).padStart(2,"0")+"min "+String(s).padStart(2,"0")+"s";}function render(){if(state.phase!=="validating"){return;}var running=state.state==="running"||state.state==="cancelling",now=Date.now()/1000,updated=Number(state.updated||now),age=Math.max(0,now-updated),started=Number(state.phase_started||updated),elapsed=Math.max(0,now-started),done=Number(state.current_done||0),total=Number(state.current_total||0),remaining=done>0&&total>done?elapsed*(total-done)/done:0;if(diagnostic){var level=state.stale||age>300?"critical":age>90?"warning":"normal",title=diagnostic.querySelector(".pw-backup-diagnostic__title"),text=diagnostic.querySelector(".pw-backup-diagnostic__text");diagnostic.className="pw-backup-diagnostic is-"+level;if(title){title.textContent=level==="critical"?"Atenção: validação sem resposta":level==="warning"?"Validação temporariamente mais lenta":"Etapa atual: validando os volumes ZIP";}if(text){text.textContent=level==="normal"?(total?done+" de "+total+" volumes validados estruturalmente.":"Conferindo a estrutura dos volumes."):"O último volume não respondeu há "+duration(age)+". O backup só será liberado após a validação.";}}if(technical){technical.textContent="Validação estrutural dos volumes ZIP: "+done.toLocaleString("pt-BR")+" de "+total.toLocaleString("pt-BR")+" concluídos. Esta verificação não relê os "+Number(state.zip_bytes||0).toLocaleString("pt-BR")+" bytes do backup para calcular uma segunda cópia criptográfica.";}if(eta&&running){eta.textContent=remaining>0?duration(remaining):"Calculando…";}if(etaCard){etaCard.classList.remove("is-warning","is-critical");if(state.stale||age>300){etaCard.classList.add("is-critical");}else if(age>90){etaCard.classList.add("is-warning");}}}function poll(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_status",nonce:nonce})}).then(function(response){return response.json();}).then(function(result){if(result.success){state=result.data||{};render();}}).catch(function(){});}poll();setInterval(poll,1300);setInterval(render,500);});</script>';
		}
		$completion_script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var nonce = __PW_NONCE__, timer = null, resumeTimer = null, settled = false, locks = [];
	var initial = window.pwBackupInitialStatus || {};
	function formatBytes(value) {
		value = Number(value || 0);
		if (value < 1024) { return value + ' B'; }
		var units = ['KB', 'MB', 'GB', 'TB'], index = -1;
		do { value /= 1024; index++; } while (value >= 1024 && index < units.length - 1);
		return value.toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + ' ' + units[index];
	}
	function keepText(node, text) {
		if (!node) { return; }
		var applying = false;
		function apply() {
			if (applying || node.textContent === text) { return; }
			applying = true;
			node.textContent = text;
			applying = false;
		}
		apply();
		var observer = new MutationObserver(apply);
		observer.observe(node, { childList: true, subtree: true, characterData: true });
		locks.push(observer);
	}
	function keepProgress() {
		var bar = document.getElementById('pw-backup-progress-bar'), number = document.getElementById('pw-backup-progress-number');
		if (bar) {
			function apply() { if (bar.style.width !== '100%') { bar.style.width = '100%'; } }
			apply();
			var observer = new MutationObserver(apply);
			observer.observe(bar, { attributes: true, attributeFilter: ['style'] });
			locks.push(observer);
		}
		keepText(number, '100%');
	}
	function keepNeutralTimeCard() {
		var eta = document.getElementById('pw-backup-eta'), card = eta && eta.closest('.pw-backup-progress__time');
		if (!card) { return; }
		function apply() { card.classList.remove('is-warning', 'is-critical'); }
		apply();
		var observer = new MutationObserver(apply);
		observer.observe(card, { attributes: true, attributeFilter: ['class'] });
		locks.push(observer);
	}
	function releaseLocks() {
		locks.forEach(function (observer) { observer.disconnect(); });
		locks = [];
		settled = false;
	}
	function updateRestoreCount(data) {
		var restoreTab = document.getElementById('pw-backup-restore-tab') || document.querySelector('.nav-tab-wrapper a[href*="tab=restore"]');
		if (restoreTab) { restoreTab.textContent = 'Restauração (' + Number(data.restore_count || 0).toLocaleString('pt-BR') + ')'; }
		if (data.latest_backup) {
			var notice = document.getElementById('pw-backup-latest-notice');
			if (notice) {
				notice.className = 'notice notice-info inline';
				notice.innerHTML = '<p><strong>Último backup:</strong> <span id="pw-backup-latest-value"></span></p>';
				notice.querySelector('#pw-backup-latest-value').textContent = data.latest_backup;
			}
		}
	}
	function refreshRestoreCount() {
		return fetch(ajaxurl + '?pw_refresh=' + Date.now(), {
			method: 'POST',
			cache: 'no-store',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams({ action: 'pw_printway_backup_restore_count', nonce: nonce })
		}).then(function (response) { return response.json(); }).then(function (result) {
			if (result && result.success) { updateRestoreCount(result.data || {}); return result.data || {}; }
			return {};
		}).catch(function () { return {}; });
	}
	function synchronizeCompletedBackup(attempt) {
		attempt = Number(attempt || 0);
		refreshRestoreCount().then(function () {
			/* Mantem uma janela curta de sincronizacao porque o fechamento do ZIP,
			 * os metadados e a resposta AJAX podem terminar em instantes diferentes. */
			if (attempt < 30) { setTimeout(function () { synchronizeCompletedBackup(attempt + 1); }, 1000); }
		});
	}
	function idleStatus(count) {
		return { percent: 0, message: 'Pronto para iniciar.', state: 'idle', updated: Date.now() / 1000, restore_count: Number(count || 0) };
	}
	function resetPanel(count) {
		releaseLocks();
		clearInterval(timer);
		window.pwBackupInitialStatus = idleStatus(count);
		window.pwBackupPollingEnabled = false;
		var set = function (id, text) { var node = document.getElementById(id); if (node) { node.textContent = text; } };
		var bar = document.getElementById('pw-backup-progress-bar');
		if (bar) { bar.style.width = '0%'; }
		set('pw-backup-progress-number', '0%');
		set('pw-backup-progress-message', 'Pronto para iniciar.');
		set('pw-backup-start', '—');
		set('pw-backup-elapsed', '—');
		set('pw-backup-eta', '—');
		var etaCard = document.getElementById('pw-backup-eta');
		etaCard = etaCard && etaCard.closest('.pw-backup-progress__time');
		if (etaCard) { etaCard.classList.remove('is-warning', 'is-critical'); }
		set('pw-backup-current', '');
		set('pw-backup-zip-details', '—');
		set('pw-backup-technical', 'Os detalhes aparecem durante a execução do backup.');
		var log = document.getElementById('pw-backup-log');
		if (log) { log.value = 'Aguardando início.'; }
		var copy = document.getElementById('pw-backup-copy-log');
		if (copy) { copy.disabled = true; }
		var diagnostic = document.getElementById('pw-backup-diagnostic');
		if (diagnostic) {
			diagnostic.className = 'pw-backup-diagnostic is-normal';
			var title = diagnostic.querySelector('.pw-backup-diagnostic__title'), text = diagnostic.querySelector('.pw-backup-diagnostic__text');
			if (title) { title.textContent = 'Aguardando início'; }
			if (text) { text.textContent = 'O sistema mostrará a etapa atual quando um novo backup for iniciado.'; }
		}
		updateRestoreCount({ restore_count: count });
	}
	function showLastCompletion(data) {
		var old = document.getElementById('pw-backup-last-completion');
		if (old) { old.remove(); }
		var volumes = Number(data.current_total || 0), notice = document.createElement('div');
		notice.id = 'pw-backup-last-completion';
		notice.className = 'notice notice-success inline';
		notice.innerHTML = '<p><strong>Último backup concluído com sucesso.</strong> ' + (volumes ? volumes.toLocaleString('pt-BR') + ' volumes ZIP foram validados e estão disponíveis em Restauração.' : 'O arquivo está disponível em Restauração.') + '</p>';
		var progress = document.getElementById('pw-backup-progress');
		if (progress && progress.parentNode) { progress.parentNode.insertBefore(notice, progress); }
		setTimeout(function () { notice.remove(); }, 5000);
	}
	function settle(data) {
		if (settled || data.state !== 'complete') { return; }
		settled = true;
		clearInterval(timer);
		window.pwBackupInitialStatus = data;
		window.pwBackupPollingEnabled = false;
		var volumes = Number(data.current_total || 0), files = Number(data.zip_files || 0);
		var volumeText = volumes === 1 ? '1 volume ZIP validado' : volumes.toLocaleString('pt-BR') + ' volumes ZIP validados';
		keepProgress();
		keepNeutralTimeCard();
		keepText(document.getElementById('pw-backup-zip-details'), volumeText + '.');
		keepText(document.getElementById('pw-backup-technical'), 'Backup concluído e validado. O conjunto está pronto para restauração.');
		keepText(document.getElementById('pw-backup-current'), '');
		keepText(document.getElementById('pw-backup-eta'), 'Concluído');
		var copy = document.getElementById('pw-backup-copy-log');
		if (copy) { copy.disabled = false; }
		var diagnostic = document.getElementById('pw-backup-diagnostic');
		if (diagnostic) {
			diagnostic.className = 'pw-backup-diagnostic is-normal';
			keepText(diagnostic.querySelector('.pw-backup-diagnostic__title'), 'Backup concluído e pronto para restauração');
			keepText(diagnostic.querySelector('.pw-backup-diagnostic__text'), volumeText + ' com sucesso. As informações desta tela foram finalizadas.');
		}
		updateRestoreCount(data);
		synchronizeCompletedBackup(0);
	}
	function poll() {
		fetch(ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams({ action: 'pw_printway_backup_status', nonce: nonce })
		}).then(function (response) { return response.json(); }).then(function (result) {
			if (result.success) { settle(result.data || {}); }
		}).catch(function () {});
	}
	function detectFinishedScreen() {
		if (settled) { return; }
		var message = document.getElementById('pw-backup-progress-message');
		var number = document.getElementById('pw-backup-progress-number');
		var current = document.getElementById('pw-backup-current');
		if (!message || !/backup conclu[ií]do/i.test(message.textContent || '')) { return; }
		var match = (current && current.textContent || '').match(/(?:volume\s+)?(\d+)\s+de\s+(\d+)/i);
		settle({ state: 'complete', percent: 100, current_total: match ? Number(match[2]) : 0, current_done: match ? Number(match[2]) : 0, restore_count: Number(initial.restore_count || 0) });
	}
	var form = document.getElementById('pw-backup-create-form');
	if (form) {
		form.addEventListener('submit', function () {
			clearTimeout(resumeTimer);
			releaseLocks();
			window.pwBackupInitialStatus = { percent: 1, message: 'Iniciando backup…', state: 'running', updated: Date.now() / 1000, restore_count: Number(initial.restore_count || 0) };
			window.pwBackupPollingEnabled = false;
			resumeTimer = setTimeout(function () { window.pwBackupPollingEnabled = true; }, 1800);
		}, true);
	}
	if (initial.state === 'complete') {
		showLastCompletion(initial);
		resetPanel(initial.restore_count);
		synchronizeCompletedBackup(0);
		return;
	}
	poll();
	timer = setInterval(poll, 1300);
	setInterval(detectFinishedScreen, 350);
});
JS;
		$completion_script = str_replace( '__PW_NONCE__', wp_json_encode( $ajax_nonce ), $completion_script );
		echo '<script>' . $completion_script . '</script>';
		$files = pw_printway_backup_list(); if ( ! $files ) { echo '<p>Nenhum backup armazenado ainda.</p>'; } else { echo '<table class="widefat striped"><thead><tr><th>Arquivo</th><th>Data e hora</th><th>Tamanho</th><th>Ações</th></tr></thead><tbody>'; foreach ( $files as $file ) { $meta = json_decode( (string) @file_get_contents( $file . '.json' ), true ); $download = wp_nonce_url( add_query_arg( array( 'action' => 'pw_printway_backup_download', 'file' => basename( $file ) ), admin_url( 'admin-post.php' ) ), 'pw_printway_backup_download' ); echo '<tr><td>' . esc_html( basename( $file ) ) . '</td><td>' . esc_html( wp_date( 'd/m/Y H:i', filemtime( $file ), wp_timezone() ) ) . '</td><td>' . esc_html( size_format( filesize( $file ), 2 ) ) . '</td><td><a class="button" href="' . esc_url( $download ) . '">Baixar</a> <a class="button" href="' . esc_url( add_query_arg( array( 'tab' => 'restore', 'file' => basename( $file ) ), $base ) ) . '">Restaurar este</a></td></tr>'; } echo '</tbody></table>'; }
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var create=document.getElementById("pw-backup-create"),cancel=document.getElementById("pw-backup-cancel"),nonce=' . wp_json_encode( $ajax_nonce ) . ',running=false,syncing=false;if(!create||!cancel){return;}function sync(){if(syncing){return;}syncing=true;var label="Criar backup agora";if(create.textContent!==label){create.textContent=label;}if(create.disabled!==running){create.disabled=running;}cancel.style.display=running?"inline-block":"none";syncing=false;}new MutationObserver(sync).observe(create,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:["disabled"]});function poll(){fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pw_printway_backup_status",nonce:nonce})}).then(function(response){return response.json();}).then(function(result){var data=result.success?(result.data||{}):{};running=data.state==="running"||data.state==="cancelling";cancel.disabled=data.state==="cancelling";sync();}).catch(function(){});}poll();setInterval(poll,1000);});</script>';
	} elseif ( 'restore' === $tab ) {
		$selected = basename( sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) ) ); $files = pw_printway_backup_list();
		$foreign_sources = array();
		foreach ( $files as $restore_file ) {
			try {
				$restore_manifest = pw_printway_backup_manifest( $restore_file, true );
				if ( empty( $restore_manifest['_pw_signature_valid'] ) ) {
					$foreign_sources[ basename( $restore_file ) ] = (string) ( $restore_manifest['site_url'] ?? '' );
				}
			} catch ( Throwable $error ) { }
		}
		pw_printway_backup_restore_list_v2( $files, $base );
		$upload_limit = (int) wp_max_upload_size();
		echo '<h2>Restaurar backup</h2><div class="notice notice-warning inline"><p><strong>Atenção:</strong> esta operação pode substituir banco de dados, arquivos, plugins e temas. Antes de restaurar, o sistema cria automaticamente um backup de segurança do estado atual.</p></div>';
		/* A restauração de um backup já armazenado não usa multipart/form-data.
		 * Assim nenhum arquivo local é reenviado por engano e o Nginx não devolve 413. */
		echo '<h3>Backup já armazenado no servidor</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pw_printway_backup_restore"><input type="hidden" name="restore_selection" value="">'; wp_nonce_field( 'pw_printway_backup_restore' );
		echo '<table class="form-table"><tr><th>Backup armazenado</th><td><select name="stored_file" required><option value="">— Escolha um backup —</option>'; foreach ( $files as $file ) { echo '<option value="' . esc_attr( basename( $file ) ) . '" ' . selected( $selected, basename( $file ), false ) . '>' . esc_html( basename( $file ) . ' — ' . wp_date( 'd/m/Y H:i', filemtime( $file ), wp_timezone() ) ) . '</option>'; } echo '</select></td></tr><tr><th>Modo de arquivos</th><td><label><input type="checkbox" name="full_replace" value="1"> Substituição completa: remover arquivos antigos das áreas presentes no backup antes de restaurar.</label></td></tr><tr><th>Confirmação</th><td><label><input type="checkbox" name="confirm_restore" value="1" required> Entendo que a restauração poderá substituir dados atuais.</label><p>Digite <strong>RESTAURAR</strong>: <input type="text" name="confirmation" autocomplete="off" required></p></td></tr><tr class="pw-backup-foreign-site-confirmation" style="display:none"><th>Backup de outro site</th><td><input type="checkbox" name="confirm_foreign_site" value="1"></td></tr></table><p><button class="button button-primary">Validar e restaurar backup armazenado</button></p></form>';
		echo '<details style="margin-top:24px"><summary><strong>Importar backup ZIP pelo plugin</strong></summary><p class="description">O arquivo será enviado em blocos pequenos, validado e incluído na lista de backups. A restauração será uma ação separada, feita somente após ele aparecer na lista.</p><form id="pw-backup-external-upload"><input id="pw-backup-external-file" type="file" accept=".zip,application/zip" required style="display:none"><p><button id="pw-backup-import-choose" type="button" class="button button-primary">Importar backup ZIP</button> <button type="button" class="button" id="pw-backup-show-upload-log">Ver e copiar último log de envio</button></p></form></details>';
		/* O envio antigo embutido nesta pagina foi desativado. O controlador V5,
		 * carregado no rodape, e o unico responsavel pelo formulario acima. */
	} else {
		pw_printway_backup_failures_page();
	}
	echo '</div>';
}

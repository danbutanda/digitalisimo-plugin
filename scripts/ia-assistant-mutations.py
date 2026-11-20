#!/usr/bin/env python3
# Mutaciones del Asistente IA (docs/ASSISTANT.md): cada una deshace un arreglo de las revisiones o rompe una garantía,
# y tests/ia-assistant.php o tests/ia-assistant-ui.mjs debe fallar. Uso, en una copia (cambia y restaura archivos):
#   python3 scripts/ia-assistant-mutations.py [--check]   (--check sólo comprueba que cada mutación aplica una vez)
import subprocess, sys
A='digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant.php'
T='digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-tools.php'
H='digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-history.php'
M='digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-model.php'
G='digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-guard.php'
B='digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-ability.php'
I='digitalisimo-ia-tools/includes/class-digitalisimo-ai.php'
J='digitalisimo-ia-tools/assets/assistant/assistant.js'
D='digitalisimo-ia-tools/assets/assistant/assistant-adapters.js'
C='digitalisimo-ia-tools/assets/assistant/assistant.css'
PHP='timeout 120 php tests/ia-assistant.php'
NODE='node tests/ia-assistant-ui.mjs'
MUT=[
 # --- Revisión: A1
 ('A1 sin reclamar antes de ejecutar', A, "$conv['pending'][ $index ]['state']   = 'running';", "", PHP),
 ('A1 confirmar sin candados', A, "$approve   = 'approve' === $request->get_param( 'decision' );\n\t\t$locks     = self::locks( $user, $id );", "$approve   = 'approve' === $request->get_param( 'decision' );\n\t\t$locks     = array();", PHP),
 ('A1 mensaje sin candados', A, "$id    = absint( $request->get_param( 'conversation' ) );\n\t\t$locks = self::locks( $user, $id );", "$id    = absint( $request->get_param( 'conversation' ) );\n\t\t$locks = array();", PHP),
 ('A1 sin ignore_user_abort', A, "ignore_user_abort( true );", "", PHP),
 ('A1 lo que corría queda cancelado', A, "$result = 'running' === ( $action['state'] ?? '' )", "$result = false", PHP),
 ('A1 se confirma lo que corría', A, "if ( 'running' === ( $action['state'] ?? '' ) ) {", "if ( false ) {", PHP),
 ('A1 avisar antes de guardar', A, "\t\tDigitalisimo_AI_Assistant_History::save( $conv );\n\t\tDigitalisimo_AI_Assistant_Tools::log(", "\t\tDigitalisimo_AI_Assistant_Tools::log(", PHP),
 ('A1 tarjetas avisadas antes de guardar', A, "\t\tDigitalisimo_AI_Assistant_History::save( $conv );\n\t\tforeach ( $pending as $pair ) {", "\t\tforeach ( $pending as $pair ) {", PHP),
 ('A1 candado no atómico', G, "if ( 1 === (int) $inserted ) {", "if ( true ) {", PHP),
 ('A1 candado caducado no se recupera', G, "(int) substr( $current, $cut + 1 ) >= time()", "true", PHP),
 ('A1 candados que no se sueltan', A, "\t\t} finally {\n\t\t\tself::unlock( $locks );\n\t\t}\n\t}\n\n\t/**\n\t * La decisión", "\t\t} finally {\n\t\t}\n\t}\n\n\t/**\n\t * La decisión", PHP),
 # --- A2
 ('A2 tarjeta recortada', A, "'input'       => $input,", "'input'       => Digitalisimo_AI_Assistant_Tools::clip( $input, 1500 ),", PHP),
 ('A2 secretos demasiado amplios', T, "|^(token|key|nonce|auth|salt)$)/i';", "|[_-]key$|token|^(token|key|nonce|auth|salt)$)/i';", PHP),
 ('A2 sin contar lo oculto', A, "'hidden'      => $hidden,", "'hidden'      => 0,", PHP),
 ('A2 sin aviso tras leer', A, "'after_read' => (bool) $read );", "'after_read' => false );", PHP),
 ('A2 interfaz recorta los datos', J, "'aria-label': 'Datos completos de la acción', tabIndex: 0 }, action.input ),", "'aria-label': 'Datos completos de la acción', tabIndex: 0 }, action.input.slice( 0, 1500 ) ),", NODE),
 ('R2-M2 confirmar sin llegar al final', J, "disabled: props.busy || ! seen || ( destructive && ! ack )", "disabled: props.busy || ( destructive && ! ack )", NODE),
 ('R2-M1 mask con el patrón estrecho', T, "if ( is_string( $key ) && self::is_secret( $key ) &&", "if ( is_string( $key ) && preg_match( self::CARD_SECRETS, $key ) &&", PHP),
 ('R2-M2 invisibles crudos', T, "'/[\\p{Cf}\\p{Co}\\x{2028}\\x{2029}]/u'", "'/(?!)/u'", PHP),
 ('R2-M2 espacios sin plegar', T, "\\x{3000}]{8,}/u'", "\\x{3000}]{8000,}/u'", PHP),
 ('R2-M2 sin resumen de riesgos', T, "return array( $json, $st['hidden'], $risks );", "return array( $json, $st['hidden'], array() );", PHP),
 ('R2-M2 aviso sólo del turno', A, "$read = $read || in_array( 'tool', array_column( $messages, 'role' ), true );", "", PHP),
 ('R2-M3 espera de 45 s', M, "const TIMEOUT       = 180;", "const TIMEOUT       = 45;", PHP),
 ('R2-B10 sin reintento de tokens', M, "$target['max_tokens'] = self::MIN_TOKENS;\n\t\t\t\tcontinue;", "break;", PHP),
 ('R2-B10 negativa de Anthropic', M, "'refusal' === ( $data['stop_reason'] ?? '' ) ? 'La IA se negó a responder.' : ''", "''", PHP),
 ('R2-B1 sin renovar', G, "foreach ( self::$held as $key => $value ) {", "foreach ( array() as $key => $value ) {", PHP),
 ('R2-B3 relectura con caché', H, "wp_cache_delete( (int) $id, 'post_meta' );", "", PHP),
 ('R2-B5 WP_Post a entero', H, "$post = $args[0] instanceof WP_Post ? $args[0] : ( is_numeric( $args[0] ) ? get_post( (int) $args[0] ) : null );", "$post = get_post( (int) $args[0] );", PHP),
 ('R2-B6 reasignar conversaciones', A, "add_action( 'delete_user', array( 'Digitalisimo_AI_Assistant_History', 'on_user_removed' ) );", "", PHP),
 ('R2-B7 campo vacío sin tope', G, "return null === $value || '' === trim( (string) $value ) ? $default : (int) $value;", "return null === $value ? $default : (int) $value;", PHP),
 ('R2-B7 el 429 gasta el minuto', A, "$locks = self::locks( $user, $id );", "Digitalisimo_AI_Assistant_Guard::rate( $user ); $locks = self::locks( $user, $id );", PHP),
 ('R2-B7 capacidad fija', A, "$cap = (string) apply_filters( 'digitalisimo_ai_assistant_capability', self::CAP );", "$cap = self::CAP;", PHP),
 ('R2-B8 destructiveHint ignorado', T, "$destructive = $a['destructive'] ?? ( $a['destructiveHint'] ?? null );", "$destructive = array_key_exists( 'destructive', $a ) ? $a['destructive'] : ( $a['destructiveHint'] ?? null );", PHP),
 ('R2-B9 se guarda vacía', H, "if ( ! is_string( $json ) || '' === $json ) {\n\t\t\treturn false;", "if ( false ) {\n\t\t\treturn false;", PHP),
 ('R2-B11 error como error', A, "if ( 'error' !== $result['status'] || true === ( $entry['annotations']['idempotent'] ?? null ) ) {", "if ( true ) {", PHP),
 ('R2-B4 tarjeta zombi', J, "open( activeRef.current );\n\t\t\t\t} else {", "setPending( ( list ) => list.concat( [ action ] ) );\n\t\t\t\t} else {", NODE),
 # --- M1..M6
 ('M1 readonly laxo', T, "$readonly    = true === ( $a['readonly'] ?? null ) || true === ( $a['readOnlyHint'] ?? null );", "$readonly    = ! empty( $a['readonly'] ) || ! empty( $a['readOnlyHint'] );", PHP),
 ('M1 cualquier plugin lee sin confirmar', T, "$kind = $trusted ? 'read' : 'write';", "$kind = 'read';", PHP),
 ('M1 contradicción como lectura', T, "if ( $readonly && true !== $destructive ) {", "if ( $readonly ) {", PHP),
 ('M2 dry_run sin declararlo', T, "'dry_run'     => true === ( $meta['digitalisimo']['dry_run'] ?? null ) && 'boolean'", "'dry_run'     => 'boolean'", PHP),
 ('M2 simulación sin bitácora', T, "self::log( array( 'user' => get_current_user_id(), 'ability' => $entry['name'], 'kind' => 'simulation'", "array( array( 'user' => get_current_user_id(), 'ability' => $entry['name'], 'kind' => 'simulation'", PHP),
 ('M3 sin tope por minuto', G, "if ( $count >= $limit ) {\n\t\t\treturn new WP_Error( 'digitalisimo_assistant_rate'", "if ( false ) {\n\t\t\treturn new WP_Error( 'digitalisimo_assistant_rate'", PHP),
 ('M3 sin cupo diario', G, "if ( $count >= $limit ) {\n\t\t\treturn false;", "if ( false ) {\n\t\t\treturn false;", PHP),
 ('M3 sin tope de tiempo', A, "if ( $step > 0 && time() - $begun >= $budget ) {", "if ( false ) {", PHP),
 ('M3 sin tope de tamaño', A, "> (int) apply_filters( 'digitalisimo_ai_assistant_max_request', self::MAX_REQUEST ) ) {", "> PHP_INT_MAX ) {", PHP),
 ('M3 límites sólo en el sitio', I, "self::input( 'log_retention', 'Retención de métricas (días)', $o, 'number' ); self::assistant_fields( $o );\n", "self::input( 'log_retention', 'Retención de métricas (días)', $o, 'number' );\n", PHP),
 ('M3 límites por defecto altos', I, "'assistant_rate_minute' => 10,", "'assistant_rate_minute' => 0,", PHP),
 ('M4 bucle sin acotar (el de antes)', A, "for ( $level = ob_get_level(); $level > 0; $level-- ) {\n\t\t\tif ( ! @ob_end_flush() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged\n\t\t\t\tbreak;\n\t\t\t}", "while ( ob_get_level() > 0 ) {\n\t\t\t@ob_end_flush();", PHP),
 ('M5 tipo con capacidades de entradas', H, "'capability_type'     => array( 'digitalisimo_ai_chat', 'digitalisimo_ai_chats' ),", "'capability_type'     => 'post',", PHP),
 ('M5 superadmin lee lo ajeno', H, "return array( 'do_not_allow' );", "return $caps;", PHP),
 ('M6 guarda un turno vacío', A, "if ( '' === $text && ! $calls ) {", "if ( false ) {", PHP),
 ('M6 OpenAI recibe assistant vacío', M, "if ( '' === (string) $m['text'] && empty( $m['calls'] ) ) {\n\t\t\t\t\tcontinue; // La API", "if ( false ) {\n\t\t\t\t\tcontinue; // La API", PHP),
 ('M6 sin la negativa', M, "$refusal   = is_string( $message['refusal'] ?? null ) ? $message['refusal'] : '';", "$refusal   = '';", PHP),
 ('M6 negativa ignorada al normalizar', M, "'refusal' => is_string( $reply['refusal'] ?? null ) ? $reply['refusal'] : '',", "'refusal' => '',", PHP),
 # --- Bajas
 ('B1 recorte por cantidad', H, "$cut = $count - self::MAX_MESSAGES;", "return array_slice( $messages, - self::MAX_MESSAGES );", PHP),
 ('B1 resultado suelto al modelo', H, "if ( null === $start ) {\n\t\t\t\treturn array();", "if ( null === $start ) {\n\t\t\t\t$start = 0;", PHP),
 ('B2 sin ajustar el tope en bytes', T, "$budget -= strlen( $json ) - self::DATA_MAX + 64;", "", PHP),
 ('B2 historial corta el JSON', H, "if ( isset( $fields['text'] ) ) {", "if ( isset( $fields['content'] ) ) { $fields['content'] = Digitalisimo_AI_Assistant_Tools::clip( (string) $fields['content'], 1000 ); }\n\t\tif ( isset( $fields['text'] ) ) {", PHP),
 ('B3 atajos sin IA bloqueados', J, "if ( ! text || busy || ( ! providers.length && ! isShortcut( text ) ) ) {", "if ( ! text || busy || ! providers.length ) {", NODE),
 ('B4 «no» confirma', A, "return true === $value || 1 === $value || '1' === $value || 'true' === $value;", "return rest_sanitize_boolean( $value );", PHP),
 ('B5 prellenado largo', A, "const MAX_PREFILL = 1500;", "const MAX_PREFILL = 4000;", PHP),
 ('B5 atajo precargado se envía', J, "if ( fromLink && isShortcut( text ) ) {", "if ( false ) {", NODE),
 ('B6 nombre del sitio como instrucción', A, "wp_json_encode( (string) get_bloginfo( 'name' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )", "'«' . get_bloginfo( 'name' ) . '»'", PHP),
 ('B7 lote publicado', T, "$input['data']['create'][ $i ] = self::as_draft( $item, array() );", "", PHP),
 ('B9 tokens de Anthropic', M, "const MAX_TOKENS    = 8192;", "const MAX_TOKENS    = 2048;", PHP),
 ('B9 respuesta cortada se ejecuta', A, "if ( ! $reply['truncated'] ) {", "if ( true ) {", PHP),
 # --- Garantías anteriores que siguen vigentes
 ('sin nonce propio', A, "if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {", "if ( false ) {", PHP),
 ('sin edit_posts en la REST', A, "if ( ! is_user_logged_in() || ! current_user_can( self::cap() ) ) {", "if ( ! is_user_logged_in() ) {", PHP),
 ('bitácora para cualquiera', A, "return current_user_can( 'manage_options' ) ? true", "return true ? true", PHP),
 ('todo se ejecuta sin confirmar', A, "if ( 'read' === $kind ) {", "if ( true ) {", PHP),
 ('sin comprobar el permiso antes', A, "if ( ! Digitalisimo_AI_Assistant_Tools::allowed( $entry, $call['input'] ) ) {", "if ( false ) {", PHP),
 ('destructivo sin explícita', A, "&& ! self::explicit( $request->get_param( 'explicit' ) ) ) {", ") {", PHP),
 ('cancelar ejecuta', A, "} elseif ( ! $approve ) {", "} elseif ( false ) {", PHP),
 ('confirmación sin caducidad', A, "(int) $action['created'] < time() - self::PENDING_TTL", "false", PHP),
 ('dato sin aviso', T, "'aviso' => self::DATA_NOTICE", "'aviso' => ''", PHP),
 ('sin ocultar claves sensibles', T, "$value[ $key ] = self::MASK;", "$value[ $key ] = $item;", PHP),
 ('sin borrar claves de los textos', T, "$text = str_replace( $key, '[clave oculta]', $text );", "", PHP),
 ('lo nuevo no nace en borrador', T, "$input[ $key ] = 'draft';", "", PHP),
 ('conversación ajena', H, "(int) $post->post_author !== (int) $user_id ||", "", PHP),
 ('sin tope de conversaciones', H, "array_slice( $ids, self::MAX_CONVERSATIONS )", "array()", PHP),
 ('assets en todo el escritorio', A, "if ( self::HOOK !== $hook ) {", "if ( false ) {", PHP),
 ('open-assistant sin permiso', B, "return is_user_logged_in() && current_user_can( Digitalisimo_AI_Assistant::cap() );", "return true;", PHP),
 ('catálogo con lo no público', T, "if ( is_object( $ability ) && self::is_public( $ability ) &&", "if ( is_object( $ability ) &&", PHP),
 ('el atajo despierta al modelo', A, "&& empty( $action['shortcut'] ) ) {", ") {", PHP),
 ('clave de Google en la URL', M, "':generateContent', 'headers'", "':generateContent?key=' . $key, 'headers'", PHP),
 ('el error repite el cuerpo', M, "sprintf( '%s no respondió (%s).', $ai, $why )", "sprintf( '%s no respondió (%s).', $ai, wp_remote_retrieve_body( $response ) )", PHP),
 ('lo pendiente no se resuelve', A, "self::settle_pending( $conv, $emit );", "$conv['pending'] = array();", PHP),
 ('adaptadores sin nonce propio', D, "'X-Digitalisimo-Assistant': String( config.nonce || '' )", "'X-Other': ''", NODE),
 ('texto como HTML', J, "h( 'div', { className: 'dga-bubble' }, m.text )", "h( 'div', { className: 'dga-bubble', dangerouslySetInnerHTML: { __html: m.text } } )", NODE),
 ('hoja fuera de su pantalla', C, ".dga-root, .dga-root *, .dga-root ::before, .dga-root ::after { box-sizing: border-box; }", "body, .dga-root *, .dga-root ::before, .dga-root ::after { box-sizing: border-box; }", NODE),
]
if '--check' in sys.argv:
    bad=[(n,open(p,encoding='utf-8').read().count(o)) for n,p,o,_,_ in MUT if open(p,encoding='utf-8').read().count(o)!=1]
    print(len(MUT),'mutaciones;','no aplican:',bad); sys.exit(1 if bad else 0)
survived=[]
for name,path,old,new,test in MUT:
    src=open(path,encoding='utf-8').read()
    if src.count(old)!=1:
        print(f'NO APLICA · {name}'); survived.append(name); continue
    open(path,'w',encoding='utf-8').write(src.replace(old,new,1))
    try:
        r=subprocess.run(test,shell=True,capture_output=True,text=True,timeout=300)
        killed=r.returncode!=0
    finally:
        open(path,'w',encoding='utf-8').write(src)
    lines=(r.stdout+r.stderr).strip().splitlines()
    why=next((l for l in lines if 'FALLA' in l or 'AssertionError' in l or 'Fatal' in l),lines[-1] if lines else '')
    print(f"{'muere' if killed else 'SOBREVIVE'} · {name} · {why[:150]}")
    if not killed: survived.append(name)
print(f'{len(MUT)-len(survived)} de {len(MUT)} mutaciones detectadas')
sys.exit(1 if survived else 0)

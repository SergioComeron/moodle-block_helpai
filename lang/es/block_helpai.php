<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings for HelpAI block.
 *
 * @package    block_helpai
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'HelpAI';
$string['helpai:addinstance'] = 'Añadir un nuevo bloque HelpAI';
$string['helpai:myaddinstance'] = 'Añadir un nuevo bloque HelpAI al Área personal';
$string['helpai:askquestion'] = 'Hacer preguntas a HelpAI';
$string['helpai:viewhistory'] = 'Ver el registro de preguntas HelpAI del curso';

// Block content.
$string['welcomemessage'] = '¡Hola! Pregúntame sobre los PDFs de este curso y te diré dónde encontrar la información.';
$string['askquestion'] = 'Escribe tu pregunta...';
$string['send'] = 'Enviar';
$string['notincourse'] = 'Este bloque solo está disponible en páginas de curso.';

// AI responses.
$string['nopdfsavailable'] = 'No hay documentos PDF disponibles en este curso.';
$string['ainotenabled'] = 'La IA no está habilitada en este sitio Moodle. Por favor, contacte con su administrador.';
$string['noprovideravailable'] = 'No hay ningún proveedor de IA disponible. Por favor, contacte con su administrador.';
$string['invalidairesponse'] = 'Respuesta inválida del proveedor de IA.';
$string['aierror'] = 'Error al procesar la solicitud de IA';

// PDF processing.
$string['nopdfs'] = 'No se encontraron documentos PDF en este curso.';
$string['pdftextnotavailable'] = 'El contenido de texto del PDF no está disponible.';
$string['pdftoolnotavailable'] = 'La herramienta de extracción de texto PDF no está disponible en este servidor.';

// Search.
$string['nokeywords'] = 'No se pudieron extraer palabras clave de tu pregunta. Por favor, intenta reformularla.';
$string['noresults'] = 'No se encontraron PDFs que coincidan con tu pregunta.';
$string['foundinfo'] = 'Encontré información relevante en los siguientes PDFs:';

// Tasks.
$string['taskindexpdfs'] = 'Indexar documentos PDF para búsqueda';

// Settings.
$string['openai_apikey'] = 'Clave API de OpenAI';
$string['openai_apikey_desc'] = 'Clave API de OpenAI del sitio (trae tu propia clave). El sitio Moodle / la institución paga a OpenAI; este plugin no factura uso, no vende licencias y no envía datos al autor. Obtén una clave en https://platform.openai.com/api-keys';
$string['openai_model'] = 'Modelo de OpenAI';
$string['openai_model_desc'] = 'Selecciona qué modelo de OpenAI usar. GPT-4o y GPT-4 Turbo soportan análisis de PDFs.';
$string['searchmode'] = 'Modo de búsqueda';
$string['searchmode_desc'] = 'Elige cómo buscar en los PDFs: Híbrido (búsqueda local primero, luego IA) o Solo IA (siempre usa IA, no necesita extraer texto)';
$string['searchmode_hybrid'] = 'Híbrido (local + IA) - Requiere pdftotext';
$string['searchmode_aionly'] = 'Solo IA (siempre usa IA) - No requiere pdftotext';
$string['maxpdfsperask'] = 'PDFs adjuntos por pregunta';
$string['maxpdfsperask_desc'] = 'Número máximo de PDF que se envían a OpenAI en una pregunta (1–10). Por defecto 3. Se listan todos los títulos del curso. Si el curso tiene esa cantidad o menos, se adjuntan todos (con un tope total de 20 MB).';
$string['dailylimit'] = 'Preguntas diarias por estudiante';
$string['dailylimit_desc'] = 'Número máximo de preguntas que un estudiante puede hacer por curso y día. El valor por defecto es 20. Usa 0 para no limitar. Los profesores, profesores editores y gestores (quien tenga la capacidad de ver el registro de preguntas) no están sujetos a este tope.';
$string['dailylimitreached'] = 'Has alcanzado el límite diario de {$a} preguntas en este curso. Inténtalo de nuevo mañana.';
$string['noanswerinmaterials'] = 'Los PDFs de este curso no contienen la respuesta a esa pregunta.';

// History.
$string['clearhistory'] = 'Borrar historial';
$string['confirmclearhistory'] = '¿Estás seguro de que quieres borrar todo tu historial de conversación?';
$string['historycleared'] = 'Historial borrado correctamente.';

// Quick actions.
$string['quickactions'] = 'Preguntas sugeridas:';
$string['quickaction1'] = '¿Qué temas se cubren en los PDFs de este curso?';
$string['quickaction2'] = '¿Dónde puedo encontrar información sobre el tema principal del curso?';
$string['quickaction3'] = '¿Qué PDF debería leer primero?';

// Schemas.
$string['schemas'] = 'Esquemas';
$string['chat'] = 'Chat';
$string['schemas_tab'] = 'Esquemas de PDFs';
$string['schemas_description'] = 'Genera esquemas estructurados de los PDFs del curso para obtener una visión general del contenido.';
$string['generate_schema'] = 'Generar esquema';
$string['view_schema'] = 'Ver esquema';
$string['regenerate_schema'] = 'Regenerar esquema';
$string['generating_schema'] = 'Generando esquema...';
$string['schema_generated'] = 'Esquema generado correctamente';
$string['no_schemas_yet'] = 'Aún no hay esquemas generados. Haz clic en "Generar esquema" para crear uno.';
$string['schema_generation_failed'] = 'Error al generar el esquema. Por favor, inténtalo de nuevo.';
$string['loading_pdfs'] = 'Cargando PDFs...';
$string['no_pdfs_in_course'] = 'No hay PDFs disponibles en este curso.';
$string['schema_for'] = 'Esquema de: {$a}';
$string['generated_on'] = 'Generado el: {$a}';
$string['close_schema'] = 'Cerrar esquema';

// Teacher question log.
$string['viewquestionlog'] = 'Registro de preguntas';
$string['questionlog'] = 'Registro de preguntas HelpAI';
$string['questionlog_desc'] = 'Preguntas hechas en este curso. Se guarda la respuesta mostrada al estudiante; no se almacenan las peticiones crudas a la API.';
$string['allusers'] = 'Todos los usuarios';
$string['noquestions'] = 'Aún no se ha registrado ninguna pregunta en este curso.';
$string['col_time'] = 'Fecha';
$string['col_user'] = 'Usuario';
$string['col_question'] = 'Pregunta';
$string['col_answer'] = 'Respuesta';
$string['col_aiused'] = 'Usó IA';
$string['col_outcome'] = 'Resultado';
$string['outcome_answered'] = 'Respondida';
$string['outcome_refused'] = 'No está en los materiales';
$string['outcome_limit_hit'] = 'Límite diario';
$string['outcome_error'] = 'Error';
$string['outcome_no_pdfs'] = 'Sin PDFs';

// Privacy.
$string['privacy:metadata'] = 'El bloque HelpAI almacena el historial personal de chat y un registro de preguntas del curso. Cuando un estudiante pregunta, el texto de la pregunta y los PDF visibles de ese curso se envían a OpenAI.';
$string['privacy:metadata:openai'] = 'El texto de la pregunta y los PDF (o su texto extraído) de este curso se envían a la API de OpenAI (api.openai.com) para generar una respuesta. No se envían ID de usuario, nombre ni correo. El tiempo que OpenAI retiene los datos depende de la cuenta OpenAI de la institución. Este plugin no envía datos al autor.';
$string['privacy:metadata:openai:prompttext'] = 'La pregunta del estudiante, o una petición de esquema de un PDF.';
$string['privacy:metadata:openai:pdfs'] = 'Los PDF de este curso que el usuario puede ver, adjuntos como fichero o como texto extraído.';
$string['privacy:metadata:openai:model'] = 'El modelo de OpenAI configurado por el administrador del sitio.';
$string['costheading'] = 'Coste';
$string['costheading_desc'] = 'La institución paga a OpenAI en USD (este plugin no factura). Con GPT-4o, una pregunta típica con 2–3 PDF de apuntes ronda 0,10–0,25 $; PDF pequeños, unos céntimos; cerca del tope de 3 ficheros puede pasar de 0,50 $. Un esquema cuesta como una pregunta con un PDF. La salida está limitada (~0,01 $). El tope diario de 20 preguntas por estudiante es el freno principal. Los precios cambian: ver la tarifa de OpenAI y el README del plugin.';
$string['privacyheading'] = 'Tratamiento externo (OpenAI)';
$string['privacyheading_desc'] = 'Cuando un estudiante usa HelpAI, su pregunta y los PDF que puede ver en ese curso se envían a OpenAI con la clave API del sitio. No se incluye la identidad (id, nombre, correo). La retención la marca la cuenta OpenAI de la institución, no este plugin. HelpAI no envía datos al autor.';
$string['privacy:metadata:block_helpai_history'] = 'Historial personal de conversaciones del chat HelpAI';
$string['privacy:metadata:block_helpai_history:userid'] = 'ID del usuario que hizo la pregunta';
$string['privacy:metadata:block_helpai_history:courseid'] = 'ID del curso donde se hizo la pregunta';
$string['privacy:metadata:block_helpai_history:role'] = 'Rol del mensaje (usuario o asistente)';
$string['privacy:metadata:block_helpai_history:message'] = 'Contenido del mensaje';
$string['privacy:metadata:block_helpai_history:timecreated'] = 'Fecha y hora en que se creó el mensaje';
$string['privacy:metadata:block_helpai_questions'] = 'Registro de preguntas del curso HelpAI (visible para profesores)';
$string['privacy:metadata:block_helpai_questions:userid'] = 'ID del usuario que hizo la pregunta';
$string['privacy:metadata:block_helpai_questions:courseid'] = 'ID del curso donde se hizo la pregunta';
$string['privacy:metadata:block_helpai_questions:question'] = 'Texto de la pregunta';
$string['privacy:metadata:block_helpai_questions:answer'] = 'Respuesta o resumen mostrado al estudiante';
$string['privacy:metadata:block_helpai_questions:aiused'] = 'Si se llamó a OpenAI';
$string['privacy:metadata:block_helpai_questions:outcome'] = 'Resultado de la pregunta (respondida, rechazada, límite, error)';
$string['privacy:metadata:block_helpai_questions:timecreated'] = 'Fecha y hora en que se hizo la pregunta';

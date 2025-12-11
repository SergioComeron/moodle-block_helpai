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
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'HelpAI';
$string['helpai:addinstance'] = 'Añadir un nuevo bloque HelpAI';
$string['helpai:myaddinstance'] = 'Añadir un nuevo bloque HelpAI al Área personal';
$string['helpai:askquestion'] = 'Hacer preguntas a HelpAI';

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
$string['openai_apikey_desc'] = 'Introduce tu clave API de OpenAI. Puedes obtener una en https://platform.openai.com/api-keys';
$string['openai_model'] = 'Modelo de OpenAI';
$string['openai_model_desc'] = 'Selecciona qué modelo de OpenAI usar. GPT-4o y GPT-4 Turbo soportan análisis de PDFs.';
$string['searchmode'] = 'Modo de búsqueda';
$string['searchmode_desc'] = 'Elige cómo buscar en los PDFs: Híbrido (búsqueda local primero, luego IA) o Solo IA (siempre usa IA, no necesita extraer texto)';
$string['searchmode_hybrid'] = 'Híbrido (local + IA) - Requiere pdftotext';
$string['searchmode_aionly'] = 'Solo IA (siempre usa IA) - No requiere pdftotext';

// Privacy.
$string['privacy:metadata'] = 'El bloque HelpAI no almacena ningún dato personal.';

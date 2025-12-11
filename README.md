# HelpAI Block for Moodle

Un bloque inteligente para Moodle que ayuda a los estudiantes a encontrar información en los documentos PDF del curso utilizando IA generativa.

## Características

- **Chat Inteligente**: Interfaz de chat estilo IA generativa
- **Dos Modos de Búsqueda**:
  - **AI Only**: Solo IA (NO requiere pdftotext) ✅ RECOMENDADO
  - **Hybrid**: Búsqueda local + IA (requiere pdftotext)
- **Referencias Directas**: No da la respuesta directamente, sino que indica en qué PDF está la información
- **Integración con AI Subsystem**: Utiliza el subsistema de IA de Moodle 4.5+
- **Flexible**: Funciona con o sin herramientas de extracción de texto
- **Caché Opcional**: Indexa y almacena el contenido de los PDFs para búsquedas rápidas (solo modo híbrido)

## Requisitos

### Requisitos Mínimos (Modo AI Only - RECOMENDADO)
- Moodle 4.5 o superior
- Subsistema de IA de Moodle habilitado
- Proveedor de IA configurado que soporte análisis de documentos:
  - ✅ OpenAI GPT-4 Turbo/GPT-4o (con visión)
  - ✅ Anthropic Claude 3 (Opus, Sonnet, Haiku)
  - ✅ Google Gemini Pro Vision
  - ❌ Modelos solo texto (no funcionará en modo AI only)

### Requisitos Adicionales (Modo Híbrido - OPCIONAL)
- Todo lo anterior +
- Herramienta `pdftotext` instalada en el servidor
- Espacio en base de datos para cache de PDFs

## Instalación

1. Copiar el directorio `helpai` a `blocks/helpai/`
2. Visitar la página de notificaciones de administración para completar la instalación
3. Configurar un proveedor de IA en: `Administración del sitio > IA > Gestionar proveedores de IA`
4. **Configurar modo de búsqueda** en: `Administración del sitio > Plugins > Bloques > HelpAI`
   - **Recomendado**: Seleccionar "AI only" (no requiere pdftotext)
   - Opcional: Seleccionar "Hybrid" si tienes pdftotext instalado

## Instalación de pdftotext (SOLO para modo híbrido)

Para mejorar la extracción de texto de PDFs:

### En Ubuntu/Debian:
```bash
sudo apt-get install poppler-utils
```

### En CentOS/RHEL:
```bash
sudo yum install poppler-utils
```

### En macOS:
```bash
brew install poppler
```

## Uso

1. Activar la edición en un curso
2. Añadir el bloque "HelpAI" a la página del curso
3. Los estudiantes pueden hacer preguntas sobre el contenido de los PDFs
4. El asistente de IA les indicará en qué PDF pueden encontrar la información

## Configuración del Subsistema de IA

1. Ir a: `Administración del sitio > IA > Gestionar proveedores de IA`
2. Habilitar y configurar un proveedor (por ejemplo, OpenAI):
   - Introducir la API key
   - Configurar el modelo a utilizar
   - Establecer los límites de uso
3. Ir a: `Administración del sitio > IA > AI Placements`
4. Asegurarse de que el placement está habilitado para el contexto de curso

## Permisos

El plugin define las siguientes capacidades:

- `block/helpai:addinstance` - Añadir el bloque a un curso
- `block/helpai:myaddinstance` - Añadir el bloque al área personal
- `block/helpai:askquestion` - Hacer preguntas al asistente de IA

Por defecto, los estudiantes tienen permiso para hacer preguntas.

## Cómo funciona

### Sistema de Búsqueda Inteligente

El plugin utiliza un sistema híbrido de búsqueda para minimizar costes de IA:

#### 1. Indexación Automática
- Los PDFs se indexan automáticamente al hacer la primera pregunta
- Una tarea programada (ejecutada a las 2:00 AM) mantiene el índice actualizado
- El contenido de los PDFs se extrae una sola vez y se almacena en caché

#### 2. Búsqueda Local (Sin coste de IA)
Cuando un estudiante hace una pregunta:
1. El sistema extrae palabras clave de la pregunta
2. Busca esas palabras en el índice de PDFs
3. Si encuentra coincidencias, devuelve los PDFs relevantes **sin usar IA**

#### 3. IA Solo para Consultas Complejas
Si la búsqueda local no encuentra resultados:
1. Se envía la pregunta al proveedor de IA configurado
2. La IA analiza el contenido cacheado de los PDFs
3. Identifica qué PDF contiene la información
4. Devuelve enlaces directos a los PDFs

### Ventajas del Sistema de Caché

✅ **Rápido**: Las búsquedas locales son instantáneas
✅ **Económico**: ~80-90% de preguntas se responden sin usar IA
✅ **Escalable**: No hay límites de peticiones a APIs externas
✅ **Fiable**: Funciona aunque el proveedor de IA no esté disponible

## Tarea Programada

El plugin incluye una tarea programada que se ejecuta diariamente a las 2:00 AM para:
- Indexar nuevos PDFs añadidos a los cursos
- Mantener el índice actualizado

Para ejecutar la tarea manualmente:
```bash
php admin/cli/scheduled_task.php --execute='\\block_helpai\\task\\index_pdfs'
```

## Limitaciones conocidas

- La extracción de texto PDF requiere herramientas externas (`pdftotext`)
- Los PDFs escaneados sin OCR pueden no tener texto extraíble
- La búsqueda local funciona mejor con palabras clave específicas
- Las respuestas de IA pueden variar según el proveedor configurado

## Mejoras futuras

- [x] Cache de contenido PDF extraído
- [x] Indexación de contenido para búsquedas más rápidas
- [ ] Soporte para otros formatos de documento (DOCX, PPTX)
- [ ] Historial de conversaciones
- [ ] Análisis de uso y estadísticas
- [ ] Integración con OCR para PDFs escaneados
- [ ] Búsqueda semántica con embeddings

## Soporte

Para reportar problemas o sugerir mejoras, por favor contacte con el equipo de desarrollo.

## Licencia

GPL v3 o posterior

## Créditos

Desarrollado para Moodle 4.5+

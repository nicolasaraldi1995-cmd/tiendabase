# Cómo cargar tu catálogo desde una planilla

Junto con tu tienda recibiste el archivo **`plantilla-catalogo.xlsx`**. Es la planilla modelo: la llenás con tus productos y la subís a la tienda, que la lee directo, sin configurar nada. Podés volver a subirla cada vez que cambien los precios: la tienda **actualiza** los productos que ya existen, no los duplica.

## Las reglas del archivo (también están adentro, en la hoja "Como llenar")

- Cargá todo en la hoja **Productos**, debajo de los títulos. No cambies los títulos.
- **Una fila por producto y por presentación**: si la yerba viene en 500g y en 1kg, son dos filas con el mismo nombre y la misma marca.
- Obligatorios: **nombre** y **marca**. Si tus productos no tienen marca, usá el nombre de tu negocio como marca.
- **precio**: solo el número, sin el signo $ (vale con coma decimal: `3500,50`).
- **precio_mayorista**: el precio para clientes mayoristas. Vacío si no vendés por mayor.
- **cantidad_mayorista**: si querés que además cualquier cliente pague ese precio al llevar cierta cantidad, poné el número (ej: `6`). Vacío = solo los negocios.
- **etiquetas / congelado / nuevo**: poné `si` cuando corresponda o dejá vacío. Si tu rubro no es de alimentos, ignorá esas columnas.

## Opción A — Llenarla a mano (o pedirla al proveedor)

Si tu lista es corta o ya la tenés ordenada, copiá los datos a la planilla y listo. Muchos proveedores y distribuidores te pueden entregar la lista directamente en este formato si les mandás el archivo.

## Opción B — Tu lista es un lío: convertila con una inteligencia artificial

Si tu lista de precios viene en un formato raro (un PDF, una foto, un Excel con los precios de cada tamaño en columnas distintas, todo mezclado), no la acomodes a mano: usá cualquier chat de IA gratuito — **ChatGPT** (chatgpt.com), **Claude** (claude.ai) o **Gemini** (gemini.google.com) — para que te la convierta.

1. Abrí el chat y **adjuntá tu lista** (el Excel, el PDF o la foto) o pegá el texto.
2. Copiá y pegá este mensaje tal cual:

> Convertí esta lista de precios a una tabla con exactamente estas columnas, en este orden: **nombre, marca, categoria, unidad, precio, precio_mayorista, cantidad_mayorista, stock, etiquetas, nuevo**.
> Reglas:
> - Una fila por producto y por presentación: si un producto viene en varios tamaños o formatos, hacé una fila por cada uno, repitiendo nombre y marca.
> - En precio y precio_mayorista poné solo el número, sin el signo $. Si mi lista no distingue precio mayorista, dejá esa columna vacía.
> - En etiquetas y nuevo poné "si" solo si corresponde; si no, dejá la celda vacía.
> - Si un dato no aparece en mi lista, dejá la celda vacía. No inventes datos.
> - Si mis productos no tienen marca, usá "[NOMBRE DE TU NEGOCIO]" como marca en todas las filas.
> - Asigná a cada producto una categoría razonable y usá siempre las mismas categorías (no crees veinte parecidas).
> - Devolveme el resultado como tabla, para copiar y pegar en Excel.

3. **Revisá el resultado** (que las categorías tengan sentido, que los precios estén bien) — la IA se puede equivocar.
4. Copiá la tabla que te devolvió y pegala en la hoja **Productos** de `plantilla-catalogo.xlsx`, debajo de los títulos. Guardá el archivo.

Si la lista es muy larga, pasásela por partes ("seguí con las siguientes 100 filas") y pegá cada tanda debajo de la anterior.

## Subir la planilla a la tienda

1. Entrá al panel → **Herramientas → Importador**.
2. Subí el archivo. Fila de encabezados: **1**.
3. En el paso de mapeo no tenés que tocar nada (las columnas se reconocen solas) → **Ver preview**.
4. Revisá la vista previa: cuántas filas, qué marcas y categorías nuevas se van a crear.
5. **Importar todo.** Si alguna fila viene mal, se salta solo esa y al final te muestra el detalle.

Las **fotos** de los productos se cargan después, desde el panel, producto por producto.

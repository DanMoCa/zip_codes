# API de Códigos Postales de Mexico

***[Freeze Frame - Scratch Record]***

Hola, te preguntarás como llegue aquí, bueno deja te explico como funciona esto 😎 abrocha tu cinturón y prepárate para una lectura de 10-15 minutos.

___

## El problema 🤔💭
Para el desarrollo de esta API se me presentaron 3 problemas.

- Popular una base de datos con la información recopilada por [Correos de México](https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx)
- Utilizar Laravel Framework (como buen artesano)
- Crear un endpoint `[GET] /api/zip-codes/{zip_code}` e imprimir la información con la siguiente sintaxis y con tiempos de respuesta ***rápidos***
 
```php
{
    "zip_code": "string",
    "locality": "string|empty",
    "federal_entity": {
        "key": "int",
        "name": "string",
        "code": "string|null"
    },
    "settlements": [
        {
            "key": "int",
            "name": "string",
            "zone_type": "string",
            "settlement_type" : {
                "name": "string"
            }
        }
    ],
    "municipality": {
        "key": "int",
        "name": "string"
    }
}
```

"Pan comido" me dije a mí mismo... hasta que revise la estructura de los datos fuente...

![Oh Dear Lord](https://media.giphy.com/media/3o6Mbh1R3ZApb4sZby/giphy.gif)

___

## Setup del proyecto 💻

Bendito sea Taylor Otwell por darnos Laravel Sail y el instalador a través de curl, y más porque mi ambiente de desarrollo es Windows, si no fuera por esta joya estaría 
desperdiciando más tiempo con setups de PHP / versions y demás cosas, el futuro con Sail es ahora viejo, XAMPP ya paso de moda.

```shell
curl -s https://laravel.build/zip-codes | bash
cd zip-codes
./vendor/bin/sail up -d
```

Agregado a eso, y considerando que es una API sencilla, considere usar Laravel Breeze con el setup de API, puesto que no se necesita nada de Front para este reto.

```shell
sail artisan breeze:install api
```

---

## El diseño de la base de datos 👨‍🎨

Verán, por facilidad de impresión o recopilación, o no sé que mente maniaca desarrollo esto en correos de México, decidió en 3 tipos de archivos
.xml, .xls y .txt... al revisar los 3 archivos opte por el archivo .txt, solo por el hecho de que era un archivo mucho más ligero que los otros dos

```php
    archivo.txt - 14.2 MB
    archivo.xls - 42.6 MB
    archivo.xml - 61.1 MB
```

El siguiente paso fue tratar de hacer match de los campos del archivo fuente con posibles entidades / modelos para un manejo fácil (larga vida a las RDBMS) y definir los campos que necesitaría

- **FederalEntity**
  * id increments
  * key unsignedBigInteger
  * name string
  * code string|nullable
- **Municipality**
  * id increments
  * key unsignedBigInteger
  * name string
  * federal_entity_id foreignKey refers FederalEntity.id 
- **SettlementType**
  * id unsignedBigInteger
  * name string
- **ZipCode**
  * id increments
  * zip_code string|index
  * locality string|""
  * municipality_id foreignKey refers Municipality.id
- **Settlement**
  * id increments 
  * key unsignedBigInteger
  * name string
  * zone_type string
  * settlement_type_id foreignKey refers SettlementType.id
  * zip_code foreignKey refers zip-codes.zip_code

¿Simple cierto? Bueno esa fue mi decision para poder aprovechar el poder que ofrece Laravel con MVC. 

## Creación del M~~V~~C y más setup ⌨

Primero los Modelos junto con sus migraciones y recursos

```shell
sail artisan make:FederalEntity --migration --resource
```
Dos Controladores, uno para importar el archivo y otro para los zip codes
```shell
sail artisan make:controller ImportController
sail artisan make:controller ZipCodeController
```

Modifique cada uno de los modelos con sus respectivos `$fillable`
```php
protected $fillable = ['...'];
```
Sin olvidar el indicar que ningún modelo utilizaría `timestamps`
```php
protected $timestamps = false;
```
Removiendo el auto incremento al modelo `SettlementType.php`
```php
//App/Models/SettlementType.php

protected $incrementing = false;
```

Modificando el modelo `ZipCode.php` para resolver de forma explícita el campo a utilizar cuando se busque por el URL parameter del endpoint
```php
//App/Models/ZipCode.php

public function resolveRouteBinding($value, $field = null)
{
    return $this->where('zip_code', $value)->firstOrFail();
}
```

Modificar la funciona `toArray()` de los recursos para que imprimieran solo los datos necesarios y estructura indicada
```php
//App/Http/Resources/ZipCodeResource.php

public function toArray(Request $request): array
{
    return [
        'zip_code' => $this->zip_code,
        'locality' =>  $this->locality,
        'federal_entity' => FederalEntityResource::make($this->federal_entity),
        'settlements' => SettlementResource::collection($this->settlements),
        'municipality' => MunicipalityResource::make($this->municipality)
    ];
}
```

Definir las rutas en `api.php` para utilizar los controladores invocables

```php
// routes/api.php

Route::post('import',ImportController::class)->middleware('auth:sanctum');
Route::get('zip-codes/{zip_code}',ZipCodeController::class);
```

Y modifique el archivo `app/Providers/AppServiceProvider.php` para asegurarme de no usar LazyLoading el momento de desarrollar y eliminar los Wrappings de los recursos.

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    Model::preventLazyLoading(!app()->isProduction());
    JsonResource::withoutWrapping();
}
```

Y listo... el setup inicial estaba terminado, seguía lo bueno.

---

## Lectura del archivo fuente 📄

Empece con el controlador invocable `ImportController.php` para la importación de mis archivos (pude haber hecho un comando y subir el archivo el proyecto, 
pero decidí por un controlador y un endpoint para fácil mantenimiento de la información fuente)

Solo resaltaré lo más importante de esta función, el código completo lo encontrarás [aquí](/app/Http/Controllers/ImportController.php) 

Primero, validar el archivo de texto para leer los archivos a insertar

```php
$request->validate([
    'file' => 'required|file|mimetypes:text/plain',
]);
```

Abrir el archivo de texto y leer los contenidos directamente sin necesidad de guardar el archivo y crear un arreglo con cada una de las líneas del archivo.
```php
$file = $request->file('file');

// Read the contents of the file
$handle = fopen($file,'r');
$contents = stream_get_contents($handle);

// Close the file
fclose($handle);

// Split the contents into an array of lines, without accent marks
$lines = explode("\n", iconv("ISO-8859-1", "ASCII//TRANSLIT//IGNORE", $contents));
```

Iterar por cada línea e ignorar las primeras dos líneas del archivo, para después explotarlas en un arreglo.
```php
// Set counter variable for line_num
$line_num = 0;

foreach($lines as $line){
    if ($line_num < 2) {
        $line_num++;
        continue;
    }
    
    // Trim whitespace from the line
    $line = trim($line);

    // Split the line into an array of data by pipes (|)
    $data = explode("|", $line);

    // Skip empty lines
    if($data[0] == ""){
        continue;
    }
}
```

Subsecuentemente, guarde la información para cada modelo en unos arreglos antes de hacer el insert masivo a la base de datos, si lo hubiera hecho de uno por uno hubiera sido ridículamente costoso en recursos. Una vez teniendo mis arreglos solo me restaba insertarlos por `tantos`

```php
// Set batch size
$batchSize = 1000;

...

// insert Settlements
foreach (array_chunk($settlements, $batchSize) as $chunk) {
    Settlement::insert($chunk);
}
```
Y voila, con esto ya tenía un controlador y un endpoint que me servirían para actualizar la base de datos de una forma sencilla y practica con un llamado a un endpoint 😎

---

## Retornar la información del endpoint 👍

Lo último que quedaba por hacer, era la lógica del controlador `ZipCodeController.php`, la cual, habiendo hecho todo lo anterior, no era más que dos líneas de código en mi método `__invoke`

```php
// App/Http/Controllers/ZipCodeController.php

public function __invoke(ZipCode $zip_code)
{
    $zip_code = $zip_code->loadMissing(['settlements','municipality','federal_entity','settlements.settlement_type']);
    return ZipCodeResource::make($zip_code);
}
```

Y asi, el endpoint `[GET] /api/zip-codes/{zip_code}` ya es funcional, cubriendo los puntos del problema.

¡Era hora de las pruebas de velocidad de respuesta!

---
## Resultados ✅

Los tiempos de respuesta promedio fueron


- localhost 
  * tiempo promedio 40-60 MS
   
- Droplet de digital ocean 1GB memoria
  * tiempo promedio 200-400 ms

___
## Conclusion 🧐

Fue un reto divertido, lo más desafiante fue descifrar la relación entre las columnas del archivo fuente y donde hacer el match con los modelos, pero nada que una noche de cafe en Denny's u el mejor sandwich de brisket no puedan ayudar a resolver (Denny's patrocíname)

Pero el dominio del Framework sin problema alguno 😋
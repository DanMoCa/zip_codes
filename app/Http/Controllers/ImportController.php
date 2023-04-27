<?php

namespace App\Http\Controllers;

use App\Models\FederalEntity;
use App\Models\Municipality;
use App\Models\Settlement;
use App\Models\SettlementType;
use App\Models\ZipCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function __invoke(Request $request)
    {
        // Validate that a file was uploaded and that it is a text file
        $request->validate([
            'file' => 'required|file|mimetypes:text/plain',
        ]);

        // Get the file from the request
        $file = $request->file('file');

        // Read the contents of the file
        $handle = fopen($file,'r');
        $contents = stream_get_contents($handle);
        // Close the file
        fclose($handle);

        // Split the contents into an array of lines without accent marks
        $lines = explode("\n", iconv("ISO-8859-1", "ASCII//TRANSLIT//IGNORE", $contents));

        // Set counter variable for line_num
        $line_num = 0;

        // Set prev variables to keep track of previous values
        $prevFederalKey = '';
        $prevMunicipalityKey = '';
        $prevSettlementName = '';
        $prevZipCode = '';

        // Set arrays to store data in before inserting into the database.
        $federalEntities =  [];
        $municipalities = [];
        $settlementTypes = [];
        $zipCodes = [];
        $zipCodeEmptyLocality = [];
        $settlements = [];

        // Set variables to store ids
        $federalEntityId = 0;
        $municipalityId = 0;

        try {
            // Loop through each line of the file
            foreach ($lines as $line) {
                // Skip first two lines of the file
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

                // Check if it is a new federal entity
                if($prevFederalKey == '' || $data[7] != $prevFederalKey)
                {
                    $federalEntityId++;
                    $prevFederalKey = $data[7];

                    // Push new object into the federalEntities array
                    $federalEntities[] = [
                        'key' => intval($data[7]),
                        'name' => strtoupper($data[4]),
                        'code' => $data[9] == '' ? null : intval($data[9])
                    ];
                }

                // Check if it is a new municipality
                if($prevMunicipalityKey == '' || $data[11] != $prevMunicipalityKey)
                {
                    $municipalityId++;
                    $prevMunicipalityKey = $data[11];

                    // Insert new object into the municipalities array
                    $municipalities[] = [
                        'key' => intval($data[11]),
                        'name' => strtoupper($data[3]),
                        'federal_entity_id' => $federalEntityId
                    ];
                }

                // Check if it is a new settlement type
                if($prevSettlementName == '' || $data[2] != $prevSettlementName)
                {
                    $prevSettlementName = $data[2];

                    // Insert new object into the settlementTypes array
                    $settlementTypes[] = [
                        'id' => intval($data[10]),
                        'name' => $data[2]
                    ];
                }

                // Check if it is a new zip code
                if($prevZipCode == '' || $prevZipCode != $data[0])
                {
                    $prevZipCode = $data[0];

                    // Check if the locality is empty
                    // Workaround for issue with mass inserting empty localities.
                    if(empty($data[5])){
                        // Insert new object into the zipCodeEmptyLocality array if locality is empty
                        $zipCodeEmptyLocality[] = [
                            'zip_code' => $data[0],
                            'municipality_id' => $municipalityId,
                        ];
                    }else{
                        // Insert new object into the zipCodes array if there's a locality
                        $zipCodes[] = [
                            'zip_code' => $data[0],
                            'locality' => strtoupper($data[5]),
                            'municipality_id' => $municipalityId,
                        ];
                    }
                }

                // Create the Settlement
                $settlements[] = [
                    'key' => intval($data[12]),
                    'name' => strtoupper($data[1]),
                    'zone_type' => strtoupper($data[13]),
                    'zip_code' => $data[0],
                    'settlement_type_id' => intval($data[10])
                ];
            }

            // Set batch size
            $batchSize = 1000;

            // insert Federal Entities
            FederalEntity::insert($federalEntities);

            // insert Municipalities
            Municipality::insert($municipalities);

            // insert Settlement Types
            //remove duplicates in the $settlementTypes array
            $settlementTypes = array_values(array_unique($settlementTypes, SORT_REGULAR));
            SettlementType::insert($settlementTypes);

            // insert Zip Codes
            foreach (array_chunk($zipCodes, $batchSize) as $chunk) {
                ZipCode::insert($chunk);
            }

            // insert Zip Codes with empty locality
            foreach (array_chunk($zipCodeEmptyLocality, $batchSize) as $chunk) {
                ZipCode::insert($chunk);
            }

            // insert Settlements
            foreach (array_chunk($settlements, $batchSize) as $chunk) {
                Settlement::insert($chunk);
            }
        }catch(\Exception $e){
            return $e;
        }

        // Return the number of rows inserted into the database
        return [
            'federal_entities' => count($federalEntities),
            'municipalities' => count($municipalities),
            'settlement_types' => count($settlementTypes),
            'zip_codes' => count($zipCodes),
            'zip_code_empty_locality' => count($zipCodeEmptyLocality),
            'settlements' => count($settlements)
        ];
    }
}

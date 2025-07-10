<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Helpers\ApiResponseHelper;

class RegionController
{
  public function GetAllProvinces()
  {
    $provinces = Province::all();
    return ApiResponseHelper::respond(
      $provinces,
      'Successfully retrieved list of provinces',
      200
    );
  }

  public function GetRegenciesByProvinceId($provinceId)
  {
    $regencies = Regency::where('province_id', $provinceId)->get();

    if ($regencies->isEmpty()) {
      return ApiResponseHelper::respond(
        null,
        'Regencies not found',
        404
      );
    }

    return ApiResponseHelper::respond(
      $regencies,
      'Successfully retrieved list of regencies',
      200
    );
  }

  public function GetDistrictsByRegencyId($regencyId)
  {
    $districts = District::where('regency_id', $regencyId)->get();

    if ($districts->isEmpty()) {
      return ApiResponseHelper::respond(
        null,
        'Districts not found',
        404
      );
    }

    return ApiResponseHelper::respond(
      $districts,
      'Successfully retrieved list of districts',
      200
    );
  }

  public function GetVillagesByDistrictId($districtId)
  {
    $villages = Village::where('district_id', $districtId)->get();

    if ($villages->isEmpty()) {
      return ApiResponseHelper::respond(
        null,
        'Villages not found',
        404
      );
    }

    return ApiResponseHelper::respond(
      $villages,
      'Successfully retrieved list of villages',
      200
    );
  }

  public function GetDomicileByVillageId($villageId)
  {
    $village = Village::with('district.regency.province')->find($villageId);

    if (!$village) {
      return ApiResponseHelper::respond(
        null,
        'Domicile data not found',
        404
      );
    }

    $provinceName = $village->district->regency->province->name;
    $regencyName = $village->district->regency->name;
    $districtName = $village->district->name;
    $villageName = $village->name;
    $fullAddress = "$villageName, $districtName, $regencyName, $provinceName";

    return ApiResponseHelper::respond(
      [
        'province' => $provinceName,
        'regency' => $regencyName,
        'district' => $districtName,
        'village' => $villageName,
        'full_address' => $fullAddress,
      ],
      'Successfully retrieved full domicile address',
      200
    );
  }


}
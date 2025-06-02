<?php

	namespace App\Http\Controllers\Api\V1;

	use App\Http\Controllers\Api\BaseController;
    use App\Models\Country;
    use App\Models\Gender;
    use App\Models\Interest;
    use App\Models\Language;

    class MasterController extends BaseController
	{
        public function languages()
        {
            $languages = Language::orderBy('language_order')->get();

            return $this->sendResponse(TRUE, 'Languages data get successfully', $languages);
        }

        public function countries()
        {
            $countries = Country::orderBy('country_order')->get();

            return $this->sendResponse(TRUE, 'Countries data get successfully', $countries);
        }

        public function interests()
        {
            $interests = Interest::orderBy('category_order')->get();

            return $this->sendResponse(TRUE, 'Interests data get successfully', $interests);
        }

        public function genders()
        {
            $genders = Gender::orderBy('gender_order')->get();

            return $this->sendResponse(TRUE, 'Genders data get successfully', $genders);
        }
	}

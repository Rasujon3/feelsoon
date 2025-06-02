<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class ReportCategory extends Model
    {
        protected $table = 'reports_categories';

        protected $fillable = [
            'category_id',
            'category_parent_id',
            'category_name',
            'category_description',
            'category_order'
        ];

        public function children()
        {
            return $this->hasMany(self::class, 'category_parent_id', 'category_id')->with('children');
        }
    }

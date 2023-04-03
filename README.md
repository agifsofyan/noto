## About Noto

Noto is a package intended to help synchronize filesystems between [OCTOBERCMS](https://octobercms.com) and [LARAVEL](https://laravel.com). By following the octobercms filesystem flow.
Package includes:
- file relations
- use of system_files table
- naming the path / disk name of the file
- the naming of the relation model of the [OCTOBERCMS](https://octobercms.com) model

## Requirement

- PHP 8.0.28
- [LARAVEL](https://laravel.com) Framework 9.52.5

## Dependency

- intervention/image

## Installation

Run ``composer require agifsofyan/noto``

## Configuration

Run ``php artisan vendor:publish --provider="Agifsofyan\Noto\Providers\NotoServiceProvider"``

Will create :
* config file `noto.php` in the *config* folder. 
  
    ```php
    <?php

        return [
            'model_path' => 'App\Models',
            'file_table' => 'system_files',
            'model_sync' => [
                'User' => 'RainLab\User\Models\User'
            ],
            'extention' => ['jpg', 'jpeg', 'png', 'gif', 'docx', 'xlsx', 'svg', 'pdf']
        ];
    ```

  - `model_path` is the path of the [LARAVEL](https://laravel.com) model used
  - `file_table` is the database table name for the file to be used. The default for [OCTOBERCMS](https://octobercms.com) is system_files.
  - `model_sync` are the registered models. And this is mandatory.
  - index (left side) of model_sync is the model name of your **[LARAVEL](https://laravel.com)** project.
  - the value (right side) of model_sync is the model name of your **[OCTOBERCMS](https://octobercms.com)** project.

* migration file `2023_01_31_000001_Db_System_Files.php` in the *database/migrations* folder.
  
  Run ``php artisan migrate``
  
## Use

* Adding `use Agifsofyan\Noto\Traits\NotoMorph;` in your model as Traits.

  Sample:
  ```php
    <?php

    namespace App\Models;

    use Agifsofyan\Noto\Traits\NotoMorph;

    class User
    {
        use NotoMorph;
  ```
  
* Adding file relation using ``morphOneNoto`` in your model if single upload file.

  Sample:
  ```php
    public function avatar()
    {
        return $this->morphOneNoto('avatar');
    }
  ```
  
* Adding file relation using ``morphManyNoto`` in your model if multiple upload file.

  Sample:
  ```php
    public function avatars()
    {
        return $this->morphManyNoto('avatars');
    }
  ```
  
* To Save file use this from your controller after save / update the model data:
  Sample:
  ```php
  
    $user->create($data); // To create new data
    or
    $user->save(); // To create new data or update the data

    $field = 'avatar';
    $fields = 'avatars';

    if($request->hasFile($field) && $request->file($field)->isValid()){
        $user->saveOneFile($request->file($field), $field, $user->id);
    }

    if($request->hasFile($fields) && $request->file($fields)->isValid()){
        $user->saveManyFile($request->file($fields), $fields, $user->id);
    }
  ```
  ``$user`` from your model where the ``morphOneNoto`` or ``morphManyNoto`` relations are registered.
  
  If update data use ``$user->save()`` don't use ``$user->update()``.

* Call the File Url
  
  Call the thumbnail file:
  ```php
  $user?->avatar()?->getThumb(150, 150)
  ```
  
  Call the original file:
  ```php
  $user?->avatar()?->getPath()
  ```

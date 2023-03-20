<?php

namespace Agifsofyan\Noto\Traits;

use Agifsofyan\Noto\Models\NotoModel;
use Illuminate\Database\Eloquent\Concerns\HasRelationships;

trait NotoMorph {

    use HasRelationships;

    protected $morphClass;

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  model|class  $related
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function morphOneNoto($name, $type = 'attachment_type', $id = 'attachment_id', $related = NotoModel::class)
    {
        return $this->getFile($related, $name, $type, $id);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  model|class  $related
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function morphManyNoto($name, $type = 'attachment_type', $id = 'attachment_id', $related = NotoModel::class)
    {
        return $this->getFile($related, $name, $type, $id, true);
    }

    protected function getMorphRelation()
    {   
        if($this->getCrossModel(static::class) != $this->morphClass){
            return $this->getCrossModel(static::class);
        }else{
            return $this->morphClass;
        }
    }

    public function getCrossModel($model)
    {
        $fileConfig = config('noto');
        
        $modelPath  = sprintf("%s\\", $fileConfig['model_path']);
        $modelSync  = $fileConfig['model_sync'];

        $modelName = str_replace($modelPath,'',$model);

        if(isset($modelSync[$modelName])) $model = $modelSync[$modelName];

        return $model;
    }

    /**
     * fromPost creates a file object from a file an uploaded file
     * @param file $uploadedFile
     * @param string $name
     * @param int $modelId
     * @param string $type
     * @param string $id
     * @param path_string|class $related
     */
    public function saveOneFile($uploadedFile, $name, $modelId, $type = 'attachment_type', $id = 'attachment_id', $related = NotoModel::class)
    {   
        $fileModel = $this->getFile($related, $name, $type, $id);
        
        if(!$fileModel){
            $fileModel = new $related;
        }
        
        $fileModel->file_name     = $uploadedFile->getClientOriginalName();
        $fileModel->file_size     = $uploadedFile->getSize();
        $fileModel->content_type = $uploadedFile->getMimeType();
        $fileModel->disk_name    = $fileModel->getDiskName();
        $fileModel->field         = $name;
        $fileModel->$type        = (string) $this->getMorphRelation();
        $fileModel->$id          = $modelId;
        
        // getRealPath() can be empty for some environments (IIS)
        $realPath = empty(trim($uploadedFile->getRealPath()))
        ? $uploadedFile->getPath() . DIRECTORY_SEPARATOR . $uploadedFile->getFileName()
        : $uploadedFile->getRealPath();

        if($fileModel->save()){
            
            if($fileModel->hasFile()){
                $fileModel->deleteFile();
            }
            
            $fileModel->putFile($realPath, $fileModel->getDiskName());
        }

        return $fileModel;
    }

    /**
     * fromPost creates a file object from a file an uploaded file
     * @param array|files $uploadedFile
     * @param string $name
     * @param int $modelId
     * @param string $type
     * @param string $id
     * @param path_string|class $related
     */
    public function saveManyFile($uploadedFile, $name, $modelId, $type = 'attachment_type', $id = 'attachment_id', $related = NotoModel::class)
    {
        $fileModels = $this->getFile($related, $name, $type, $id, true);
        
        foreach ($uploadedFile as $key => $file) {

            if(isset($fileModels[$key])){
                $fileModel = $fileModels[$key];
            }else{
                $fileModel = new $related;
            }

            $fileModel->file_name     = $file->getClientOriginalName();
            $fileModel->file_size     = $file->getSize();
            $fileModel->content_type = $file->getMimeType();
            $fileModel->disk_name    = $fileModel->getDiskName();
            $fileModel->field         = $name;
            $fileModel->$type        = (string) $this->getMorphRelation();
            $fileModel->$id          = $modelId;
            
            // getRealPath() can be empty for some environments (IIS)
            $realPath = empty(trim($file->getRealPath()))
            ? $file->getPath() . DIRECTORY_SEPARATOR . $file->getFileName()
            : $file->getRealPath();
    
            if($fileModel->save()){
                
                if($fileModel->hasFile()){
                    $fileModel->deleteFile();
                }
                
                $fileModel->putFile($realPath, $fileModel->getDiskName());
            }
        }
    }

    public function getFile($model, $field, $type = 'attachment_type', $id = 'attachment_id', $list = false)
    {
        if(!$this->id) return null;

        $query = $model::where($id, $this->id)->where('field', $field);

        if(!is_null($model)){
            $modelPath = $this->getMorphRelation();
            
            if(!is_null($modelPath)){
                $query = $query->where($type, $modelPath);
            }
        }

        if($list === true){
            return $query->get();

        }else{
            return $query->first();
        }
    }
}
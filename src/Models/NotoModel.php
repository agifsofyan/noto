<?php

namespace Agifsofyan\Noto\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File as FileHelper;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Agifsofyan\Noto\Helpers\Resizer;

/**
 * File attachment model
 *
 * @package october\system
 * @author Alexey Bobkov, Samuel Georges
 */
class NotoModel extends Model
{
    use HasFactory;

    /**
     * @var string table in database used by the model
     */
    protected $table = 'system_files';

    /**
     * @var array morphTo relation
     */
    public function attachment()
    {
        return $this->morphTo();
    }

    /**
     * @var array fillable attributes are mass assignable
     */
    protected $fillable = [
        'disk_name',
        'file_name',
        'file_size',
        'content_type',
        'title',
        'description',
        'field',
        'attachment_id',
        'attachment_type',
        'is_public',
        'sort_order'
    ];

    /**
     * @var array guarded attributes aren't mass assignable
     */
    protected $guarded = [];

    /**
     * @var array imageExtensions known
     */
    public static $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @var array hidden fields from array/json access
     */
    // protected $hidden = ['attachment_type', 'attachment_id', 'is_public'];

    /**
     * @var array appends fields to array/json access
     */
    protected $appends = ['path', 'extension'];

    /**
     * @var mixed data is a local file name or an instance of an uploaded file,
     * objects of the UploadedFile class.
     */
    public $data = null;

    /**
     * @var array autoMimeTypes
     */
    protected $autoMimeTypes = [
        'docx' => 'application/msword',
        'xlsx' => 'application/excel',
        'gif'  => 'image/gif',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'svg'  => 'image/svg+xml',
    ];

    private function setOctoberModel($model)
    {
        $octoPath = null;

        $fileConfig = config('filesystems.noto');
        $modelPath  = sprintf("%s\\", $fileConfig['model_path']);
        $modelSync  = $fileConfig['model_sync'];

        $modelName = str_replace($modelPath,'',$model);

        if(isset($modelSync[$modelName])) $octoPath = $modelSync[$modelName];

        return $octoPath;
    }

    public function getFileByAttachmentId($model, $field, $list = false, $fullData = false)
    {
        if(!$model->id) return $this->getFileObj(null);

        $query = static::where('attachment_id', $model->id)->where('field', $field);

        if(!is_null($model)){
            $modelPath = $this->setOctoberModel(get_class($model));

            if(!is_null($modelPath)){
                $query = $query->where('attachment_type', $modelPath);
            }
        }

        if($list === true){
            $result = $query->get();


            if($fullData === true) return $result;

            if(sizeof($result) > 0){
                return $result->map(function($val){
                    return $this->getFileObj($val);
                });
            }else{
                return $result;
            }

        }else{
            $result = $query->first();

            if($fullData === true) return $result;

            return $this->getFileObj($result);
        }
    }

    private function getFileObj($media, $width = 190, $height = 190)
    {
        return (object) [
            'original' => !$media ? null : $media->getPath(),
            'thumbnail' => !$media ? null : $media->getThumb($width, $height)
        ];
    }

    /**
     * fromPost creates a file object from a file an uploaded file
     * @param UploadedFile $uploadedFile
     * @return $this
     */
    public function fromPost($uploadedFile, $model, $field)
    {
        if ($uploadedFile === null || !$model->id) return;

        $modelPath = $this->setOctoberModel(get_class($model));

        $fileData = self::whereNotNull('attachment_id')
            ->where('attachment_id', $model->id)
            ->where('attachment_type', $modelPath)
            ->first();
            
        $this->file_name = $uploadedFile->getClientOriginalName();
        $this->file_size = $uploadedFile->getSize();
        $this->content_type = $uploadedFile->getMimeType();
        $this->disk_name = $this->getDiskName();
        $this->field = $field;
        $this->attachment_id = $model->id;
        $this->attachment_type = (string) $modelPath;

        // getRealPath() can be empty for some environments (IIS)
        $realPath = empty(trim($uploadedFile->getRealPath()))
        ? $uploadedFile->getPath() . DIRECTORY_SEPARATOR . $uploadedFile->getFileName()
        : $uploadedFile->getRealPath();

        $args = collect($this)->except('path', 'extension')->toArray();

        if(!is_null($fileData)){
            $tempData = clone $fileData;
            $storeData = $fileData->update($args);
        }else{
            $tempData = null;
            $storeData = self::save($args);
        }

        if($storeData){
            $this->putFile($realPath, $this->disk_name);

            if(!is_null($tempData)) $tempData->deleteFile();
        }

        return $this->getDiskPath();
    }

    /**
     * getPathAttribute helper attribute for getPath
     * @return string
     */
    public function getPathAttribute()
    {
        return $this->getPath();
    }

    /**
     * getExtensionAttribute helper attribute for getExtension
     * @return string
     */
    public function getExtensionAttribute()
    {
        return $this->getExtension();
    }

    /**
     * setDataAttribute used only when filling attributes
     */
    public function setDataAttribute($value)
    {
        $this->data = $value;
    }

    /**
     * getWidthAttribute helper attribute for get image width
     * @return string
     */
    public function getWidthAttribute()
    {
        if ($this->isImage()) {
            $dimensions = $this->getImageDimensions();

            return $dimensions[0];
        }
    }

    /**
     * getHeightAttribute helper attribute for get image height
     * @return string
     */
    public function getHeightAttribute()
    {
        if ($this->isImage()) {
            $dimensions = $this->getImageDimensions();

            return $dimensions[1];
        }
    }

    /**
     * getSizeAttribute helper attribute for file size in human format
     * @return string
     */
    public function getSizeAttribute()
    {
        return $this->sizeToString();
    }

    //
    // Raw output
    //

    /**
     * output the raw file contents
     * @param string $disposition The Content-Disposition to set, defaults to inline
     * @param bool $returnResponse
     * @return Response|void
     */
    public function output($disposition = 'inline', $returnResponse = false)
    {
        $response = Response::make($this->getContents())->withHeaders([
            'Content-type' => $this->getContentType(),
            'Content-Disposition' => $disposition . '; filename="' . $this->file_name . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, pre-check=0, post-check=0, max-age=0',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $this->file_size,
        ]);

        if ($returnResponse) {
            return $response;
        }

        $response->sendHeaders();
        $response->sendContent();
    }

    /**
     * getCacheKey returns the cache key used for the hasFile method
     * @param string $path The path to get the cache key for
     * @return string
     */
    public function getCacheKey($path = null)
    {
        if (empty($path)) {
            $path = $this->getDiskPath();
        }

        return 'database-file::' . $path;
    }

    /**
     * getFilename returns the file name without path
     */
    public function getFilename()
    {
        return $this->file_name;
    }

    /**
     * getExtension returns the file extension
     */
    public function getExtension()
    {
        return FileHelper::extension($this->file_name);
    }

    /**
     * getLastModified returns the last modification date as a UNIX timestamp
     * @return int
     */
    public function getLastModified($fileName = null)
    {
        return $this->storageCmd('lastModified', $this->getDiskPath($fileName));
    }

    /**
     * getContentType returns the file content type
     */
    public function getContentType()
    {
        if ($this->content_type !== null) {
            return $this->content_type;
        }

        $ext = $this->getExtension();
        if (isset($this->autoMimeTypes[$ext])) {
            return $this->content_type = $this->autoMimeTypes[$ext];
        }

        return null;
    }

    /**
     * getContents from storage device
     */
    public function getContents($fileName = null)
    {
        return $this->storageCmd('get', $this->getDiskPath($fileName));
    }

    /**
     * getPath returns the public address to access the file
     */
    public function getPath($fileName = null)
    {
        if (empty($fileName)) {
            $fileName = $this->disk_name;
        }

        return $this->getPublicPath() . $this->getPartitionDirectory() . $fileName;
    }

    /**
     * getLocalPath returns a local path to this file. If the file is stored remotely,
     * it will be downloaded to a temporary directory.
     */
    public function getLocalPath()
    {
        if ($this->isLocalStorage()) {
            return $this->getLocalRootPath() . '/' . $this->getDiskPath();
        }

        $itemSignature = md5($this->getPath()) . $this->getLastModified();

        $cachePath = $this->getLocalTempPath($itemSignature . '.' . $this->getExtension());

        if (!FileHelper::exists($cachePath)) {
            $this->copyStorageToLocal($this->getDiskPath(), $cachePath);
        }

        return $cachePath;
    }

    /**
     * getDiskPath returns the path to the file, relative to the storage disk
     * @return string
     */
    public function getDiskPath($fileName = null)
    {
        if (empty($fileName)) {
            $fileName = $this->disk_name;
        }

        return $this->getStorageDirectory() . $this->getPartitionDirectory() . $fileName;
    }

    /**
     * isPublic determines if the file is flagged "public" or not
     */
    public function isPublic()
    {
        if (array_key_exists('is_public', $this->attributes)) {
            return $this->attributes['is_public'];
        }

        if (isset($this->is_public)) {
            return $this->is_public;
        }

        return true;
    }

    /**
     * sizeToString returns the file size as string
     * @return string Returns the size as string.
     */
    public function sizeToString()
    {
        return FileHelper::sizeToString($this->file_size);
    }

    /**
     * isImage checks if the file extension is an image and returns true or false
     */
    public function isImage()
    {
        return in_array(strtolower($this->getExtension()), static::$imageExtensions);
    }

    /**
     * getImageDimensions
     * @return array|bool
     */
    protected function getImageDimensions()
    {
        return getimagesize($this->getLocalPath());
    }

    /**
     * getThumb generates and returns a thumbnail path
     *
     * @param integer $width
     * @param integer $height
     * @param array $options [
     *     'mode' => 'auto',
     *     'offset' => [0, 0],
     *     'quality' => 90,
     *     'sharpen' => 0,
     *     'interlace' => false,
     *     'extension' => 'auto',
     * ]
     * @return string The URL to the generated thumbnail
     */
    public function getThumb($width, $height, $options = [])
    {
        if (!$this->isImage() || !$this->hasFile()) {
            return $this->getPath();
        }

        $width = (int) $width;
        $height = (int) $height;

        $options = $this->getDefaultThumbOptions($options);
        $thumbFile = $this->getThumbFilename($width, $height, $options);
        $thumbPath = $this->getDiskPath($thumbFile);
        $thumbPublic = $this->getPath($thumbFile);

        if (!$this->hasFile($thumbFile)) {
            try {
                if ($this->isLocalStorage()) {
                    $this->makeThumbLocal($thumbPath, $width, $height, $options);
                }else {
                    $this->makeThumbStorage($thumbPath, $width, $height, $options);
                }
            }catch (Exception $ex) {
                return '';
            }
        }

        return $thumbPublic;
    }

    /**
     * getThumbFilename generates a thumbnail filename
     * @return string
     */
    public function getThumbFilename($width, $height, $options)
    {
        $options = $this->getDefaultThumbOptions($options);
        return 'thumb_' . $this->id . '_' . $width . '_' . $height . '_' . $options['offset'][0] . '_' . $options['offset'][1] . '_' . $options['mode'] . '.' . $options['extension'];
    }

    /**
     * getDefaultThumbOptions returns the default thumbnail options
     * @return array
     */
    protected function getDefaultThumbOptions($overrideOptions = [])
    {
        $defaultOptions = [
            'mode' => 'crop',
            'offset' => [0, 0],
            'quality' => 90,
            'sharpen' => 0,
            'interlace' => false,
            'extension' => 'auto',
        ];

        if (!is_array($overrideOptions)) {
            $overrideOptions = ['mode' => $overrideOptions];
        }

        $options = array_merge($defaultOptions, $overrideOptions);

        $options['mode'] = strtolower($options['mode']);

        if (strtolower($options['extension']) === 'auto') {
            $options['extension'] = strtolower($this->getExtension());
        }

        return $options;
    }

    /**
     * makeThumbLocal generates the thumbnail based on the local file system. This step
     * is necessary to simplify things and ensure the correct file permissions are given
     * to the local files.
     */
    public function makeThumbLocal($thumbPath, $width, $height, $options = [])
    {
        $rootPath = $this->getLocalRootPath();
        $filePath = $rootPath.'/'.$this->getDiskPath();
        $thumbPath = $rootPath.'/'.$thumbPath;

        /*
         * Generate thumbnail
         */
        Resizer::open($filePath)
            ->resize($width, $height, $options)
            ->save($thumbPath)
        ;

        FileHelper::chmod($thumbPath);
    }

    /**
     * makeThumbStorage generates the thumbnail based on a remote storage engine
     */
    public function makeThumbStorage($thumbPath, $width, $height, $options = [])
    {
        $tempFile = $this->getLocalTempPath();

        /*
         * Generate thumbnail
         */
        try {
            $this->copyStorageToLocal($this->getDiskPath(), $tempFile);

            Resizer::open($tempFile)
            ->resize($width, $height, $options)
            ->save($tempFile);
        }finally{
            /*
            * Publish to storage and clean up
            */
            $this->copyLocalToStorage($tempFile, $thumbPath);

            FileHelper::delete($tempFile);
        }
    }

    /**
     * deleteThumbs deletes all thumbnails for this file
     */
    public function deleteThumbs()
    {
        $pattern = 'thumb_'.$this->id.'_';

        $directory = $this->getStorageDirectory() . $this->getPartitionDirectory();
        $allFiles = $this->storageCmd('files', $directory);
        $collection = [];
        foreach ($allFiles as $file) {
            if (starts_with(basename($file), $pattern)) {
                $collection[] = $file;
            }
        }

        /*
         * Delete the collection of files
         */
        if (!empty($collection)) {
            if ($this->isLocalStorage()) {
                FileHelper::delete($collection);
            }
            else {
                $this->getDisk()->delete($collection);

                foreach ($collection as $filePath) {
                    Cache::forget($this->getCacheKey($filePath));
                }
            }
        }
    }

    //
    // File handling
    //

    /**
     * getDiskName generates a disk name from the supplied file name
     */
    protected function getDiskName()
    {
        if ($this->disk_name !== null) {
            return $this->disk_name;
        }

        $ext = strtolower($this->getExtension());
        $name = str_replace('.', '', uniqid('', true));

        return $this->disk_name = !empty($ext) ? $name.'.'.$ext : $name;
    }

    /**
     * getLocalTempPath returns a temporary local path to work from
     */
    protected function getLocalTempPath($path = null)
    {
        if (!$path) {
            return $this->getTempPath() . '/' . md5($this->getDiskPath()) . '.' . $this->getExtension();
        }

        return $this->getTempPath() . '/' . $path;
    }

    /**
     * putFile saves a file
     * @param string $sourcePath An absolute local path to a file name to read from.
     * @param string $destinationFileName A storage file name to save to.
     */
    protected function putFile($sourcePath, $destinationFileName = null)
    {
        if (!$destinationFileName) {
            $destinationFileName = $this->disk_name;
        }

        $destinationPath = $this->getStorageDirectory() . $this->getPartitionDirectory();

        if (!$this->isLocalStorage()) {
            return $this->copyLocalToStorage($sourcePath, $destinationPath . $destinationFileName);
        }

        /*
         * Using local storage, tack on the root path and work locally
         * this will ensure the correct permissions are used.
         */
        $destinationPath = $this->getLocalRootPath() . '/' . $destinationPath;

        /*
         * Verify the directory exists, if not try to create it. If creation fails
         * because the directory was created by a concurrent process then proceed,
         * otherwise trigger the error.
         */
        if (
            !FileHelper::isDirectory($destinationPath) &&
            !FileHelper::makeDirectory($destinationPath, 0755, true, true) &&
            !FileHelper::isDirectory($destinationPath)
        ) {
            if (($lastErr = error_get_last()) !== null) {
                trigger_error($lastErr['message'], E_USER_WARNING);
            }
        }

        return FileHelper::copy($sourcePath, $destinationPath . $destinationFileName);
    }

    /**
     * deleteFile contents from storage device
     */
    public function deleteFile($disk_name = null)
    {
        if (!$disk_name) {
            $disk_name = $this->disk_name;
        }

        $directory = $this->getStorageDirectory() . $this->getPartitionDirectory($disk_name);
        $filePath = $directory . $disk_name;

        if ($this->storageCmd('exists', $filePath)) {
            $this->storageCmd('delete', $filePath);
        }

        // Clear remote storage cache
        if (!$this->isLocalStorage()) {
            Cache::forget($this->getCacheKey($filePath));
        }

        $this->deleteThumbs();
        $this->deleteEmptyDirectory($directory);
    }

    /**
     * hasFile checks file exists on storage device
     */
    protected function hasFile($fileName = null)
    {
        $filePath = $this->getDiskPath($fileName);

        if ($this->isLocalStorage()) {
            return $this->storageCmd('exists', $filePath);
        }

        // Cache remote storage results for performance increase
        $result = Cache::rememberForever($this->getCacheKey($filePath), function () use ($filePath) {
            return $this->storageCmd('exists', $filePath);
        });

        // Forget negative results
        if (!$result) {
            Cache::forget($this->getCacheKey($filePath));
        }

        return $result;
    }

    /**
     * deleteEmptyDirectory checks if directory is empty then deletes it,
     * three levels up to match the partition directory.
     */
    protected function deleteEmptyDirectory($dir = null)
    {
        if (!$this->isDirectoryEmpty($dir)) {
            return;
        }

        $this->storageCmd('deleteDirectory', $dir);

        $dir = dirname($dir);
        if (!$this->isDirectoryEmpty($dir)) {
            return;
        }

        $this->storageCmd('deleteDirectory', $dir);

        $dir = dirname($dir);
        if (!$this->isDirectoryEmpty($dir)) {
            return;
        }

        $this->storageCmd('deleteDirectory', $dir);
    }

    /**
     * isDirectoryEmpty returns true if a directory contains no files
     */
    protected function isDirectoryEmpty($dir)
    {
        if (!$dir) {
            return null;
        }

        return count($this->storageCmd('allFiles', $dir)) === 0;
    }

    //
    // Storage interface
    //

    /**
     * storageCmd calls a method against File or Storage depending on local storage
     * This allows local storage outside the storage/app folder and is
     * also good for performance. For local storage, *every* argument
     * is prefixed with the local root path. Props to Laravel for
     * the unified interface.
     */
    protected function storageCmd()
    {
        $args = func_get_args();
        $command = array_shift($args);
        $result = null;

        if ($this->isLocalStorage()) {
            $interface = 'File';
            $path = $this->getLocalRootPath();
            $args = array_map(function ($value) use ($path) {
                return $path . '/' . $value;
            }, $args);

            $result = forward_static_call_array([$interface, $command], $args);
        }
        else {
            $result = call_user_func_array([$this->getDisk(), $command], $args);
        }

        return $result;
    }

    /**
     * copyStorageToLocal file
     */
    protected function copyStorageToLocal($storagePath, $localPath)
    {
        return FileHelper::put($localPath, $this->getDisk()->get($storagePath));
    }

    /**
     * copyLocalToStorage file
     */
    protected function copyLocalToStorage($localPath, $storagePath)
    {
        return $this->getDisk()->put($storagePath, FileHelper::get($localPath), $this->isPublic() ? 'public' : null);
    }

    //
    // Configuration
    //

    /**
     * getMaxFilesize returns the maximum size of an uploaded file as configured in php.ini
     * @return int The maximum size of an uploaded file in kilobytes
     */
    public static function getMaxFilesize()
    {
        return round(UploadedFile::getMaxFilesize() / 1024);
    }

    /**
     * getStorageDirectory defines the internal storage path, override this method
     */
    public function getStorageDirectory()
    {
        if ($this->isPublic()) {
            return 'uploads/public/';
        }

        return 'uploads/protected/';
    }

    /**
     * getPublicPath defines the public address for the storage path
     */
    public function getPublicPath()
    {
        if ($this->isPublic()) {
            return $this->urlPath().'/uploads/public/';
        }

        return $this->urlPath().'/uploads/protected/';
    }

    /**
     * getTempPath defines the internal working path, override this method
     */
    public function getTempPath()
    {
        $path = temp_path() . '/uploads';

        if (!FileHelper::isDirectory($path)) {
            FileHelper::makeDirectory($path, 0755, true, true);
        }

        return $path;
    }

    /**
     * getDisk returns the storage disk the file is stored on
     * @return FilesystemAdapter
     */
    public function getDisk()
    {
        return Storage::disk($this->disk());
    }

    /**
     * isLocalStorage returns true if the storage engine is local
     */
    protected function isLocalStorage()
    {
        return Storage::getDefaultDriver() === 'local' or Storage::getDefaultDriver() === 'public';
    }

    /**
    * getPartitionDirectory generates a partition for the file
    * return /ABC/DE1/234 for an name of ABCDE1234.
    * @param Attachment $attachment
    * @param string $styleName
    * @return mixed
    */
    protected function getPartitionDirectory($disk_name = null)
    {
        if(is_null($disk_name)) $disk_name = $this->disk_name;
        return implode('/', array_slice(str_split($disk_name, 3), 0, 3)) . '/';
    }

    /**
     * getLocalRootPath if working with local storage, determine the absolute local path
     */
    protected function getLocalRootPath()
    {
        return storage_path() . '/app/public';
    }

    protected function disk()
    {
        return config('filesystems.default');
    }

    protected function urlPath()
    {
        return config('filesystems.disks.'.$this->disk().'.url');
    }

    /**
     * Output file, or fall back on the 404 page
     */
    public function get($code = null)
    {
        try {
            return $this->findFileObject($code)->output('inline', true);
        }
        catch (Exception $ex) {
            throw new Exception("Not Found");
        }
    }

    /**
     * Returns a unique code used for masking the file identifier.
     * @param $file App\Models\SystemFile
     * @return string
     */
    public function getUniqueCode($file)
    {
        if (!$file) {
            return null;
        }

        $hash = md5($file->file_name . '!' . $file->disk_name);
        return base64_encode($file->id . '!' . $hash);
    }

    /**
     * Locates a file model based on the unique code.
     * @param $code string
     * @return App\Models\SystemFile
     */
    protected function findFileObject($code)
    {
        if (!$code) {
            throw new Exception('Missing code');
        }

        $parts = explode('!', base64_decode($code));
        if (count($parts) < 2) {
            throw new Exception('Invalid code');
        }

        [$id, $hash] = $parts;

        if (!$file = static::find((int) $id)) {
            throw new Exception('Unable to find file');
        }

        /**
         * Ensure that the file model utilized for this request is
         * the one specified in the relationship configuration
         */
        if ($file->attachment) {
            $fileModel = $file->attachment->{$file->field}()->getRelated();

            /**
             * Only attempt to get file model through its assigned class
             * when the assigned class differs from the default one that
             * the file has already been loaded from
             */
            if (get_class($file) !== get_class($fileModel)) {
                $file = $fileModel->find($file->id);
            }
        }

        $verifyCode = $this->getUniqueCode($file);
        if ($code != $verifyCode) {
            throw new Exception('Invalid hash');
        }

        return $file;
    }
}


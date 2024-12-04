<?php
//Your Settings can be read here: settings::read('myArray/settingName') = $settingValue;
//Your Settings can be saved here: settings::set('myArray/settingName',$settingValue,$overwrite = true/false);
class modrinth_api{
    //public static function command($line):void{}//Run when base command is class name, $line is anything after base command (string). e.g. > [base command] [$line]
    public static function init():void{
        $defaultSettings = array(
            "apiUrl" => "https://api.modrinth.com/v2",
            "libraryDir" => "mcservers\\library\\modrinth"
        );
        foreach($defaultSettings as $settingName => $settingValue){
            settings::set($settingName,$settingValue,false);
        }
    }//Run at startup
    public static function apiLoad(string $uri):mixed{
        $url = settings::read('apiUrl') . '/' . $uri;

        $dir = substr($uri,0,strpos($uri,"?"));

        $resultJson = json::readFile($url);

        if(empty($resultJson)){
            return false;
        }

        return $resultJson;
    }
    public static function search(string $query, string|bool $catagory, string $mcVersion, string $type, bool $serverSide):array|false{
        $return = array();

        $string = "?query=" . $query . '&facets=[';
        
        if(is_string($catagory)){
            $string .= '[%22categories:' . $catagory . '%22],';
        }

        $string .= '[%22versions:' . $mcVersion . '%22],[%22project_type:' . $type . '%22]';

        if($serverSide){
            $string .= ',[%22server_side!=unsupported%22]';
        }

        $string .= ']&limit=20';

        $resultJson = self::apiLoad('search' . $string);

        if(!is_array($resultJson)){
            return false;
        }

        $return['results'] = array();
        foreach($resultJson['hits'] as $hit){
            $varnames = array('project_id','title','icon_url','slug','author','downloads','description','date_modified');
            $hitinfo = array();
            foreach($varnames as $varname){
                $hitinfo[$varname] = $hit[$varname];
            }
            $return['results'][] = $hitinfo;
        }

        $return['total'] = $resultJson['total_hits'];

        return $return;
    }
    public static function setSetting(string $settingName, mixed $settingValue, bool $overwrite):bool{
        return settings::set($settingName,$settingValue,$overwrite);
    }
    public static function downloadFile(string $projectId, string $versionId, string|bool $mcVersion, string|bool $loader):array|false{
        $path = "project/" . $projectId . "/version/" . $versionId;
        $json = self::apiLoad($path);
        $localPath = settings::read('libraryDir') . "/" . $path;
        if($json === false){
            return false;
        }
        json::writeFile($localPath . ".json",$json,true);

        $return = array();

        foreach($json['files'] as $file){
            if($file['primary']){
                $primaryFile = $localPath . '/files/' . $file['filename'];
                if(!is_file($primaryFile)){
                    if(!downloader::downloadFile($file['url'],$primaryFile)){
                        return false;
                    }
                }
                $types = json::readFile('https://api.modrinth.com/v3/project/' . $projectId . '/version/' . $versionId,false)['project_types'];
                $return[] = array(
                    'file' => $primaryFile,
                    'type' => $types[0],
                    'url' => $file['url'],
                    'projectId' => $projectId,
                    'versionId' => $versionId
                );
                break;
            }
        }
        
        foreach($json['dependencies'] as $dependency){
            if(empty($dependency['project_id'])){
                return false;
            }
            $dependencyFiles = self::downloadFile($dependency['project_id'],self::getProjectLatestVersion($dependency['project_id'],$mcVersion,$loader),$mcVersion,$loader);
            if(is_array($dependencyFiles)){
                $return = array_merge($return,$dependencyFiles);
            }
            else{
                return false;
            }
        }

        return $return;
    }
    public static function getProjectLatestVersion(string $projectId, string|bool $mcVersion = false, string|bool $loader = false):string|false{
        $json = self::listProjectVersions($projectId,$mcVersion,$loader);
        if(isset($json[0]['id'])){
            return $json[0]['id'];
        }
        return false;
    }
    public static function listProjectVersions(string $projectId, string|bool $mcVersion = false, string|bool $loader = false):array|false{
        $path = 'project/' . $projectId . '/version';
        $filter = "";
        if(is_string($mcVersion)){
            $filter = "?game_versions=[%22" . $mcVersion . "%22]";
        }
        if(is_string($loader)){
            $filter = "?loaders=[%22" . $loader . "%22]";
        }
        if(is_string($mcVersion) && is_string($loader)){
            $filter = "?game_versions=[%22" . $mcVersion . "%22]&loaders=[%22" . $loader . "%22]";
        }
        $json = self::apiLoad($path . $filter);
        if($json === false){
            return false;
        }

        $return = array();

        foreach($json as $result){
            $varnames = array('id','name','version_number','dependencies','date_published','version_type','downloads');
            foreach($varnames as $varname){
                $resultInfo[$varname] = $result[$varname];
            }
            $return[] = $resultInfo;
        }

        return $return;
    }
}
<?php
    
    if (($handle = fopen(MODX_BASE_PATH."import/inns.csv", "r")) !== FALSE) {
        
        // Разобрать файл, поулчить массив вида инн - группа
        //$inn_list = array('622000492524'=>'3price', '6311190845'=>'5price');
        
        $row = 1;
        $inn_list = array();
        
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            if($data[0] != 'inn')
                $inn_list[$data[0]] = $data[1];
            $row++;
        }
        fclose($handle);
    
        // Включаем обработку ошибок
        $modx->getService('error','error.modError');
        $modx->setLogLevel(modX::LOG_LEVEL_INFO);
    
        
        // получаем объект класса modUserProfile
        $users_profiles = $modx->getCollection('modUserProfile', array('mobilephone:IN' => array_keys($inn_list)));
        
        if(!empty($users_profiles))
        {
            $user_ids_infos = array();
            foreach($users_profiles as &$user_p)
            {
                $user_ids_infos[$user_p->internalKey] = $user_p->mobilephone;
            }
        }
        else
        {
            $log_level = modX::LOG_LEVEL_ERROR;
            $result = 'Не удалось получить информацию пользователей по инн(возможно не найдены просто)';
        }
    
        // Получим все существующие группы
        $groups = $modx->getCollection('modUserGroup');
        $g_groups = array();
        if(!empty($groups))
        {
            foreach($groups as $g)
            {
                $g_groups[$g->get('name')] = $g->id;
            }
        }
        else
        {
            $log_level = modX::LOG_LEVEL_ERROR;
            $result = 'Не удалось получить группы пользователей';
        }
        
        // Получим уже самих пользователей
        if(!empty($user_ids_infos))
        {
            $users = $modx->getCollection('modUser', array('id:IN' =>array_keys($user_ids_infos)));
            if(!empty($users))
            {
                $i = 0;
                $new_i = 0;
                $removed_i = 0;
                $error_i = 0;
                foreach($users as $user)
                {
                    $i++;
                    // Если пользователь еще не член новой группы, то нужно найти все лишние
                    if(!$user->isMember($inn_list[$user_ids_infos[$user->id]]))
                    {
                        $user_groups = $user->getUserGroupNames();
                        if(!empty($user_groups))
                        {
                            // Выходим из лишних групп, если это не группа отличающая юр лицо
                            foreach($user_groups as $ug)
                            {
                                if($ug != 'entity')
                                {
                                    $user->leaveGroup($g_groups[$ug]);
                                    $removed_i++;
                                }
                            }
                        }
                        if(!empty($g_groups[$inn_list[$user_ids_infos[$user->id]]]))
                        {
                            $user->joinGroup($g_groups[$inn_list[$user_ids_infos[$user->id]]]);
                            $new_i++;
                        }
                        elseif(!empty($inn_list[$user_ids_infos[$user->id]]))
                        {
                            $modx->log(modX::LOG_LEVEL_ERROR, 'Нет группы '.$inn_list[$user_ids_infos[$user->id]].' в сайте', '', 'bash_update_inns');
                            $error_i++;
                        }
                    }
                }
                $log_level = modX::LOG_LEVEL_INFO;
                $result = 'Обработано '.$i.' пользователей. Вышли из '. $removed_i . ' групп. Присвоено ' . $new_i . ' групп. ' . $error_i . ' ошибок.';
            }
            else
            {
                $log_level = modX::LOG_LEVEL_ERROR;
                $result = 'Не удалось получить пользователей, хотя информация по ним есть';
            }
        }
        
        $modx->log($log_level, $result, '', 'bash_update_inns');
        
        // ошибок не возникло, удалим файл
        if($log_level == modX::LOG_LEVEL_INFO)
            unlink(MODX_BASE_PATH."import/inns.csv");
            
    }
   

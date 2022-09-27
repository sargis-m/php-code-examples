<?php

$id_course = '';
$id_user = '';

$query = db()->newQuery();
$query->from('career_paths-users');
$query->setCols('`career_paths-users`.id_user, `career_paths-courses`.*, `career_paths`.type');
$query->join('career_paths-courses', '`career_paths-users`.id_career_path=`career_paths-courses`.id_career_path', 'inner');
$query->join('courses-users', '`courses-users`.id_course=' . $id_course . ' AND `courses-users`.id_user=' . $id_user, 'left');
$query->join('career_paths', '`career_paths`.id=`career_paths-courses`.id_career_path');
$query->where('`career_paths-users`.id_user = ?', $id_user);
$query->where('`career_paths-courses`.id_course = ?', $id_course);
$query->where('`courses-users`.id IS NULL');

$union = db()->newQuery();
$union->from('career_paths-user_groups');
$union->setCols('`user_groups-users`.id_user, `career_paths-courses`.*, `career_paths`.type');
$union->join('user_groups-users', '`user_groups-users`.`id_user_group` = `career_paths-user_groups`.`id_user_group`', 'inner');
$union->join('career_paths-courses', '`career_paths-user_groups`.id_career_path=`career_paths-courses`.id_career_path', 'inner');
$union->join('courses-users', '`courses-users`.id_course=' . $id_course . ' AND `courses-users`.id_user=' . $id_user, 'left');
$union->join('career_paths', '`career_paths`.id=`career_paths-courses`.id_career_path');
$union->where('`user_groups-users`.id_user = ?', $id_user);
$union->where('`career_paths-courses`.id_course = ?', $id_course);
$union->where('`courses-users`.id IS NULL');

$result = db()->execute($query->union($union))->fetchResult();

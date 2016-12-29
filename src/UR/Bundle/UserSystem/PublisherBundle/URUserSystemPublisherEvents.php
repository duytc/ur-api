<?php

namespace UR\Bundle\UserSystem\PublisherBundle;

final class URUserSystemPublisherEvents
{
    const CHANGE_PASSWORD_INITIALIZE = 'ur_user_system_publisher.change_password.edit.initialize';
    const CHANGE_PASSWORD_SUCCESS = 'ur_user_system_publisher.change_password.edit.success';
    const CHANGE_PASSWORD_COMPLETED = 'ur_user_system_publisher.change_password.edit.completed';
    const GROUP_CREATE_INITIALIZE = 'ur_user_system_publisher.group.create.initialize';
    const GROUP_CREATE_SUCCESS = 'ur_user_system_publisher.group.create.success';
    const GROUP_CREATE_COMPLETED = 'ur_user_system_publisher.group.create.completed';
    const GROUP_DELETE_COMPLETED = 'ur_user_system_publisher.group.delete.completed';
    const GROUP_EDIT_INITIALIZE = 'ur_user_system_publisher.group.edit.initialize';
    const GROUP_EDIT_SUCCESS = 'ur_user_system_publisher.group.edit.success';
    const GROUP_EDIT_COMPLETED = 'ur_user_system_publisher.group.edit.completed';
    const PROFILE_EDIT_INITIALIZE = 'ur_user_system_publisher.profile.edit.initialize';
    const PROFILE_EDIT_SUCCESS = 'ur_user_system_publisher.profile.edit.success';
    const PROFILE_EDIT_COMPLETED = 'ur_user_system_publisher.profile.edit.completed';
    const REGISTRATION_INITIALIZE = 'ur_user_system_publisher.registration.initialize';
    const REGISTRATION_SUCCESS = 'ur_user_system_publisher.registration.success';
    const REGISTRATION_COMPLETED = 'ur_user_system_publisher.registration.completed';
    const REGISTRATION_CONFIRM = 'ur_user_system_publisher.registration.confirm';
    const REGISTRATION_CONFIRMED = 'ur_user_system_publisher.registration.confirmed';
    const RESETTING_RESET_INITIALIZE = 'ur_user_system_publisher.resetting.reset.initialize';
    const RESETTING_RESET_SUCCESS = 'ur_user_system_publisher.resetting.reset.success';
    const RESETTING_RESET_COMPLETED = 'ur_user_system_publisher.resetting.reset.completed';
    const SECURITY_IMPLICIT_LOGIN = 'ur_user_system_publisher.security.implicit_login';
}

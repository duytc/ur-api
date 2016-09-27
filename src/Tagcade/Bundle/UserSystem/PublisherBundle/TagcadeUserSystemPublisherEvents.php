<?php

namespace Tagcade\Bundle\UserSystem\PublisherBundle;

final class TagcadeUserSystemPublisherEvents
{
    const CHANGE_PASSWORD_INITIALIZE = 'tagcade_user_system_publisher.change_password.edit.initialize';
    const CHANGE_PASSWORD_SUCCESS = 'tagcade_user_system_publisher.change_password.edit.success';
    const CHANGE_PASSWORD_COMPLETED = 'tagcade_user_system_publisher.change_password.edit.completed';
    const GROUP_CREATE_INITIALIZE = 'tagcade_user_system_publisher.group.create.initialize';
    const GROUP_CREATE_SUCCESS = 'tagcade_user_system_publisher.group.create.success';
    const GROUP_CREATE_COMPLETED = 'tagcade_user_system_publisher.group.create.completed';
    const GROUP_DELETE_COMPLETED = 'tagcade_user_system_publisher.group.delete.completed';
    const GROUP_EDIT_INITIALIZE = 'tagcade_user_system_publisher.group.edit.initialize';
    const GROUP_EDIT_SUCCESS = 'tagcade_user_system_publisher.group.edit.success';
    const GROUP_EDIT_COMPLETED = 'tagcade_user_system_publisher.group.edit.completed';
    const PROFILE_EDIT_INITIALIZE = 'tagcade_user_system_publisher.profile.edit.initialize';
    const PROFILE_EDIT_SUCCESS = 'tagcade_user_system_publisher.profile.edit.success';
    const PROFILE_EDIT_COMPLETED = 'tagcade_user_system_publisher.profile.edit.completed';
    const REGISTRATION_INITIALIZE = 'tagcade_user_system_publisher.registration.initialize';
    const REGISTRATION_SUCCESS = 'tagcade_user_system_publisher.registration.success';
    const REGISTRATION_COMPLETED = 'tagcade_user_system_publisher.registration.completed';
    const REGISTRATION_CONFIRM = 'tagcade_user_system_publisher.registration.confirm';
    const REGISTRATION_CONFIRMED = 'tagcade_user_system_publisher.registration.confirmed';
    const RESETTING_RESET_INITIALIZE = 'tagcade_user_system_publisher.resetting.reset.initialize';
    const RESETTING_RESET_SUCCESS = 'tagcade_user_system_publisher.resetting.reset.success';
    const RESETTING_RESET_COMPLETED = 'tagcade_user_system_publisher.resetting.reset.completed';
    const SECURITY_IMPLICIT_LOGIN = 'tagcade_user_system_publisher.security.implicit_login';
}

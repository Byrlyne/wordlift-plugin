<?php

interface WordLift_EntityService {

    public function getByPostID( $postID );

    public function getBySubject( $subject );

    public function create( $subject );

    public function bindPostToSubjects( $postID, $subject );

}

?>
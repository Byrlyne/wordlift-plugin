angular.module('wordlift.editpost.widget.directives.wlEntityForm', [])
.directive('wlEntityForm', ['configuration', '$window', '$log', (configuration, $window, $log)->
    restrict: 'E'
    scope:
      entity: '='
      onSubmit: '&'
      box: '='

    template: """
      <div name="wordlift" class="wl-entity-form">
      <div ng-show="entity.images.length > 0">
          <img ng-src="{{entity.images[0]}}" wl-on-error="removeCurrentImage()" />
      </div>
      <div>
          <label class="wl-required">Entity label</label>
          <input type="text" ng-model="entity.label" ng-disabled="checkEntityId(entity.id)" />
      </div>
      <div ng-hide="isInternal()">
          <label class="wl-required">Entity type</label>
          <select ng-hide="hasOccurences()" ng-model="entity.mainType" ng-options="type.id as type.name for type in supportedTypes" ></select>
          <input ng-show="hasOccurences()" type="text" ng-value="getCurrentTypeUri()" disabled="true" />
      </div>
      <div>
          <label class="wl-required">Entity Description</label>
          <textarea ng-model="entity.description" rows="6" ng-disabled="isInternal()"></textarea>
      </div>
      <div ng-hide="isInternal()">
          <label ng-show="checkEntityId(entity.id)" class="wl-required">Entity Id</label>
          <input ng-show="checkEntityId(entity.id)" type="text" ng-model="entity.id" disabled="true" />
      </div>
      <div ng-hide="isInternal()">
          <label>Entity Same as</label>
          <input type="text" ng-model="entity.sameAs" />
          <div ng-show="entity.suggestedSameAs.length > 0" class="wl-suggested-sameas-wrapper">
            <h5>same as suggestions</h5>
            <div ng-click="setSameAs(sameAs)" ng-class="{ 'active': entity.sameAs == sameAs }" class="wl-sameas" ng-repeat="sameAs in entity.suggestedSameAs">{{sameAs}}</div>
          </div>
      </div>
      <div ng-hide="isInternal()" class="wl-buttons-wrapper">
        <span class="button button-primary wl-button" ng-click="onSubmit()">Add</span>
      </div>
      <div ng-show="isInternal()" class="wl-buttons-wrapper">
        <span class="button button-primary wl-button" ng-click="linkTo('lod')">View Linked Data<i class="wl-link"></i></span>
        <span class="button button-primary wl-button" ng-click="linkTo('edit')">Edit<i class="wl-link"></i></span>
      </div>
      </div>
    """
    link: ($scope, $element, $attrs, $ctrl) ->  

      $scope.configuration = configuration

      $scope.removeCurrentImage = ()->
        removed = $scope.entity.images.shift()
        $log.warn "Removed #{removed} from entity #{$scope.entity.id} images collection"
        
      $scope.getCurrentTypeUri = ()->
        for type in configuration.types
          if type.css is "wl-#{$scope.entity.mainType}"
            return type.uri

      $scope.isInternal = ()->
        if $scope.entity.id.startsWith configuration.datasetUri
          return true
        return false 
      
      $scope.linkTo = (linkType)->
        $window.location.href = ajaxurl + '?action=wordlift_redirect&uri=' + $window.encodeURIComponent($scope.entity.id) + "&to=" + linkType
      
      $scope.hasOccurences = ()->
        $scope.entity.occurrences.length > 0
      $scope.setSameAs = (uri)->
        $scope.entity.sameAs = uri
      
      $scope.checkEntityId = (uri)->
        /^(f|ht)tps?:\/\//i.test(uri)

      availableTypes = [] 
      for type in configuration.types
        availableTypes[ type.css.replace('wl-','') ] = type.uri

      $scope.supportedTypes = ({ id: type.css.replace('wl-',''), name: type.uri } for type in configuration.types)
      if $scope.box
        $scope.supportedTypes = ({ id: type, name: availableTypes[ type ] } for type in $scope.box.registeredTypes)
        

])

(function() {
  angular.module('testMod', []).controller('testCtrl', function($scope) {
    return $scope.date = new Date();
  }).directive('timeDatePicker', [
    '$filter',
    '$sce',
    function($filter,
    $sce) {
      var _dateFilter;
      _dateFilter = $filter('date');
      return {
        restrict: 'AE',
        replace: true,
        scope: {
          _modelValue: '=ngModel'
        },
        require: 'ngModel',
        templateUrl: 'time-date.tpl',
        link: function(scope,
    element,
    attrs,
    ngModel) {
          var ref;
          scope._mode = (ref = attrs.defaultMode) != null ? ref : 'date';
          scope._displayMode = attrs.displayMode;
          scope._hours24 = (attrs.displayTwentyfour != null) && attrs.displayTwentyfour;
          ngModel.$render = function() {
            scope.date = ngModel.$modelValue != null ? new Date(ngModel.$modelValue) : new Date();
            scope.calendar._year = scope.date.getFullYear();
            scope.calendar._month = scope.date.getMonth();
            scope.clock._minutes = scope.date.getMinutes();
            return scope.clock._hours = scope.date.getHours();
          };
          scope.save = function() {
            return scope._modelValue = scope.date;
          };
          return scope.cancel = function() {
            return ngModel.$render();
          };
        },
        controller: [
          '$scope',
          function(scope) {
            var i;
            scope.date = new Date();
            scope.display = {
              fullTitle: function() {
                return _dateFilter(scope.date,
          'EEEE d MMMM yyyy, h:mm a');
              },
              title: function() {
                if (scope._mode === 'date') {
                  return _dateFilter(scope.date,
          'EEEE h:mm a');
                } else {
                  return _dateFilter(scope.date,
          'MMMM d yyyy');
                }
              },
              super: function() {
                if (scope._mode === 'date') {
                  return _dateFilter(scope.date,
          'MMM');
                } else {
                  return '';
                }
              },
              main: function() {
                return $sce.trustAsHtml(scope._mode === 'date' ? _dateFilter(scope.date,
          'd') : `${_dateFilter(scope.date,
          'h:mm')}<small>${_dateFilter(scope.date,
          'a')}</small>`);
              },
              sub: function() {
                if (scope._mode === 'date') {
                  return _dateFilter(scope.date,
          'yyyy');
                } else {
                  return _dateFilter(scope.date,
          'HH:mm');
                }
              }
            };
            scope.calendar = {
              _month: 0,
              _year: 0,
              _months: (function() {
                var j,
          results;
                results = [];
                for (i = j = 0; j <= 11; i = ++j) {
                  results.push(_dateFilter(new Date(0,
          i),
          'MMMM'));
                }
                return results;
              })(),
              offsetMargin: function() {
                return `${new Date(this._year,
          this._month).getDay() * 3.6}rem`;
              },
              isVisible: function(d) {
                return new Date(this._year,
          this._month,
          d).getMonth() === this._month;
              },
              class: function(d) {
                if (new Date(this._year,
          this._month,
          d).getTime() === new Date(scope.date.getTime()).setHours(0,
          0,
          0,
          0)) {
                  return "selected";
                } else if (new Date(this._year,
          this._month,
          d).getTime() === new Date().setHours(0,
          0,
          0,
          0)) {
                  return "today";
                } else {
                  return "";
                }
              },
              select: function(d) {
                return scope.date.setFullYear(this._year,
          this._month,
          d);
              },
              monthChange: function() {
                if ((this._year == null) || isNaN(this._year)) {
                  this._year = new Date().getFullYear();
                }
                scope.date.setFullYear(this._year,
          this._month);
                if (scope.date.getMonth() !== this._month) {
                  return scope.date.setDate(0);
                }
              }
            };
            scope.clock = {
              _minutes: 0,
              _hours: 0,
              _incHours: function(inc) {
                return this._hours = Math.max(0,
          Math.min(23,
          this._hours + inc));
              },
              _incMinutes: function(inc) {
                return this._minutes = Math.max(0,
          Math.min(59,
          this._minutes + inc));
              },
              _hour: function() {
                var _h;
                _h = scope.date.getHours();
                _h = _h % 12;
                if (_h === 0) {
                  return 12;
                } else {
                  return _h;
                }
              },
              setHour: function(h) {
                if (h === 12 && this.isAM()) {
                  h = 0;
                }
                h += !this.isAM() ? 12 : 0;
                if (h === 24) {
                  h = 12;
                }
                return scope.date.setHours(h);
              },
              setAM: function(b) {
                if (b && !this.isAM()) {
                  return scope.date.setHours(scope.date.getHours() - 12);
                } else if (!b && this.isAM()) {
                  return scope.date.setHours(scope.date.getHours() + 12);
                }
              },
              isAM: function() {
                return scope.date.getHours() < 12;
              }
            };
            scope.$watch('clock._minutes',
          function(val) {
              if ((val != null) && val !== scope.date.getMinutes()) {
                return scope.date.setMinutes(val);
              }
            });
            scope.$watch('clock._hours',
          function(val) {
              if ((val != null) && val !== scope.date.getHours()) {
                return scope.date.setHours(val);
              }
            });
            scope.setNow = function() {
              return scope.date = new Date();
            };
            scope._mode = 'date';
            scope.modeClass = function() {
              if (scope._displayMode != null) {
                scope._mode = scope._displayMode;
              }
              if (scope._displayMode === 'full') {
                return 'full-mode';
              } else if (scope._displayMode === 'time') {
                return 'time-only';
              } else if (scope._displayMode === 'date') {
                return 'date-only';
              } else if (scope._mode === 'date') {
                return 'date-mode';
              } else {
                return 'time-mode';
              }
            };
            scope.modeSwitch = function() {
              var ref;
              return scope._mode = (ref = scope._displayMode) != null ? ref : scope._mode === 'date' ? 'time' : 'date';
            };
            return scope.modeSwitchText = function() {
              if (scope._mode === 'date') {
                return 'Clock';
              } else {
                return 'Calendar';
              }
            };
          }
        ]
      };
    }
  ]);

}).call(this);

//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXMiOlsiPGFub255bW91cz4iXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7RUFBQSxPQUFPLENBQUMsTUFBUixDQUFlLFNBQWYsRUFBMEIsRUFBMUIsQ0FDQSxDQUFDLFVBREQsQ0FDWSxVQURaLEVBQ3dCLFFBQUEsQ0FBQyxNQUFELENBQUE7V0FDdEIsTUFBTSxDQUFDLElBQVAsR0FBYyxJQUFJLElBQUosQ0FBQTtFQURRLENBRHhCLENBR0EsQ0FBQyxTQUhELENBR1csZ0JBSFgsRUFHNkI7SUFBQyxTQUFEO0lBQVksTUFBWjtJQUFvQixRQUFBLENBQUMsT0FBRDtJQUFVLElBQVYsQ0FBQTtBQUMvQyxVQUFBO01BQUEsV0FBQSxHQUFjLE9BQUEsQ0FBUSxNQUFSO2FBQ2Q7UUFBQSxRQUFBLEVBQVUsSUFBVjtRQUNBLE9BQUEsRUFBUyxJQURUO1FBRUEsS0FBQSxFQUNFO1VBQUEsV0FBQSxFQUFhO1FBQWIsQ0FIRjtRQUlBLE9BQUEsRUFBUyxTQUpUO1FBS0EsV0FBQSxFQUFhLGVBTGI7UUFNQSxJQUFBLEVBQU0sUUFBQSxDQUFDLEtBQUQ7SUFBUSxPQUFSO0lBQWlCLEtBQWpCO0lBQXdCLE9BQXhCLENBQUE7QUFDSixjQUFBO1VBQUEsS0FBSyxDQUFDLEtBQU4sNkNBQWtDO1VBQ2xDLEtBQUssQ0FBQyxZQUFOLEdBQXFCLEtBQUssQ0FBQztVQUMzQixLQUFLLENBQUMsUUFBTixHQUFpQixpQ0FBQSxJQUE2QixLQUFLLENBQUM7VUFDcEQsT0FBTyxDQUFDLE9BQVIsR0FBa0IsUUFBQSxDQUFBLENBQUE7WUFDaEIsS0FBSyxDQUFDLElBQU4sR0FBZ0IsMkJBQUgsR0FBNkIsSUFBSSxJQUFKLENBQVMsT0FBTyxDQUFDLFdBQWpCLENBQTdCLEdBQStELElBQUksSUFBSixDQUFBO1lBQzVFLEtBQUssQ0FBQyxRQUFRLENBQUMsS0FBZixHQUF1QixLQUFLLENBQUMsSUFBSSxDQUFDLFdBQVgsQ0FBQTtZQUN2QixLQUFL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QUFyQjt1QkFBZ0QsS0FBSyxDQUFDLElBQUksQ0FBQyxRQUFYLENBQW9CLEdBQXBCLEVBQWhEOztZQUQyQixDQUE3QjtZQUVBLEtBQUssQ0FBQyxNQUFOLEdBQWUsUUFBQSxDQUFBLENBQUE7cUJBQUcsS0FBSyxDQUFDLElBQU4sR0FBYSxJQUFJLElBQUosQ0FBQTtZQUFoQjtZQUNmLEtBQUssQ0FBQyxLQUFOLEdBQWM7WUFDZCxLQUFLLENBQUMsU0FBTixHQUFrQixRQUFBLENBQUEsQ0FBQTtjQUNoQixJQUFHLDBCQUFIO2dCQUE0QixLQUFLLENBQUMsS0FBTixHQUFjLEtBQUssQ0FBQyxhQUFoRDs7Y0FDQSxJQUFHLEtBQUssQ0FBQyxZQUFOLEtBQXNCLE1BQXpCO3VCQUFxQyxZQUFyQztlQUFBLE1BQ0ssSUFBRyxLQUFLLENBQUMsWUFBTixLQUFzQixNQUF6Qjt1QkFBcUMsWUFBckM7ZUFBQSxNQUNBLElBQUcsS0FBSyxDQUFDLFlBQU4sS0FBc0IsTUFBekI7dUJBQXFDLFlBQXJDO2VBQUEsTUFDQSxJQUFHLEtBQUssQ0FBQyxLQUFOLEtBQWUsTUFBbEI7dUJBQThCLFlBQTlCO2VBQUEsTUFBQTt1QkFDQSxZQURBOztZQUxXO1lBT2xCLEtBQUssQ0FBQyxVQUFOLEdBQW1CLFFBQUEsQ0FBQSxDQUFBO0FBQUcsa0JBQUE7cUJBQUEsS0FBSyxDQUFDLEtBQU4sOENBQXNDLEtBQUssQ0FBQyxLQUFOLEtBQWUsTUFBbEIsR0FBOEIsTUFBOUIsR0FBMEM7WUFBaEY7bUJBQ25CLEtBQUssQ0FBQyxjQUFOLEdBQXVCLFFBQUEsQ0FBQSxDQUFBO2NBQUcsSUFBRyxLQUFLLENBQUMsS0FBTixLQUFlLE1BQWxCO3VCQUE4QixRQUE5QjtlQUFBLE1BQUE7dUJBQTJDLFdBQTNDOztZQUFIO1VBOURGLENBQVg7O01BbEJaO0lBRitDLENBQXBCO0dBSDdCO0FBQUEiLCJzb3VyY2VzQ29udGVudCI6WyJhbmd1bGFyLm1vZHVsZSgndGVzdE1vZCcsIFtdKVxuLmNvbnRyb2xsZXIgJ3Rlc3RDdHJsJywgKCRzY29wZSkgLT5cbiAgJHNjb3BlLmRhdGUgPSBuZXcgRGF0ZSgpXG4uZGlyZWN0aXZlICd0aW1lRGF0ZVBpY2tlcicsIFsnJGZpbHRlcicsICckc2NlJywgKCRmaWx0ZXIsICRzY2UpIC0+XG4gIF9kYXRlRmlsdGVyID0gJGZpbHRlciAnZGF0ZSdcbiAgcmVzdHJpY3Q6ICdBRSdcbiAgcmVwbGFjZTogdHJ1ZVxuICBzY29wZTpcbiAgICBfbW9kZWxWYWx1ZTogJz1uZ01vZGVsJ1xuICByZXF1aXJlOiAnbmdNb2RlbCdcbiAgdGVtcGxhdGVVcmw6ICd0aW1lLWRhdGUudHBsJ1xuICBsaW5rOiAoc2NvcGUsIGVsZW1lbnQsIGF0dHJzLCBuZ01vZGVsKSAtPlxuICAgIHNjb3BlLl9tb2RlID0gYXR0cnMuZGVmYXVsdE1vZGUgPyAnZGF0ZSdcbiAgICBzY29wZS5fZGlzcGxheU1vZGUgPSBhdHRycy5kaXNwbGF5TW9kZVxuICAgIHNjb3BlLl9ob3VyczI0ID0gYXR0cnMuZGlzcGxheVR3ZW50eWZvdXI/IGFuZCBhdHRycy5kaXNwbGF5VHdlbnR5Zm91clxuICAgIG5nTW9kZWwuJHJlbmRlciA9IC0+XG4gICAgICBzY29wZS5kYXRlID0gaWYgbmdNb2RlbC4kbW9kZWxWYWx1ZT8gdGhlbiBuZXcgRGF0ZSBuZ01vZGVsLiRtb2RlbFZhbHVlIGVsc2UgbmV3IERhdGUoKVxuICAgICAgc2NvcGUuY2FsZW5kYXIuX3llYXIgPSBzY29wZS5kYXRlLmdldEZ1bGxZZWFyKClcbiAgICAgIHNjb3BlLmNhbGVuZGFyLl9tb250aCA9IHNjb3BlLmRhdGUuZ2V0TW9udGgoKVxuICAgICAgc2NvcGUuY2xvY2suX21pbnV0ZXMgPSBzY29wZS5kYXRlLmdldE1pbnV0ZXMoKVxuICAgICAgc2NvcGUuY2xvY2suX2hvdXJzID0gc2NvcGUuZGF0ZS5nZXRIb3VycygpXG4gICAgc2NvcGUuc2F2ZSA9IC0+IHNjb3BlLl9tb2RlbFZhbHVlID0gc2NvcGUuZGF0ZVxuICAgIHNjb3BlLmNhbmNlbCA9IC0+IG5nTW9kZWwuJHJlbmRlcigpXG4gIGNvbnRyb2xsZXI6IFsnJHNjb3BlJywgKHNjb3BlKSAtPlxuICAgIHNjb3BlLmRhdGUgPSBuZXcgRGF0ZSgpXG4gICAgc2NvcGUuZGlzcGxheSA9XG4gICAgICBmdWxsVGl0bGU6IC0+IF9kYXRlRmlsdGVyIHNjb3BlLmRhdGUsICdFRUVFIGQgTU1NTSB5eXl5LCBoOm1tIGEnXG4gICAgICB0aXRsZTogLT5cbiAgICAgICAgaWYgc2NvcGUuX21vZGUgaXMgJ2RhdGUnIHRoZW4gX2RhdGVGaWx0ZXIgc2NvcGUuZGF0ZSwgJ0VFRUUgaDptbSBhJ1xuICAgICAgICBlbHNlIF9kYXRlRmlsdGVyIHNjb3BlLmRhdGUsICdNTU1NIGQgeXl5eSdcbiAgICAgIHN1cGVyOiAtPlxuICAgICAgICBpZiBzY29wZS5fbW9kZSBpcyAnZGF0ZScgdGhlbiBfZGF0ZUZpbHRlciBzY29wZS5kYXRlLCAnTU1NJ1xuICAgICAgICBlbHNlICcnXG4gICAgICBtYWluOiAtPiAkc2NlLnRydXN0QXNIdG1sKFxuICAgICAgICBpZiBzY29wZS5fbW9kZSBpcyAnZGF0ZScgdGhlbiBfZGF0ZUZpbHRlciBzY29wZS5kYXRlLCAnZCdcbiAgICAgICAgZWxzZSBcIiN7X2RhdGVGaWx0ZXIgc2NvcGUuZGF0ZSwgJ2g6bW0nfTxzbWFsbD4je19kYXRlRmlsdGVyIHNjb3BlLmRhdGUsICdhJ308L3NtYWxsPlwiXG4gICAgICApXG4gICAgICBzdWI6IC0+XG4gICAgICAgIGlmIHNjb3BlLl9tb2RlIGlzICdkYXRlJyB0aGVuIF9kYXRlRmlsdGVyIHNjb3BlLmRhdGUsICd5eXl5J1xuICAgICAgICBlbHNlIF9kYXRlRmlsdGVyIHNjb3BlLmRhdGUsICdISDptbSdcbiAgICBzY29wZS5jYWxlbmRhciA9XG4gICAgICBfbW9udGg6IDBcbiAgICAgIF95ZWFyOiAwXG4gICAgICBfbW9udGhzOiAoX2RhdGVGaWx0ZXIgbmV3IERhdGUoMCwgaSksICdNTU1NJyBmb3IgaSBpbiBbMC4uMTFdKVxuICAgICAgb2Zmc2V0TWFyZ2luOiAtPiBcIiN7bmV3IERhdGUoQF95ZWFyLCBAX21vbnRoKS5nZXREYXkoKSAqIDMuNn1yZW1cIlxuICAgICAgaXNWaXNpYmxlOiAoZCkgLT4gbmV3IERhdGUoQF95ZWFyLCBAX21vbnRoLCBkKS5nZXRNb250aCgpIGlzIEBfbW9udGhcbiAgICAgIGNsYXNzOiAoZCkgLT5cbiAgICAgICAgaWYgbmV3IERhdGUoQF95ZWFyLCBAX21vbnRoLCBkKS5nZXRUaW1lKCkgaXMgbmV3IERhdGUoc2NvcGUuZGF0ZS5nZXRUaW1lKCkpLnNldEhvdXJzKDAsMCwwLDApIHRoZW4gXCJzZWxlY3RlZFwiXG4gICAgICAgIGVsc2UgaWYgbmV3IERhdGUoQF95ZWFyLCBAX21vbnRoLCBkKS5nZXRUaW1lKCkgaXMgbmV3IERhdGUoKS5zZXRIb3VycygwLDAsMCwwKSB0aGVuIFwidG9kYXlcIlxuICAgICAgICBlbHNlIFwiXCJcbiAgICAgIHNlbGVjdDogKGQpIC0+IHNjb3BlLmRhdGUuc2V0RnVsbFllYXIgQF95ZWFyLCBAX21vbnRoLCBkXG4gICAgICBtb250aENoYW5nZTogLT5cbiAgICAgICAgaWYgbm90IEBfeWVhcj8gb3IgaXNOYU4gQF95ZWFyIHRoZW4gQF95ZWFyID0gbmV3IERhdGUoKS5nZXRGdWxsWWVhcigpXG4gICAgICAgIHNjb3BlLmRhdGUuc2V0RnVsbFllYXIgQF95ZWFyLCBAX21vbnRoXG4gICAgICAgIGlmIHNjb3BlLmRhdGUuZ2V0TW9udGgoKSBpc250IEBfbW9udGggdGhlbiBzY29wZS5kYXRlLnNldERhdGUgMFxuICAgIHNjb3BlLmNsb2NrID1cbiAgICAgIF9taW51dGVzOiAwXG4gICAgICBfaG91cnM6IDBcbiAgICAgIF9pbmNIb3VyczogKGluYykgLT4gQF9ob3VycyA9IE1hdGgubWF4IDAsIE1hdGgubWluIDIzLCBAX2hvdXJzICsgaW5jXG4gICAgICBfaW5jTWludXRlczogKGluYykgLT4gQF9taW51dGVzID0gTWF0aC5tYXggMCwgTWF0aC5taW4gNTksIEBfbWludXRlcyArIGluY1xuICAgICAgX2hvdXI6IC0+XG4gICAgICAgIF9oID0gc2NvcGUuZGF0ZS5nZXRIb3VycygpXG4gICAgICAgIF9oID0gX2ggJSAxMlxuICAgICAgICByZXR1cm4gaWYgX2ggaXMgMCB0aGVuIDEyIGVsc2UgX2hcbiAgICAgIHNldEhvdXI6IChoKSAtPlxuICAgICAgICBpZiBoIGlzIDEyIGFuZCBAaXNBTSgpIHRoZW4gaCA9IDBcbiAgICAgICAgaCArPSBpZiBub3QgQGlzQU0oKSB0aGVuIDEyIGVsc2UgMFxuICAgICAgICBpZiBoIGlzIDI0IHRoZW4gaCA9IDEyXG4gICAgICAgIHNjb3BlLmRhdGUuc2V0SG91cnMgaFxuICAgICAgc2V0QU06IChiKSAtPiBpZiBiIGFuZCBub3QgQGlzQU0oKSB0aGVuIHNjb3BlLmRhdGUuc2V0SG91cnMoc2NvcGUuZGF0ZS5nZXRIb3VycygpIC0gMTIpIGVsc2UgaWYgbm90IGIgYW5kIEBpc0FNKCkgdGhlbiBzY29wZS5kYXRlLnNldEhvdXJzKHNjb3BlLmRhdGUuZ2V0SG91cnMoKSArIDEyKVxuICAgICAgaXNBTTogLT4gc2NvcGUuZGF0ZS5nZXRIb3VycygpIDwgMTJcbiAgICBzY29wZS4kd2F0Y2ggJ2Nsb2NrLl9taW51dGVzJywgKHZhbCkgLT5cbiAgICAgIGlmIHZhbD8gYW5kIHZhbCBpc250IHNjb3BlLmRhdGUuZ2V0TWludXRlcygpIHRoZW4gc2NvcGUuZGF0ZS5zZXRNaW51dGVzIHZhbFxuICAgIHNjb3BlLiR3YXRjaCAnY2xvY2suX2hvdXJzJywgKHZhbCkgLT5cbiAgICAgIGlmIHZhbD8gYW5kIHZhbCBpc250IHNjb3BlLmRhdGUuZ2V0SG91cnMoKSB0aGVuIHNjb3BlLmRhdGUuc2V0SG91cnMgdmFsXG4gICAgc2NvcGUuc2V0Tm93ID0gLT4gc2NvcGUuZGF0ZSA9IG5ldyBEYXRlKClcbiAgICBzY29wZS5fbW9kZSA9ICdkYXRlJ1xuICAgIHNjb3BlLm1vZGVDbGFzcyA9IC0+XG4gICAgICBpZiBzY29wZS5fZGlzcGxheU1vZGU/IHRoZW4gc2NvcGUuX21vZGUgPSBzY29wZS5fZGlzcGxheU1vZGVcbiAgICAgIGlmIHNjb3BlLl9kaXNwbGF5TW9kZSBpcyAnZnVsbCcgdGhlbiAnZnVsbC1tb2RlJ1xuICAgICAgZWxzZSBpZiBzY29wZS5fZGlzcGxheU1vZGUgaXMgJ3RpbWUnIHRoZW4gJ3RpbWUtb25seSdcbiAgICAgIGVsc2UgaWYgc2NvcGUuX2Rpc3BsYXlNb2RlIGlzICdkYXRlJyB0aGVuICdkYXRlLW9ubHknXG4gICAgICBlbHNlIGlmIHNjb3BlLl9tb2RlIGlzICdkYXRlJyB0aGVuICdkYXRlLW1vZGUnXG4gICAgICBlbHNlICd0aW1lLW1vZGUnXG4gICAgc2NvcGUubW9kZVN3aXRjaCA9IC0+IHNjb3BlLl9tb2RlID0gc2NvcGUuX2Rpc3BsYXlNb2RlID8gaWYgc2NvcGUuX21vZGUgaXMgJ2RhdGUnIHRoZW4gJ3RpbWUnIGVsc2UgJ2RhdGUnXG4gICAgc2NvcGUubW9kZVN3aXRjaFRleHQgPSAtPiBpZiBzY29wZS5fbW9kZSBpcyAnZGF0ZScgdGhlbiAnQ2xvY2snIGVsc2UgJ0NhbGVuZGFyJ1xuXV0iXX0=
//# sourceURL=coffeescript
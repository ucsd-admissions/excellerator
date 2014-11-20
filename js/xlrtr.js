;( function( $ ){

  var xlrtrConfig = {
    chooser: '.xlrtr-chooser',
    form: '.xlrtr-form',
    submit: '.xlrtr-submit',
    changeFile: '.xlrtr-change',
    infoPane: '.xlrtr-info',
    flipper: '.xlrtr-flipper',
    progress: '.xlrtr-progress',
    progressInterval: 250,
    viewLog: '.xlrtr-view-log'
  }

  var xlrtrCtrl = function( config ){

    var _this = this;

    this.chooser = $( config.chooser );
    this.form = $( config.form );
    this.submit = $( config.submit );
    this.changeFile = $( config.changeFile );
    this.infoPane = $( config.infoPane );
    this.flipper = $( config.flipper );
    this.progress = $( config.progress );
    this.viewLog = $( config.viewLog );


    /**
     * Set up the info pane
     */
    this.setup = function(){
      this.infoPane.html( "<span class='xlrtr-ok'>Uploading</span>" );
    }


    /**
     * Update the view based on data from log
     */
    this.update = function( data ){

      try{

        var log = JSON.parse( data );
        var completed = Math.floor( log.processed / log.total * 100 );

        console.log( log );

        if( log.hasOwnProperty( 'status' ) && log.status === 'error' ){
          this.infoPane.html( "<span class='xlrtr-error'>Error</span> " + log.error_message );
          return;
        }

        this.infoPane.html( "<span class='xlrtr-ok'>Importing</span> Row <strong>" + log.processed + "</strong> of <strong>" + log.total + "</strong>" );
        this.progress.width( completed + '%' );
      
        if( log.hasOwnProperty( 'status' ) && log.status === 'complete' ){
          this.infoPane.html( "<span class='xlrtr-ok'>Done!</span> " + log.processed + " rows processed." );
          return;
        }

      } catch( err ){}
    };


    /**
     * Handle change events on the file picker
     */
    this.chooser.on( 'change', function(){

      if( _this.chooser[0].files[0] ){
        _this.selectedFile = _this.chooser[0].files[0];
      }

      var filename = $(this)[0].files[0].name;

      setTimeout( function(){
        _this.flipper.addClass( 'flipped' );
      }, 200 );

      _this.infoPane.html( "<span class='xlrtr-note'>Selected file:</span> <strong>" + filename + "</strong>" );

    });


    /**
     * Handle form submission
     *
     * TODO: Standard form submit if <IE10
     */
    this.form.on( 'submit', function( e ){

      e.preventDefault();

      var uploadData = new FormData();

      uploadData.append( 'xlrtr_file', _this.selectedFile );
      uploadData.append( 'action', xlrtrData.upload.dest );
      uploadData.append( '_wpnonce', xlrtrData.upload.nonce );
      setTimeout( function(){
        _this.setup();
        _this.flipper.addClass( 'again' );
      }, 200 );
    
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: uploadData,
        success: function( data ){
          window.clearInterval( _this.xlrtrProgress );
          _this.update( data );
        },
        cache: false,
        contentType: false,
        processData: false
      });

      _this.xlrtrProgress = window.setInterval( function(){

        var progressData = {
          action: xlrtrData.progress.dest,
          _wpnonce: xlrtrData.progress.nonce
        }

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: progressData,
          success: function( data ){
            _this.update( data );
          }
        });

      }, config.progressInterval );

    });


    /**
     * Submit
     */
    this.submit.on( 'click', function(){
      _this.form.submit();
    });


    /**
     * Change file
     */
    this.changeFile.on( 'click', function(){
      _this.chooser.click();
    });


    /**
     * Toggle log
     */
    this.viewLog.on( 'click', function(){
      $(this).siblings( '.xlrtr-log' ).slideToggle();
    });


  };

  new xlrtrCtrl( xlrtrConfig );

})( jQuery );
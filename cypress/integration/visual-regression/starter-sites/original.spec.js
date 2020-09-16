describe( 'Starter Sites VR - https://demosites.io/original/', () => {
    let pages = [];
    it( 'Check frontpage', () => {
    	let frontpage = "https://demosites.io/original/"
	    cy.visit(frontpage );
		cy.captureDocument();
        cy.get( '#nv-primary-navigation-main' ).then( $headerMenu => {
            [ ...$headerMenu.find( '.menu-item a' ) ].forEach( $url => {
            	let url = $url.href;
            	if(url.includes("#")){
            		return;
	            }
            	if(frontpage.replace(/\/*$/, "") === url.replace(/\/*$/, "")){
            		return;
	            }

                pages.push( $url.href );
            } );
        } );
    } );
    it( 'Check additional pages', () => {
        pages.forEach( page => {
            cy.visit( page );
            cy.captureDocument();
        } );
    } );

} );

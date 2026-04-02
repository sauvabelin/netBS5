
function scanLi(li, clickCallable) {

    var submenu = li.querySelector(':scope > ul');

    if(submenu !== null) {

        submenu.originalHeight     = submenu.offsetHeight + "px";

        Array.from(submenu.querySelectorAll(':scope > li')).forEach(function(childLi) {

            scanLi(childLi, function(event) {

                event.stopPropagation();
                toggle(childLi);
            });
        });

        submenu.style.height    = "0px";
    }

    li.onclick = clickCallable;
}

function toggle(li) {

    if(li.classList.contains("active"))
        closeLi(li);

    else
        openLi(li);
}

function openLi(li) {

    li.classList.add('active');

    var submenu = li.querySelector(':scope > ul');

    if(submenu)
        submenu.style.height = submenu.originalHeight;
}

function closeLi(li) {

    li.classList.remove('active');

    var submenu = li.querySelector(':scope > ul');

    if(submenu)
        submenu.style.height = "0px";

    Array.from(li.querySelectorAll('li')).forEach(function(childLi) {
        closeLi(childLi);
    });
}

module.exports = function() {

    var linodelist  = document.querySelectorAll('.menu-content > ul > li:not(.menu-category)');
    var lis         = Array.from(linodelist);

    lis.forEach(function(topli) {

        scanLi(topli, function(event) {

            event.stopPropagation();

            //Close other top level lis
            lis.forEach(function(toclose) {
                if(toclose !== topli)
                    closeLi(toclose);
            });

            toggle(topli);
        });
    });

    //Init active link if any
    var cntnt   = document.querySelector('.menu-content');

    if(!cntnt)
        return;

    var item    = cntnt.querySelector('li.active');

    while(item) {

        if(item.tagName === 'LI')
            openLi(item);

        if(item === cntnt)
            break;

        item = item.parentElement;
    }

};
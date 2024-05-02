figma.showUI(__html__, {
   width: 410,
   height: 361,
   title: "SVG Converter"
})

figma.ui.onmessage = (message) => {
   if (message.type == 'figma_id') {
      figma.ui.postMessage({
         data: figma.currentUser.id
      });
   }

   if (message.type == 'save_user') {
      figma.clientStorage.setAsync("user", message.user);
   }

   if (message.type == 'get_user') {
      figma.clientStorage.getAsync('user').then(result => {
         figma.ui.postMessage({
            data: {
               figma_id: figma.currentUser.id,
               user: result
            }
         });
      });
   }

   if (message.type == 'copy') {
      if (figma.currentPage.selection.length > 0) {
         figma.clientStorage.getAsync('user').then(result => {
            if (result.subscribe) {
               figma.currentPage.selection.map(selected =>
                  selected.exportAsync({ format: 'SVG' })
                     .then(svgCode => figma.ui.postMessage({
                        data: svgToCssImg(String.fromCharCode.apply(null, new Uint16Array(svgCode)), message.typeCopy)
                     }))
               );
            } else {
               notify('The subscription has expired', true);
            }
         });
      } else {
         notify('Nothing is selected');
      }
   }

   if (message.type == 'message_success') {
      notify(message.data);
   }

   if (message.type == 'message_error') {
      notify(message.data, true);
   }

   if (message.type == 'settings') {
      if(message.flag){
         figma.ui.resize(410, 451)
      }else{
         figma.ui.resize(410, 361)
      }
   }
}

function notify(msg, error = false) {
   figma.notify(msg, { timeout: 4000, error: error });
}

function svgToCssImg(svg, typeCopy) {
   const symbols = /[\r\n%#()<>?[\\\]^`{|}]/g;
   let namespaced = addNameSpace(svg);
   let escaped = encodeSVG(namespaced);
   escaped = escaped.replaceAll(`"`, `'`);

   let result;

   if (typeCopy == 'CSS') {
      result = `background: url("data:image/svg+xml,${escaped}");`;
   } else if (typeCopy == 'IMG') {
      result = `<img src="data:image/svg+xml,${escaped}" alt="img">`;
   } else if (typeCopy == 'HTML') {
      result = svg;
   }

   return result;

   function addNameSpace(data) {
      if (data.indexOf(`http://www.w3.org/2000/svg`) < 0) {
         data = data.replace(/<svg/g, `<svg xmlns='http://www.w3.org/2000/svg'`);
      }

      return data;
   }

   function encodeSVG(data) {
      data = data.replace(/>\s{1,}</g, `><`);
      data = data.replace(/\s{2,}/g, ` `);

      return data.replace(symbols, encodeURIComponent);
   }
}

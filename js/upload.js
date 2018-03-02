;(function ($, undefined) {
 $.fn.uploadfile = function (setting) {
 var defaultSetting = {
 url : 'file.php',
 width : 600,
 height : 50,
 canDrag : true,
 canMultiple : true,
 success : function (fileName) { //�����ļ��ϴ��ɹ��Ļص�����
 },
 error : function (fileName) { //�����ļ��ϴ�ʧ�ܵĻص�����
 },
 complete : function () { //�ϴ���ɵĻص�����
 }
 };
 
 //�ж�������Ƿ�֧��FileReader
 if(!window.FileReader){
 alert('�����������֧��FileReader��������������');
 return;
 }
 
 setting = $.extend(true, {}, defaultSetting, setting);
 setting.width < 450 && (setting.width = 450);
 
 $(this).each(function (i, item) {
 var demoHtml = '';
 //�Ƿ������קͼƬ�ϴ�������dom�ṹ
 if(setting.canDrag){
 setting.height < 200 && (setting.height = 200);
 demoHtml += '<div class="file_sel">';
 demoHtml +=  '<div class="file_input">';
 demoHtml +=  '<div class="sel_file_img">';
 demoHtml +=  '<span><img src="img/add_img.png"/></span>';
 demoHtml +=  '</div>';
 demoHtml +=  '<div class="sel_file_btn">';
 demoHtml +=  '<input type="file"/>';
 demoHtml +=  '<button>���ѡ���ļ�</button>';
 demoHtml +=  '</div>';
 demoHtml +=  '</div>';
 demoHtml +=  '<div class="file_drag">';
 demoHtml +=  '<span>���߽��ļ��ϵ��˴�</span>';
 demoHtml +=  '</div>';
 demoHtml += '</div>';
 demoHtml += '<div class="file_info_handle">';
 demoHtml +=  '<div class="file_info">';
 demoHtml +=  '��ǰѡ����<span class="file_count">0</span>���ļ�����<span class="file_size">0</span>KB��';
 demoHtml +=  '<input type="file"/>';
 demoHtml +=  '<button class="continue_sel">����ѡ��</button>';
 demoHtml +=  '<button class="uploadfile">��ʼ�ϴ�</button>';
 demoHtml +=  '</div>';
 demoHtml += '</div>';
 demoHtml += '<div class="file_show">';
 demoHtml += '</div>';
 }else{
 setting.height < 50 && (setting.height = 50);
 $(item).addClass('noDrag');
 demoHtml += '<div class="file_info_handle">';
 demoHtml += '<div class="file_info">';
 demoHtml += '��ǰѡ����<span class="file_count">0</span>���ļ�����<span class="file_size">0</span>KB��';
 demoHtml += '<input type="file"/>';
 demoHtml += '<button class="continue_sel">����ѡ��</button>';
 demoHtml += '<button class="uploadfile">��ʼ�ϴ�</button>';
 demoHtml += '</div>';
 demoHtml += '</div>';
 demoHtml += '<div class="file_show">';
 demoHtml += '<div class="sel_file_btn">';
 demoHtml += '<input type="file"/>';
 demoHtml += '<div class="sel_btn"></div>';
 demoHtml += '</div>';
 demoHtml += '</div>';
 }
 $(item).css({
 width : setting.width,
 height : setting.height,
 display : 'block'
 });
 $(item).html(demoHtml);
 
 //��ȡDOM�ڵ�
 var fileArr = [],
 fileSize = 0,
 _this = $(item),
 fileDrag = $('.file_sel .file_drag', _this),
 selFileIpt = $('input[type=file]', _this),
 selFileBtn = selFileIpt.next();
 fileCount = $('.file_info_handle .file_info .file_count', _this),
 fileSz = $('.file_info_handle .file_info .file_size', _this),
 beginUpload = $('.file_info_handle .file_info .uploadfile', _this),
 fileShow = $('.file_show', _this),
 noDragSelFile = $('.file_show .sel_file_btn', _this);
  
 //��ʾ��ק�ϴ�����
 setting.canDrag || fileShow.show();
 
 //�Ƿ���Զ�ѡ
 setting.canMultiple && selFileIpt.attr('multiple', 'multiple');
 
 //���¼�
 selFileIpt.on('change', selFile);
 
 //�ð�ťȥ����input��click�¼�
 selFileBtn.on('click', function () { 
 $(this).prev().click();
 })
 
 fileDrag.on({
 dragover : dragOver, 
 drop : selFile
 })
 
 beginUpload.on('click', upLoadFile);
 
  
 
 // ѡ���ļ�
 function selFile (e) {
 e = e || window.event;
 //��ֹ�������Ĭ����Ϊ
 if(e.preventDefault){ 
  e.preventDefault(); 
 }else{
  e.returnValue = false;
 }
 var files = this.files || event.dataTransfer.files,
 src = 'img/',
 imgSrc;
 Array.prototype.forEach.call(files, function (item, i) {
 
  //��ֹ�ظ�ѡ����ͬ���ļ�
  var notExist = fileArr.some(function (existFile) {
  return existFile.name === item.name;
  })
  if(notExist && fileArr.length != 0){
  return !notExist;
  }
 
  fileArr.push(item);
  var fr = new FileReader();
  fr.readAsDataURL(item);
  fr.onload = function () {
 
  //�ж�չʾ���ļ�����
  if(item.type.indexOf("image") > -1){
  imgSrc = fr.result;
  }else if(item.name.indexOf("rar") > -1){
  imgSrc = src + 'rar.png';
  }else if(item.name.indexOf("zip") > -1){
  imgSrc = src + 'zip.png';
  }else if(item.type.indexOf("text") > -1){
  imgSrc = src + 'txt.png';
  }else{
  imgSrc = src + 'file.png';
  }
 
  //չʾѡ����ļ�
  var imgDom = $('<span class="img_box"><span class="up_load_success" title="�ϴ��ɹ�"></span><span class="img_handle"><span class="file_name" title="'+ item.name +'">'+ item.name +'</span><span class="icon-bin"></span></span><img src="'+ imgSrc +'"/>' + item.name + '</span>');
  if(setting.canDrag){
  fileShow.css('display') === 'none' && fileShow.show();
  fileShow.append(imgDom);
  }else{
  fileShow.css('display') === 'none' && fileShow.show();
  noDragSelFile.before(imgDom);
  }
  }
 })
 
 //ѡ����ļ�����Ϣ
 fileCount.html(fileArr.length);
 fileSz.html(getFileInfo());
 
 //��ֹ��ɾ�����ϴ�ѡ����ļ����ٴ�ѡ����ͬ���ļ���Ч�����⡣
 this.value =''; 
 }
 
 //��ק
 function dragOver (e) {
 var event = e || window.event;
 event.preventDefault();
 }
 
 //�ϴ��ļ�
 function upLoadFile () {
 if(!fileArr.length){
  alert('��ѡ���ļ�');
  return;
 }
 fileArr.forEach(function (item, i) {
  var upLoadSuccess = $('.img_box').eq(i).children('.up_load_success');
   
  //��ֹ�ظ��ϴ�
  if(upLoadSuccess.css('display') === 'block') return false; 
  var formData = new FormData();
  formData.append('file', item);
  $.ajax({
  url: setting.url,
  type: 'POST',
  cache: false,
  data: formData,
  processData: false,
  contentType: false
  }).done(function(res) {
  //�ϴ��ɹ�ͼ��
  upLoadSuccess.show();
 
  //�����ļ��ϴ��ɹ�ִ�лص�
  setting.success(item.name);
 
  //ȫ���ļ��ϴ����ִ�лص�����
  (i === (fileArr.length - 1)) && setting.complete();
  }).fail(function(res) {
  //�����ļ��ϴ�ʧ��ִ�лص�
  setting.error(item.name);
 
  (i === (fileArr.length - 1)) && setting.complete();
  });
 })
 }
 
 //�����ļ���Ϣ
 function getFileInfo () {
 //ÿ�����¼����С����ֹ��λ��ͬ��ɴ���
 fileSize = 0;
 fileArr.forEach(function (item, i) {
  fileSize += item.size;
 })
 fileSize = (fileSize / 1024).toFixed(2);
 return fileSize;
 }
 
 fileShow.on('click', '.icon-bin' , function () {
 //ɾ���ڵ�
 var index = $(this).parents('.img_box').index();
 $(this).parents('.img_box').remove();
 
 //ɾ���ϴ��ļ�
 fileArr.splice(index, 1);
 
 //�޸��ļ���Ϣ
 fileCount.html(fileArr.length);
 fileSz.html(getFileInfo());
 
 //�����ļ���ʾ����
 !setting.canDrag || fileArr.length || fileShow.hide();
 })
 })
 }
})(jQuery)
// The Vue build version to load with the `import` command
// (runtime-only or standalone) has been set in webpack.base.conf with an alias.
import Vue from 'vue'
import App from './App'
import router from './router'
import firebase from 'firebase'

Vue.config.productionTip = false

const config = {
  apiKey: 'AIzaSyBadOJ8lCChEQDESmEHPqvMubfylgTmeNs',
  authDomain: 'login-722d2.firebaseapp.com',
  databaseURL: 'https://login-722d2.firebaseio.com',
  projectId: 'login-722d2',
  storageBucket: 'login-722d2.appspot.com',
  messagingSenderId: '822071159981',
  appId: '1:822071159981:web:b46245728b49a786b5aa24'
}
firebase.initializeApp(config)

/* eslint-disable no-new */
new Vue({
  el: '#app',
  router,
  template: '<App/>',
  components: { App }
})

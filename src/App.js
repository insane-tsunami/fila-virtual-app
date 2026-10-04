import React from 'react';
import { BrowserRouter as Router, Switch, Route } from 'react-router-dom';

import Dashboard from './Dashboard';
import QrCode from './Dashboard/Qrcode';
import Perfil from './Dashboard/Perfil';
import Cliente from './Cliente';
import Home from './Home';
import Login from './Login';
import Register from './Register';

import GlobalStyles from './styles/global';

function App() {
  return (
    <>
      <GlobalStyles />
      <Router>
        <Switch>
          <Route exact path="/">
            <Home />
          </Route>
          <Route path="/cadastro">
            <Register />
          </Route>

          <Route path="/login">
            <Login />
          </Route>
          <Route exact path="/dashboard">
            <Dashboard />
          </Route>

          <Route path="/dashboard/qrcode">
            <QrCode />
          </Route>

          <Route path="/fila/:slug">
            <Cliente />
          </Route>

          <Route path="/dashboard/perfil">
            <Perfil />
          </Route>
        </Switch>
      </Router>
    </>
  );
}

export default App;
